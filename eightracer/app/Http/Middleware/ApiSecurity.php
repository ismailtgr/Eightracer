<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class ApiSecurity
{
    public function handle(Request $request, Closure $next)
    {
        if (app()->isProduction() || config('security.require_https')) {
            abort_unless($request->isSecure(), 403, 'Koneksi HTTPS diperlukan.');
        }
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Cache-Control', 'no-store, private');
        if (app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }
        return $response;
    }
}
