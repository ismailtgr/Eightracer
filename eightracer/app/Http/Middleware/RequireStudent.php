<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;

class RequireStudent
{
    public function handle(Request $request, \Closure $next)
    {
        abort_unless($request->user()?->role === 'siswa', 403);

        return $next($request);
    }
}
