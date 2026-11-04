<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|max:255|unique:users,username',
            'password' => 'required|string|min:8|confirmed',
            'role'     => 'required|in:administrator,guru,siswa',
        ]);

        $user = User::create([
            'username' => $validated['username'],
            'password' => Hash::make($validated['password']),
            'role'     => $validated['role'],
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Registrasi berhasil',
            'user'    => $user,
            'token'   => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($validated)) {
            throw ValidationException::withMessages([
                'username' => ['Username atau password salah.'],
            ]);
        }

        $user = User::where('username', $validated['username'])->firstOrFail();

        // Hapus token lama agar 1 sesi = 1 token
        $user->tokens()->delete();

        $token = $user->createToken('auth-token')->plainTextToken;

        $userPayload = $this->buildUserPayload($user);

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'data'    => [
                'token' => $token,
                'user'  => $userPayload,
            ],
            // Key lama dipertahankan agar frontend yang sudah ada tidak rusak
            'user'    => $userPayload,
            'token'   => $token,
        ]);
    }

    public function me(Request $request)
    {
        $userPayload = $this->buildUserPayload($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Data user berhasil diambil.',
            'data'    => [
                'user' => $userPayload,
            ],
            // Kompatibilitas: endpoint /me sebelumnya mengembalikan atribut user di root
            ...$userPayload,
        ]);
    }

    /**
     * Susun data user beserta role dan profil terkait (siswa / guru / administrator).
     */
    private function buildUserPayload(User $user): array
    {
        $payload = $user->only(['id', 'username', 'role', 'created_at', 'updated_at']);

        if ($user->role === 'siswa') {
            $siswa = $user->siswa()->with('riwayatKelas.kelas')->first();

            $payload['siswa'] = $siswa ? [
                'id'           => $siswa->id,
                'nisn'         => $siswa->nisn,
                'nama_lengkap' => $siswa->nama_lengkap,
                'kelas'        => optional(optional($siswa->riwayatKelas->first())->kelas)->nama_kelas,
            ] : null;
        } elseif ($user->role === 'administrator') {
            $payload['administrator'] = $user->administrator;
        } elseif ($user->role === 'guru') {
            $payload['guru'] = $user->guru;
        }

        return $payload;
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout berhasil',
        ]);
    }
}