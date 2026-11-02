<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class EnforceTokenLifetime
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->email_verified_at, 403, 'Verifikasi email diperlukan.');
        $token = $request->user()?->currentAccessToken();
        abort_unless($token instanceof PersonalAccessToken, 401, 'Silakan login kembali.');
        $hours = $request->user()->role === 'administrator' ? 72 : 168;
        abort_if(! $token->expires_at || $token->expires_at->isPast()
            || ! $token->created_at || $token->created_at->copy()->addHours($hours)->isPast(), 401, 'Silakan login kembali.');

        return $next($request);
    }
}
