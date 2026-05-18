<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExchangeRateController;
use App\Http\Controllers\GoalContributionController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\PreferenceController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\WalletConversionController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/me', [PreferenceController::class, 'show']);
    Route::patch('/me/preferences', [PreferenceController::class, 'update']);

    Route::get('/currencies', [CurrencyController::class, 'index']);

    Route::apiResource('wallets', WalletController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::apiResource('categories', CategoryController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::apiResource('exchange-rates', ExchangeRateController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::apiResource('goals', GoalController::class)
        ->only(['index', 'store', 'update', 'destroy']);
    Route::post('/goals/{goal}/contributions', [GoalContributionController::class, 'store']);

    Route::apiResource('transactions', TransactionController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::post('/wallet-conversions', [WalletConversionController::class, 'store']);

    Route::get('/dashboard', [DashboardController::class, 'show']);
    Route::get('/analytics', [DashboardController::class, 'analytics']);
});
