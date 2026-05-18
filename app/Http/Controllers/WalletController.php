<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWalletRequest;
use App\Http\Requests\UpdateWalletRequest;
use App\Http\Resources\WalletResource;
use App\Models\Currency;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WalletController extends Controller
{
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

        if (($payload['is_default'] ?? false) === true) {
            $request->user()
                ->wallets()
                ->whereKeyNot($wallet->id)
                ->update(['is_default' => false]);
        }

        $wallet->fill($payload)->save();

        return new WalletResource($wallet->refresh()->load('currency'));
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
