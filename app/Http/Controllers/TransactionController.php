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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
            ->with(['category', 'wallet.currency', 'currency', 'reportingCurrency', 'invoiceImages'])
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
            $search = (string) $request->string('search');
            $query->where(function ($query) use ($search): void {
                $query->where('note', 'like', '%'.$search.'%')
                    ->orWhere('transactions.id', $search)
                    ->orWhereHas('category', fn ($query) => $query->where('name', 'like', '%'.$search.'%'));
            });
        }

        $perPage = min(max($request->integer('per_page', 5), 1), 100);

        return TransactionResource::collection($query->paginate($perPage)->withQueryString());
    }

    /**
     * @group Transactions
     * @authenticated
     */
    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $invoiceImages = $this->invoiceImageFiles($request);
        unset($payload['invoice_image'], $payload['invoice_images']);

        $transaction = DB::transaction(function () use ($request, $payload, $invoiceImages): Transaction {
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

            $this->storeInvoiceImages($invoiceImages, $transaction);

            $this->applyWalletDelta(
                $wallet,
                $this->signedAmount((string) $payload['type'], (string) $payload['amount'])
            );

            return $transaction;
        });

        $transaction->load(['category', 'wallet.currency', 'currency', 'reportingCurrency', 'invoiceImages']);

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

        return new TransactionResource($transaction->load(['category', 'wallet.currency', 'currency', 'reportingCurrency', 'invoiceImages']));
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
            $transaction->loadMissing('goalContribution.goal', 'sourceConversion', 'destinationConversion', 'invoiceImages');

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

            $invoiceImages = $this->invoiceImageFiles($request);
            $removeInvoiceImages = $request->boolean('remove_invoice_image')
                || $request->boolean('remove_invoice_images');

            if ($removeInvoiceImages) {
                $this->deleteTransactionInvoiceImages($transaction);
            } else {
                $this->deleteTransactionInvoiceImagesById(
                    $transaction,
                    $request->input('remove_invoice_image_ids', [])
                );
            }

            $this->storeInvoiceImages($invoiceImages, $transaction);

            if ($transaction->goalContribution !== null) {
                $transaction->goalContribution->forceFill([
                    'amount' => $payload['amount'],
                    'occurred_on' => $payload['occurred_on'],
                ])->save();

                $this->syncGoalCompletion($transaction->goalContribution->goal);
            }

            return $transaction;
        });

        return new TransactionResource($transaction->refresh()->load(['category', 'wallet.currency', 'currency', 'reportingCurrency', 'invoiceImages']));
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
                if ($sourceTransaction !== null) {
                    $this->deleteTransactionInvoiceImages($sourceTransaction->loadMissing('invoiceImages'));
                }
                if ($destinationTransaction !== null) {
                    $this->deleteTransactionInvoiceImages($destinationTransaction->loadMissing('invoiceImages'));
                }
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

            $this->deleteTransactionInvoiceImages($transaction);
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

    private function storeInvoiceImage(UploadedFile $image, Transaction $transaction): string
    {
        $directory = "transaction-invoices/{$transaction->user_id}";
        $extension = $image->extension();
        $timestamp = strtolower(now()->format('F_j_Y_h_i_s_a'));
        $filename = "{$timestamp}.{$extension}";
        $counter = 2;

        while (Storage::disk('public')->exists("{$directory}/{$filename}")) {
            $filename = "{$timestamp}_{$counter}.{$extension}";
            $counter++;
        }

        return $image->storeAs($directory, $filename, 'public');
    }

    /**
     * @param array<int, UploadedFile> $images
     */
    private function storeInvoiceImages(array $images, Transaction $transaction): void
    {
        foreach ($images as $image) {
            $transaction->invoiceImages()->create([
                'path' => $this->storeInvoiceImage($image, $transaction),
            ]);
        }
    }

    /**
     * @return array<int, UploadedFile>
     */
    private function invoiceImageFiles(Request $request): array
    {
        $files = [];
        $invoiceImages = $request->file('invoice_images', []);

        if ($invoiceImages instanceof UploadedFile) {
            $files[] = $invoiceImages;
        } elseif (is_array($invoiceImages)) {
            foreach ($invoiceImages as $image) {
                if ($image instanceof UploadedFile) {
                    $files[] = $image;
                }
            }
        }

        $invoiceImage = $request->file('invoice_image');
        if ($invoiceImage instanceof UploadedFile) {
            $files[] = $invoiceImage;
        }

        return $files;
    }

    private function deleteTransactionInvoiceImages(Transaction $transaction): void
    {
        $transaction->loadMissing('invoiceImages');

        foreach ($transaction->invoiceImages as $invoiceImage) {
            $this->deleteInvoiceImage($invoiceImage->path);
        }

        $transaction->invoiceImages()->delete();

        if ($transaction->invoice_image_path !== null) {
            $this->deleteInvoiceImage($transaction->invoice_image_path);
            $transaction->forceFill(['invoice_image_path' => null])->save();
        }
    }

    /**
     * @param mixed $ids
     */
    private function deleteTransactionInvoiceImagesById(Transaction $transaction, mixed $ids): void
    {
        if (! is_array($ids)) {
            return;
        }

        $ids = array_values(array_filter(array_map('intval', $ids)));

        if ($ids === []) {
            return;
        }

        $invoiceImages = $transaction->invoiceImages()->whereIn('id', $ids)->get();

        foreach ($invoiceImages as $invoiceImage) {
            $this->deleteInvoiceImage($invoiceImage->path);
            $invoiceImage->delete();
        }
    }

    private function deleteInvoiceImage(?string $path): void
    {
        if ($path !== null) {
            Storage::disk('public')->delete($path);
        }
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
