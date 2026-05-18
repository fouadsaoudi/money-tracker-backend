<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWalletConversionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\WalletConversion;
use App\Services\CurrencyConversionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class WalletConversionController extends Controller
{
    public function __construct(
        private readonly CurrencyConversionService $currencyConversionService,
    ) {
    }

    /**
     * @group Wallet conversions
     * @authenticated
     */
    public function store(StoreWalletConversionRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $result = DB::transaction(function () use ($request, $payload): array {
            $sourceWallet = $this->walletForConversion($request, (int) $payload['source_wallet_id']);
            $destinationWallet = $this->walletForConversion($request, (int) $payload['destination_wallet_id']);
            $occurredOn = Carbon::parse((string) $payload['occurred_on']);
            $sourceAmount = (string) $payload['source_amount'];
            $destinationAmount = (string) $payload['destination_amount'];
            $convertedAbs = $this->reportingAmount(
                $request,
                $sourceWallet,
                $destinationWallet,
                $sourceAmount,
                $destinationAmount,
                $occurredOn,
            );

            $category = Category::query()->firstOrCreate(
                ['user_id' => $request->user()->id, 'name' => 'Conversions'],
                ['color' => '#0f766e', 'icon' => 'currency_exchange', 'is_archived' => false]
            );

            if ($category->is_archived) {
                $category->forceFill(['is_archived' => false])->save();
            }

            $note = trim((string) ($payload['note'] ?? ''));
            $sourceNote = 'Converted to '.$destinationWallet->currency->code.($note === '' ? '' : ' - '.$note);
            $destinationNote = 'Converted from '.$sourceWallet->currency->code.($note === '' ? '' : ' - '.$note);

            $sourceTransaction = $request->user()->transactions()->create([
                'category_id' => $category->id,
                'wallet_id' => $sourceWallet->id,
                'currency_id' => $sourceWallet->currency_id,
                'type' => 'outgoing',
                'amount' => $sourceAmount,
                'note' => $sourceNote,
                'occurred_on' => $occurredOn,
                'reporting_currency_id' => $request->user()->reporting_currency_id,
                'exchange_rate_snapshot' => $this->rateFor($convertedAbs, $sourceAmount),
                'converted_amount' => bcmul($convertedAbs, '-1', 4),
            ]);

            $destinationTransaction = $request->user()->transactions()->create([
                'category_id' => $category->id,
                'wallet_id' => $destinationWallet->id,
                'currency_id' => $destinationWallet->currency_id,
                'type' => 'incoming',
                'amount' => $destinationAmount,
                'note' => $destinationNote,
                'occurred_on' => $occurredOn,
                'reporting_currency_id' => $request->user()->reporting_currency_id,
                'exchange_rate_snapshot' => $this->rateFor($convertedAbs, $destinationAmount),
                'converted_amount' => $convertedAbs,
            ]);

            $sourceWallet->forceFill([
                'balance' => bcsub((string) $sourceWallet->balance, $sourceAmount, 4),
            ])->save();
            $destinationWallet->forceFill([
                'balance' => bcadd((string) $destinationWallet->balance, $destinationAmount, 4),
            ])->save();

            WalletConversion::query()->create([
                'user_id' => $request->user()->id,
                'source_wallet_id' => $sourceWallet->id,
                'destination_wallet_id' => $destinationWallet->id,
                'source_transaction_id' => $sourceTransaction->id,
                'destination_transaction_id' => $destinationTransaction->id,
                'source_amount' => $sourceAmount,
                'destination_amount' => $destinationAmount,
                'occurred_on' => $occurredOn,
            ]);

            return [
                $sourceTransaction->load(['category', 'wallet.currency', 'currency', 'reportingCurrency']),
                $destinationTransaction->load(['category', 'wallet.currency', 'currency', 'reportingCurrency']),
            ];
        });

        return response()->json([
            'data' => [
                'source_transaction' => new TransactionResource($result[0]),
                'destination_transaction' => new TransactionResource($result[1]),
            ],
        ], 201);
    }

    private function walletForConversion(StoreWalletConversionRequest $request, int $walletId): Wallet
    {
        return Wallet::query()
            ->with('currency')
            ->where('user_id', $request->user()->id)
            ->whereKey($walletId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function reportingAmount(
        StoreWalletConversionRequest $request,
        Wallet $sourceWallet,
        Wallet $destinationWallet,
        string $sourceAmount,
        string $destinationAmount,
        Carbon $occurredOn,
    ): string {
        $reportingCurrencyId = $request->user()->reporting_currency_id;

        if ($sourceWallet->currency_id === $reportingCurrencyId) {
            return bcadd($sourceAmount, '0', 4);
        }

        if ($destinationWallet->currency_id === $reportingCurrencyId) {
            return bcadd($destinationAmount, '0', 4);
        }

        $snapshot = $this->currencyConversionService->snapshot(
            $request->user()->loadMissing('reportingCurrency'),
            $sourceWallet->currency_id,
            'incoming',
            $sourceAmount,
            $occurredOn,
        );

        return $snapshot['converted_amount'];
    }

    private function rateFor(string $reportingAmount, string $nativeAmount): string
    {
        return bcdiv($reportingAmount, $nativeAmount, 8);
    }
}
