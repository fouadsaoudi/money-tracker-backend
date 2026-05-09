<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $user, string $token): string {
            $baseUrl = (string) (config('services.mobile_app.password_reset_url')
                ?: rtrim((string) config('app.url'), '/').'/reset-password');

            $separator = str_contains($baseUrl, '?') ? '&' : '?';

            return $baseUrl.$separator.http_build_query([
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]);
        });
    }
}
