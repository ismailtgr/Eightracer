<?php

namespace App\Http\Middleware;

use App\Services\Auth\SchoolAccess;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class EnforceTokenLifetime
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(SchoolAccess::canAuthenticate($request->user()), 403, 'Akun belum aktif. Hubungi admin sekolah.');
        $token = $request->user()?->currentAccessToken();
        abort_unless($token instanceof PersonalAccessToken, 401, 'Silakan login kembali.');
        $hours = SchoolAccess::isStaff($request->user()) ? 72 : 168;
        abort_if(! $token->expires_at || $token->expires_at->isPast()
            || ! $token->created_at || $token->created_at->copy()->addHours($hours)->isPast(), 401, 'Silakan login kembali.');

        return $next($request);
    }
}
