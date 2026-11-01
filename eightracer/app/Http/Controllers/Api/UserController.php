<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        if ($request->filled('username')) {
            $query->where('username', 'like', '%' . $request->query('username') . '%');
        }

        if ($request->filled('role')) {
            $query->where('role', $request->query('role'));
        }

        $users = $query->paginate(10);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar pengguna berhasil diambil.',
            'data' => $users,
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', Rule::in(['administrator', 'admin', 'guru', 'siswa'])],
        ]);

        $role = $validated['role'] === 'admin' ? 'administrator' : $validated['role'];

        $user = User::create([
            'username' => $validated['username'],
            'password' => Hash::make($validated['password']),
            'role' => $role,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Pengguna berhasil dibuat.',
            'data' => $user,
        ], 201);
    }

    public function show(string|int $id): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pengguna tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data pengguna berhasil diambil.',
            'data' => $user,
        ], 200);
    }

    public function update(Request $request, string|int $id): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pengguna tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user->id)],
            'role' => ['sometimes', Rule::in(['administrator', 'admin', 'guru', 'siswa'])],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        if (isset($validated['role'])) {
            $user->role = $validated['role'] === 'admin' ? 'administrator' : $validated['role'];
        }

        $user->username = $validated['username'];

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Pengguna berhasil diperbarui.',
            'data' => $user,
        ], 200);
    }

    public function destroy(string|int $id): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pengguna tidak ditemukan.',
            ], 404);
        }

        $user->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Akun berhasil dihapus.',
        ], 200);
    }
}
