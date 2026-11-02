<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
class AuthController extends Controller
{
    public function register(Request $request)
    {
        // NISN alone is not proof of ownership; unverified account claiming stays closed.
        abort(403, 'Pendaftaran akun dilakukan melalui admin sekolah.');
    }
    public function login(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|max:255', 'password' => 'required|string|max:1024',
        ]);
        [$user, $token] = DB::transaction(function () use ($validated) {
            $user = User::where('username', $validated['username'])->lockForUpdate()->first();
            $hash = $user?->password ?? Hash::make(bin2hex(random_bytes(32)));
            if (! Hash::check($validated['password'], $hash) || ! $user) {
                throw ValidationException::withMessages(['username' => ['Username atau password salah.']]);
            }
            if (Hash::needsRehash($user->password)) {
                $user->forceFill(['password' => Hash::make($validated['password'])])->save();
            }
            if ($user->role === 'administrator') {
                $user->tokens()->delete();
            }
            $token = $user->createToken('auth-token', ['*'], now()->addHours($user->role === 'administrator' ? 72 : 168))->plainTextToken;
            return [$user, $token];
        });
        return response()->json(['message' => 'Login berhasil', 'user' => $user, 'token' => $token]);
    }
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logout berhasil']);
    }
}
