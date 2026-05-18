<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePreferencesRequest;
use App\Http\Resources\CurrencyResource;
use Illuminate\Http\JsonResponse;

class PreferenceController extends Controller
{
    /**
     * @group Profile
     * @authenticated
     */
    public function show(): JsonResponse
    {
        $user = request()->user()->load('reportingCurrency');

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'reporting_currency' => new CurrencyResource($user->reportingCurrency),
            ],
        ]);
    }

    /**
     * @group Profile
     * @authenticated
     */
    public function update(UpdatePreferencesRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->forceFill($request->validated())->save();
        $user->load('reportingCurrency');

        return response()->json([
            'message' => 'Preferences updated successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'reporting_currency' => new CurrencyResource($user->reportingCurrency),
            ],
        ]);
    }
}
