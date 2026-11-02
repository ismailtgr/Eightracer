<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    public function requestLink(Request $request)
    {
        $data = $request->validate(['email' => 'required|email:rfc|max:255']);
        $this->configured();
        // Additional query closure prevents unverified addresses from receiving a token.
        try {
            Password::sendResetLink(['email' => $data['email'], fn ($q) => $q->whereNotNull('email_verified_at')]);
        } catch (\Throwable) {
            // Same response for delivery failure and unknown accounts; do not expose SMTP internals.
            \Illuminate\Support\Facades\Log::warning('Password reset delivery unavailable');
        }
        return response()->json(['message' => 'Jika akun memiliki email pemulihan terverifikasi, tautan akan dikirim.']);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email:rfc|max:255', 'token' => 'required|string|max:255',
            'password' => ['required', 'string', 'max:128', 'confirmed', PasswordRule::min(12)->letters()->mixedCase()->numbers()->symbols(),
                function ($attribute, $value, $fail) { if (strlen($value) > 72) { $fail('Password maksimal 72 byte.'); } }],
        ]);
        $this->configured();
        // Serialize resets for the same account, including broker validation and token consumption.
        $result = DB::transaction(function () use ($data) {
            User::where('email', $data['email'])->lockForUpdate()->first();
            return Password::reset([
                ...$data, fn ($q) => $q->whereNotNull('email_verified_at'),
            ], function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
                $user->tokens()->delete();
                DB::table('sessions')->where('user_id', $user->id)->delete();
                event(new PasswordReset($user));
            });
        });
        if ($result !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['token' => 'Tautan tidak valid atau sudah kedaluwarsa.']);
        }
        return response()->json(['message' => 'Password diperbarui. Silakan login kembali.']);
    }

    private function configured(): void
    {
        $url = config('security.password_reset_url');
        abort_unless(config('security.password_reset_enabled') && is_string($url)
            && filter_var($url, FILTER_VALIDATE_URL) && parse_url($url, PHP_URL_SCHEME) === 'https'
            && ! parse_url($url, PHP_URL_QUERY) && ! parse_url($url, PHP_URL_FRAGMENT)
            && ! parse_url($url, PHP_URL_USER)
            && ! in_array(config('mail.default'), ['log', 'array', null], true), 503);
    }
}
