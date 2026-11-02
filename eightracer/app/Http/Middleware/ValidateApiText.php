<?php
namespace App\Http\Middleware;
use App\Support\PlainText;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
class ValidateApiText
{
    public function handle(Request $request, Closure $next)
    {
        $check = function (array $values, string $prefix = '', int $depth = 0) use (&$check): void {
            if ($depth > 12 || count($values) > 500) {
                throw ValidationException::withMessages(['input' => 'Input terlalu besar atau terlalu dalam.']);
            }
            foreach ($values as $key => $value) {
                if (! preg_match('/^[a-zA-Z0-9_\-]{1,100}$/D', (string) $key)) {
                    throw ValidationException::withMessages(['input' => 'Nama field tidak valid.']);
                }
                $field = $prefix === '' ? (string) $key : $prefix.'.'.$key;
                if (in_array($key, ['password', 'password_confirmation', 'token'], true)) {
                    continue;
                }
                if (is_array($value)) {
                    $check($value, $field, $depth + 1);
                } elseif (is_string($value) && (mb_strlen($value) > 10000 || ! PlainText::safe($value))) {
                    throw ValidationException::withMessages([$field => 'Gunakan teks biasa dengan panjang yang wajar.']);
                }
            }
        };
        $check($request->input());
        return $next($request);
    }
}
