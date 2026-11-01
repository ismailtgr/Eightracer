<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class SiswaAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Siswa::with(['user', 'riwayatKelas.kelas']);

        if ($request->filled('nama_lengkap')) {
            $query->where('nama_lengkap', 'like', '%' . $request->query('nama_lengkap') . '%');
        }

        if ($request->filled('nisn')) {
            $query->where('nisn', 'like', '%' . $request->query('nisn') . '%');
        }

        if ($request->filled('kelas')) {
            $query->whereHas('riwayatKelas.kelas', function ($q) use ($request) {
                $q->where('nama_kelas', 'like', '%' . $request->query('kelas') . '%');
            });
        }

        $siswa = $query->paginate(10);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar data siswa berhasil diambil.',
            'data' => $siswa,
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['nullable', 'string', 'max:255', 'unique:users,username'],
            'password' => ['nullable', 'string', 'min:6'],
            'nisn' => ['required', 'string', 'max:255', 'unique:siswa,nisn'],
            'nis' => ['nullable', 'string', 'max:255'],
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'nama_panggilan' => ['nullable', 'string', 'max:255'],
            'jenis_kelamin' => ['required', Rule::in(['L', 'P'])],
            'tempat_lahir' => ['nullable', 'string', 'max:255'],
            'tanggal_lahir' => ['nullable', 'date'],
            'agama' => ['nullable', 'string', 'max:255'],
            'golongan_darah' => ['nullable', 'string', 'max:5'],
            'alamat_rumah' => ['nullable', 'string'],
            'tinggal_bersama' => ['nullable', 'string', 'max:255'],
            'anak_ke' => ['nullable', 'integer', 'min:1'],
            'warna_favorit' => ['nullable', 'string', 'max:255'],
            'makanan_favorit' => ['nullable', 'string', 'max:255'],
            'bahasa_keseharian' => ['nullable', 'string', 'max:255'],
            'permainan_online' => ['nullable', 'string', 'max:255'],
            'permainan_offline' => ['nullable', 'string', 'max:255'],
            'nilai_tka' => ['nullable', 'numeric', 'between:0,100'],
            'catatan' => ['nullable', 'string'],
        ]);

        $siswa = DB::transaction(function () use ($validated) {
            $username = $validated['username'] ?? $validated['nisn'];
            $password = $validated['password'] ?? 'password123';

            $user = User::create([
                'username' => $username,
                'password' => Hash::make($password),
                'role' => 'siswa',
            ]);

            unset($validated['username'], $validated['password']);
            $validated['user_id'] = $user->id;

            return Siswa::create($validated);
        });

        $siswa->load('user');

        return response()->json([
            'status' => 'success',
            'message' => 'Data siswa berhasil ditambahkan.',
            'data' => $siswa,
        ], 201);
    }

    public function show(string|int $id): JsonResponse
    {
        $siswa = Siswa::with([
            'user',
            'riwayatKelas.kelas',
            'nilaiRapor.mataPelajaran',
            'asesmenKarakteristik',
            'rekomendasiProdi.klaster',
        ])->find($id);

        if (! $siswa) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data siswa tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data detail siswa berhasil diambil.',
            'data' => $siswa,
        ], 200);
    }

    public function update(Request $request, string|int $id): JsonResponse
    {
        $siswa = Siswa::find($id);

        if (! $siswa) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data siswa tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'nisn' => ['sometimes', 'string', 'max:255', Rule::unique('siswa', 'nisn')->ignore($siswa->id)],
            'nis' => ['nullable', 'string', 'max:255'],
            'nama_lengkap' => ['sometimes', 'string', 'max:255'],
            'nama_panggilan' => ['nullable', 'string', 'max:255'],
            'jenis_kelamin' => ['sometimes', Rule::in(['L', 'P'])],
            'tempat_lahir' => ['nullable', 'string', 'max:255'],
            'tanggal_lahir' => ['nullable', 'date'],
            'agama' => ['nullable', 'string', 'max:255'],
            'golongan_darah' => ['nullable', 'string', 'max:5'],
            'alamat_rumah' => ['nullable', 'string'],
            'tinggal_bersama' => ['nullable', 'string', 'max:255'],
            'anak_ke' => ['nullable', 'integer', 'min:1'],
            'warna_favorit' => ['nullable', 'string', 'max:255'],
            'makanan_favorit' => ['nullable', 'string', 'max:255'],
            'bahasa_keseharian' => ['nullable', 'string', 'max:255'],
            'permainan_online' => ['nullable', 'string', 'max:255'],
            'permainan_offline' => ['nullable', 'string', 'max:255'],
            'nilai_tka' => ['nullable', 'numeric', 'between:0,100'],
            'catatan' => ['nullable', 'string'],
        ]);

        $siswa->update($validated);
        $siswa->load('user');

        return response()->json([
            'status' => 'success',
            'message' => 'Data siswa berhasil diperbarui.',
            'data' => $siswa,
        ], 200);
    }

    public function destroy(string|int $id): JsonResponse
    {
        $siswa = Siswa::find($id);

        if (! $siswa) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data siswa tidak ditemukan.',
            ], 404);
        }

        $user = $siswa->user;

        if ($user) {
            $user->delete();
        } else {
            $siswa->delete();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data siswa berhasil dihapus.',
        ], 200);
    }
}
