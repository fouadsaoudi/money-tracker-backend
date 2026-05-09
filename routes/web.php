<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/reset-password', [AuthController::class, 'showResetPasswordForm'])
    ->name('password.reset');

Route::post('/reset-password', [AuthController::class, 'resetPasswordFromWeb'])
    ->name('password.update');
