<?php

namespace App\Providers;

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
        if ($this->app->isProduction()) {
            config(['app.debug' => false, 'session.secure' => true, 'session.http_only' => true, 'session.encrypt' => true]);
        }
        \Illuminate\Auth\Notifications\ResetPassword::createUrlUsing(function ($user, string $token) {
            return config('security.password_reset_url').'?'.http_build_query(['token' => $token, 'email' => $user->getEmailForPasswordReset()]);
        });
        \Illuminate\Support\Facades\RateLimiter::for('login', fn ($request) => [
            \Illuminate\Cache\RateLimiting\Limit::perMinute(20)->by('ip:'.$request->ip()),
            \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by('account:'.hash('sha256', mb_strtolower((string) $request->input('username')))),
        ]);
        \Illuminate\Support\Facades\RateLimiter::for('register', fn ($request) => \Illuminate\Cache\RateLimiting\Limit::perMinute(3)->by($request->ip()));
        \Illuminate\Support\Facades\RateLimiter::for('password-reset', fn ($request) => [
            \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by('reset-ip:'.$request->ip()),
            \Illuminate\Cache\RateLimiting\Limit::perMinute(3)->by('reset-email:'.hash('sha256', mb_strtolower((string) $request->input('email')))),
        ]);
        \Illuminate\Support\Facades\RateLimiter::for('api', fn ($request) => \Illuminate\Cache\RateLimiting\Limit::perMinute(120)->by($request->user()->id));
    }
}
