<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            \App\Http\Middleware\RequireApiJson::class,
            \App\Http\Middleware\ApiSecurity::class,
            \App\Http\Middleware\ValidateApiText::class,
        ]);
        $middleware->alias([
            'administrator' => \App\Http\Middleware\RequireAdministrator::class,
            'token.lifetime' => \App\Http\Middleware\EnforceTokenLifetime::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(fn (\Illuminate\Http\Request $request, \Throwable $error) => $request->is('api/*') || $request->expectsJson());
        $exceptions->render(function (\Throwable $error, \Illuminate\Http\Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }
            if ($error instanceof \Illuminate\Validation\ValidationException) {
                return response()->json(['message' => 'Input tidak valid.', 'errors' => $error->errors()], 422);
            }
            $status = $error instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                ? $error->getStatusCode() : ($error instanceof \Illuminate\Auth\AuthenticationException ? 401 : 500);
            $messages = [401 => 'Silakan login kembali.', 403 => 'Akses tidak diizinkan.',
                404 => 'Data atau halaman tidak ditemukan.', 405 => 'Metode tidak diizinkan.',
                409 => 'Data berubah. Muat ulang dan coba kembali.', 413 => 'File atau input terlalu besar.',
                419 => 'Sesi tidak valid.', 429 => 'Terlalu banyak percobaan. Coba lagi nanti.',
                503 => 'Layanan sementara belum tersedia.'];
            return response()->json(['message' => $messages[$status] ?? 'Permintaan tidak dapat diproses.'], $status,
                $error instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface ? $error->getHeaders() : []);
        });
        $exceptions->report(function (\Throwable $error) {
            if (app()->bound('request') && request()->is('api/*')) {
                \Illuminate\Support\Facades\Log::error('API request failed', [
                    'exception_type' => get_class($error), 'route' => request()->route()?->uri(),
                ]);
                return false;
            }
        });
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response) {
            if (request()->is('api/*')) {
                $response->headers->set('Cache-Control', 'no-store, private');
                $response->headers->set('X-Content-Type-Options', 'nosniff');
                $response->headers->set('Referrer-Policy', 'no-referrer');
            }
            return $response;
        });
    })->create();
