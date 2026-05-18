<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\CurrencyConversionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionController extends Controller
{
    public function __construct(
        private readonly CurrencyConversionService $currencyConversionService,
    ) {
    }

    /**
     * @group Transactions
     * @authenticated
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $request->user()
            ->transactions()
            ->with(['category', 'wallet.currency', 'currency', 'reportingCurrency'])
            ->orderByDesc('occurred_on')
            ->orderByDesc('id');

        if ($request->filled('from')) {
            $query->where('occurred_on', '>=', Carbon::parse((string) $request->string('from'))->startOfDay());
        }

        if ($request->filled('to')) {
            $query->where('occurred_on', '<=', Carbon::parse((string) $request->string('to'))->endOfDay());
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', (string) $request->string('type'));
        }

        if ($request->filled('currency_id')) {
            $query->where('currency_id', $request->integer('currency_id'));
        }

        if ($request->filled('search')) {
            $query->where('note', 'like', '%'.((string) $request->string('search')).'%');
        }

        return TransactionResource::collection($query->paginate(15)->withQueryString());
    }

    /**
     * @group Transactions
     * @authenticated
     */
    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $transaction = DB::transaction(function () use ($request, $payload): Transaction {
            $wallet = $this->walletForTransaction($request, (int) $payload['wallet_id']);
            $snapshot = $this->currencyConversionService->snapshot(
                $request->user()->loadMissing('reportingCurrency'),
                $wallet->currency_id,
                (string) $payload['type'],
                (string) $payload['amount'],
                Carbon::parse((string) $payload['occurred_on']),
            );

            $transaction = $request->user()->transactions()->create([
                ...$payload,
                'currency_id' => $wallet->currency_id,
                'reporting_currency_id' => $request->user()->reporting_currency_id,
                'exchange_rate_snapshot' => $snapshot['rate'],
                'converted_amount' => $snapshot['converted_amount'],
            ]);

            $this->applyWalletDelta(
                $wallet,
                $this->signedAmount((string) $payload['type'], (string) $payload['amount'])
            );

            return $transaction;
        });

        $transaction->load(['category', 'wallet.currency', 'currency', 'reportingCurrency']);

        return (new TransactionResource($transaction))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @group Transactions
     * @authenticated
     */
    public function show(Transaction $transaction): TransactionResource
    {
        abort_unless($transaction->user_id === request()->user()->id, 404);

        return new TransactionResource($transaction->load(['category', 'wallet.currency', 'currency', 'reportingCurrency']));
    }

    /**
     * @group Transactions
     * @authenticated
     */
    public function update(UpdateTransactionRequest $request, Transaction $transaction): TransactionResource
    {
        abort_unless($transaction->user_id === $request->user()->id, 404);

        $validated = $request->validated();
        $walletId = (int) ($validated['wallet_id'] ?? $transaction->wallet_id);

        if ($walletId === 0) {
            throw ValidationException::withMessages([
                'wallet_id' => ['A wallet is required for this transaction.'],
            ]);
        }

        $transaction = DB::transaction(function () use ($request, $transaction, $walletId): Transaction {
            $wallet = $this->walletForTransaction($request, $walletId);
            $transaction->loadMissing('goalContribution.goal', 'sourceConversion', 'destinationConversion');

            if ($transaction->sourceConversion !== null || $transaction->destinationConversion !== null) {
                throw ValidationException::withMessages([
                    'transaction' => ['Wallet conversion transactions cannot be edited directly.'],
                ]);
            }

            if (
                $transaction->goalContribution !== null
                && $wallet->currency_id !== $transaction->goalContribution->goal->currency_id
            ) {
                throw ValidationException::withMessages([
                    'wallet_id' => ['Choose a wallet that uses the same currency as the linked goal.'],
                ]);
            }

            if (
                $transaction->goalContribution !== null
                && $request->validated('type', $transaction->type) !== 'outgoing'
            ) {
                throw ValidationException::withMessages([
                    'type' => ['Goal contribution transactions must stay as expenses.'],
                ]);
            }

            $oldWallet = $transaction->wallet_id === $wallet->id
                ? $wallet
                : ($transaction->wallet_id === null
                    ? null
                    : $this->walletForTransaction($request, (int) $transaction->wallet_id));

            $payload = [
                'category_id' => $request->validated('category_id', $transaction->category_id),
                'wallet_id' => $wallet->id,
                'currency_id' => $wallet->currency_id,
                'type' => $request->validated('type', $transaction->type),
                'amount' => (string) $request->validated('amount', $transaction->amount),
                'note' => $request->exists('note') ? $request->validated('note') : $transaction->note,
                'occurred_on' => $request->validated('occurred_on', $transaction->occurred_on?->toISOString()),
            ];

            $snapshot = $this->currencyConversionService->snapshot(
                $request->user()->loadMissing('reportingCurrency'),
                $wallet->currency_id,
                (string) $payload['type'],
                (string) $payload['amount'],
                Carbon::parse((string) $payload['occurred_on']),
            );

            if ($oldWallet !== null) {
                $this->applyWalletDelta(
                    $oldWallet,
                    $this->reverseDelta($this->signedAmount($transaction->type, (string) $transaction->amount))
                );
            }

            $this->applyWalletDelta(
                $wallet,
                $this->signedAmount((string) $payload['type'], (string) $payload['amount'])
            );

            $transaction->fill([
                ...$payload,
                'reporting_currency_id' => $request->user()->reporting_currency_id,
                'exchange_rate_snapshot' => $snapshot['rate'],
                'converted_amount' => $snapshot['converted_amount'],
            ])->save();

            if ($transaction->goalContribution !== null) {
                $transaction->goalContribution->forceFill([
                    'amount' => $payload['amount'],
                    'occurred_on' => $payload['occurred_on'],
                ])->save();

                $this->syncGoalCompletion($transaction->goalContribution->goal);
            }

            return $transaction;
        });

        return new TransactionResource($transaction->refresh()->load(['category', 'wallet.currency', 'currency', 'reportingCurrency']));
    }

    /**
     * @group Transactions
     * @authenticated
     */
    public function destroy(Transaction $transaction): JsonResponse
    {
        abort_unless($transaction->user_id === request()->user()->id, 404);

        DB::transaction(function () use ($transaction): void {
            $transaction->loadMissing('goalContribution.goal', 'sourceConversion', 'destinationConversion');

            $conversion = $transaction->sourceConversion ?? $transaction->destinationConversion;

            if ($conversion !== null) {
                $sourceTransaction = Transaction::query()->find($conversion->source_transaction_id);
                $destinationTransaction = Transaction::query()->find($conversion->destination_transaction_id);

                if ($sourceTransaction !== null && $sourceTransaction->wallet_id !== null) {
                    $sourceWallet = Wallet::query()
                        ->where('user_id', $sourceTransaction->user_id)
                        ->whereKey($sourceTransaction->wallet_id)
                        ->lockForUpdate()
                        ->first();

                    if ($sourceWallet !== null) {
                        $this->applyWalletDelta(
                            $sourceWallet,
                            $this->reverseDelta($this->signedAmount($sourceTransaction->type, (string) $sourceTransaction->amount))
                        );
                    }
                }

                if ($destinationTransaction !== null && $destinationTransaction->wallet_id !== null) {
                    $destinationWallet = Wallet::query()
                        ->where('user_id', $destinationTransaction->user_id)
                        ->whereKey($destinationTransaction->wallet_id)
                        ->lockForUpdate()
                        ->first();

                    if ($destinationWallet !== null) {
                        $this->applyWalletDelta(
                            $destinationWallet,
                            $this->reverseDelta($this->signedAmount($destinationTransaction->type, (string) $destinationTransaction->amount))
                        );
                    }
                }

                $conversion->delete();
                $sourceTransaction?->delete();
                $destinationTransaction?->delete();

                return;
            }

            if ($transaction->wallet_id !== null) {
                $wallet = Wallet::query()
                    ->where('user_id', $transaction->user_id)
                    ->whereKey($transaction->wallet_id)
                    ->lockForUpdate()
                    ->first();

                if ($wallet !== null) {
                    $this->applyWalletDelta(
                        $wallet,
                        $this->reverseDelta($this->signedAmount($transaction->type, (string) $transaction->amount))
                    );
                }
            }

            if ($transaction->goalContribution !== null) {
                $goal = $transaction->goalContribution->goal;
                $transaction->goalContribution->delete();
                $this->syncGoalCompletion($goal);
            }

            $transaction->delete();
        });

        return response()->json([
            'message' => 'Transaction deleted successfully.',
        ]);
    }

    private function walletForTransaction(Request $request, int $walletId): Wallet
    {
        return Wallet::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($walletId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function signedAmount(string $type, string $amount): string
    {
        return $type === 'outgoing'
            ? bcmul($amount, '-1', 4)
            : bcadd($amount, '0', 4);
    }

    private function reverseDelta(string $delta): string
    {
        return bcmul($delta, '-1', 4);
    }

    private function applyWalletDelta(Wallet $wallet, string $delta): void
    {
        $wallet->forceFill([
            'balance' => bcadd((string) $wallet->balance, $delta, 4),
        ])->save();
    }

    private function syncGoalCompletion($goal): void
    {
        $current = (string) $goal->contributions()->sum('amount');

        $goal->forceFill([
            'completed_at' => bccomp($current, (string) $goal->target_amount, 4) >= 0
                ? now()
                : null,
        ])->save();
    }
}
