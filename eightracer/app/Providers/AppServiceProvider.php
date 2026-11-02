<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('login', fn ($request) => [
            Limit::perMinute(20)->by('ip:'.$request->ip()),
            Limit::perMinute(5)->by('account:'.hash('sha256', mb_strtolower((string) $request->input('email')))),
        ]);
        RateLimiter::for('register', fn ($request) => Limit::perMinute(3)->by($request->ip()));
        RateLimiter::for('password-reset', fn ($request) => [
            Limit::perMinute(5)->by('reset-ip:'.$request->ip()),
            Limit::perMinute(3)->by('reset-email:'.hash('sha256', mb_strtolower((string) $request->input('email')))),
        ]);
        RateLimiter::for('otp-request', fn ($request) => [
            Limit::perMinutes(15, 3)->by('otp-email:'.hash('sha256', mb_strtolower((string) $request->input('email', $request->user()?->email)))),
            Limit::perMinutes(15, 15)->by('otp-ip:'.$request->ip()),
        ]);
        RateLimiter::for('otp-verify', fn ($request) => Limit::perMinute(10)->by('otp-check:'.$request->ip()));
        RateLimiter::for('api', fn ($request) => Limit::perMinute(120)->by($request->user()->id));
    }
}
