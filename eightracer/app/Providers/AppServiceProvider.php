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
            Limit::perMinute(5)->by('account:'.hash('sha256', mb_strtolower((string) ($request->input('email') ?: 'nisn:'.$request->input('nisn'))))),
        ]);
        RateLimiter::for('register', fn ($request) => Limit::perMinute(3)->by($request->ip()));
        RateLimiter::for('password-reset', fn ($request) => [
            Limit::perMinute(5)->by('reset-ip:'.$request->ip()),
            Limit::perMinute(3)->by('reset-email:'.hash('sha256', mb_strtolower((string) ($request->input('email') ?: 'nisn:'.$request->input('nisn'))))),
        ]);
        RateLimiter::for('otp-request', fn ($request) => [
            Limit::perMinutes(15, 3)->by('otp-email:'.hash('sha256', mb_strtolower((string) $request->input('email', $request->user()?->email)))),
            Limit::perMinutes(15, 15)->by('otp-ip:'.$request->ip()),
        ]);
        RateLimiter::for('otp-verify', fn ($request) => Limit::perMinute(10)->by('otp-check:'.$request->ip()));
        foreach (['bulk-preview' => 3, 'bulk-commit' => 3, 'book-pdf' => 3, 'profile-submission' => 5] as $name => $max) {
            RateLimiter::for($name, fn ($request) => Limit::perMinute($max)->by($name.':'.$request->user()->id));
        }
        RateLimiter::for('scholarship-chat', fn ($request) => Limit::perMinute(max(10, min(60, (int) config('gemini.chat_requests_per_minute', 30))))
            ->by('scholarship-chat:v2:'.$request->user()->id));
        RateLimiter::for('api', fn ($request) => Limit::perMinute(120)->by($request->user()->id));
    }
}
