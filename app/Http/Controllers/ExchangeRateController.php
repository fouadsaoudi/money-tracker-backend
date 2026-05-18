<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExchangeRateRequest;
use App\Http\Requests\UpdateExchangeRateRequest;
use App\Http\Resources\ExchangeRateResource;
use App\Models\ExchangeRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExchangeRateController extends Controller
{
    /**
     * @group Exchange Rates
     * @authenticated
     */
    public function index(): AnonymousResourceCollection
    {
        return ExchangeRateResource::collection(
            request()->user()
                ->exchangeRates()
                ->with(['fromCurrency', 'toCurrency'])
                ->latest('effective_at')
                ->latest('id')
                ->get()
        );
    }

    /**
     * @group Exchange Rates
     * @authenticated
     */
    public function store(StoreExchangeRateRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $payload['effective_at'] ??= now();

        $exchangeRate = $request->user()->exchangeRates()->create($payload);
        $exchangeRate->load(['fromCurrency', 'toCurrency']);

        return (new ExchangeRateResource($exchangeRate))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @group Exchange Rates
     * @authenticated
     */
    public function update(UpdateExchangeRateRequest $request, ExchangeRate $exchangeRate): ExchangeRateResource
    {
        abort_unless($exchangeRate->user_id === $request->user()->id, 404);

        $payload = $request->validated();
        $exchangeRate->fill($payload)->save();

        return new ExchangeRateResource($exchangeRate->load(['fromCurrency', 'toCurrency']));
    }

    /**
     * @group Exchange Rates
     * @authenticated
     */
    public function destroy(ExchangeRate $exchangeRate): JsonResponse
    {
        abort_unless($exchangeRate->user_id === request()->user()->id, 404);

        $exchangeRate->delete();

        return response()->json([
            'message' => 'Exchange rate deleted successfully.',
        ]);
    }
}
