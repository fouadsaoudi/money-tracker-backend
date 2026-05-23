<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWalletRequest;
use App\Http\Requests\UpdateWalletRequest;
use App\Http\Resources\WalletResource;
use App\Models\Currency;
use App\Models\Wallet;
use App\Services\CurrencyConversionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    public function __construct(
        private readonly CurrencyConversionService $currencyConversionService,
    ) {
    }

    /**
     * @group Wallets
     * @authenticated
     */
    public function index(): AnonymousResourceCollection
    {
        return WalletResource::collection(
            request()->user()
                ->wallets()
                ->with('currency')
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get()
        );
    }

    /**
     * @group Wallets
     * @authenticated
     */
    public function store(StoreWalletRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $currency = Currency::query()->findOrFail((int) $payload['currency_id']);
        $makeDefault = (bool) ($payload['is_default'] ?? false)
            || ! $request->user()->wallets()->exists();

        if ($makeDefault) {
            $request->user()->wallets()->update(['is_default' => false]);
        }

        $wallet = $request->user()->wallets()->create([
            'currency_id' => $currency->id,
            'name' => $payload['name'] ?? $currency->code.' Wallet',
            'balance' => $payload['balance'] ?? 0,
            'is_default' => $makeDefault,
        ]);

        return (new WalletResource($wallet->load('currency')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @group Wallets
     * @authenticated
     */
    public function update(UpdateWalletRequest $request, Wallet $wallet): WalletResource
    {
        abort_unless($wallet->user_id === $request->user()->id, 404);

        $payload = $request->validated();

        DB::transaction(function () use ($request, $wallet, $payload): void {
            if (($payload['is_default'] ?? false) === true) {
                $request->user()
                    ->wallets()
                    ->whereKeyNot($wallet->id)
                    ->update(['is_default' => false]);
            }

            if (array_key_exists('balance', $payload)) {
                $this->recordBalanceAdjustment($request, $wallet, (string) $payload['balance']);
            }

            $wallet->fill($payload)->save();
        });

        return new WalletResource($wallet->refresh()->load('currency'));
    }

    private function recordBalanceAdjustment(UpdateWalletRequest $request, Wallet $wallet, string $newBalance): void
    {
        $delta = bcsub($newBalance, (string) $wallet->balance, 4);

        if (bccomp($delta, '0', 4) === 0) {
            return;
        }

        $type = bccomp($delta, '0', 4) === 1 ? 'incoming' : 'outgoing';
        $amount = ltrim($delta, '-');
        $occurredOn = Carbon::now();
        $snapshot = $this->currencyConversionService->snapshot(
            $request->user()->loadMissing('reportingCurrency'),
            $wallet->currency_id,
            $type,
            $amount,
            $occurredOn,
        );
        $category = $request->user()->categories()->firstOrCreate(
            ['name' => 'Wallet adjustment'],
            ['color' => '#2563eb', 'icon' => 'adjust', 'is_archived' => false],
        );

        $request->user()->transactions()->create([
            'category_id' => $category->id,
            'wallet_id' => $wallet->id,
            'currency_id' => $wallet->currency_id,
            'type' => $type,
            'amount' => $amount,
            'note' => 'Wallet adjustment',
            'occurred_on' => $occurredOn,
            'reporting_currency_id' => $request->user()->reporting_currency_id,
            'exchange_rate_snapshot' => $snapshot['rate'],
            'converted_amount' => $snapshot['converted_amount'],
        ]);
    }

    /**
     * @group Wallets
     * @authenticated
     */
    public function destroy(Wallet $wallet): JsonResponse
    {
        abort_unless($wallet->user_id === request()->user()->id, 404);

        if ($wallet->is_default) {
            return response()->json([
                'message' => 'The default wallet cannot be deleted.',
            ], 422);
        }

        if ($wallet->transactions()->exists()) {
            return response()->json([
                'message' => 'Wallets with transactions cannot be deleted.',
            ], 422);
        }

        $wallet->delete();

        return response()->json([
            'message' => 'Wallet deleted successfully.',
        ]);
    }
}
