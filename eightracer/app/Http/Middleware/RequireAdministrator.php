<?php

namespace App\Http\Middleware;

use App\Services\Auth\SchoolAccess;
use Closure;
use Illuminate\Http\Request;

class RequireAdministrator
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(SchoolAccess::isStaff($request->user()), 403, 'Akses tidak diizinkan.');

        return $next($request);
    }
}
