<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class RequireAdministrator
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->role === 'administrator', 403, 'Akses tidak diizinkan.');
        return $next($request);
    }
}
