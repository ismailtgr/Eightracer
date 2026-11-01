<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireApiJson
{
    public function handle(Request $request, Closure $next)
    {
        // API consumers receive JSON auth/validation errors, never a web-login redirect.
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
