<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class SiswaController extends Controller
{
    public function getProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $siswa = Siswa::with([
            'user',
            'nilaiRapor.mataPelajaran',
            'asesmenKarakteristik',
            'rekomendasiProdi.klaster',
        ])->where('user_id', $user->id)->first();

        if (! $siswa) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data profil siswa tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data profil siswa berhasil diambil.',
            'data' => $siswa,
        ], 200);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $siswa = Siswa::where('user_id', $user->id)->first();

        if (! $siswa) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data profil siswa tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'username' => ['sometimes', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user->id)],
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

        if (isset($validated['username'])) {
            $user->update(['username' => $validated['username']]);
            unset($validated['username']);
        }

        $siswa->update($validated);

        $siswa->load([
            'user',
            'nilaiRapor.mataPelajaran',
            'asesmenKarakteristik',
            'rekomendasiProdi.klaster',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Data profil siswa berhasil diperbarui.',
            'data' => $siswa,
        ], 200);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Password saat ini tidak sesuai.',
            ], 422);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Password berhasil diperbarui.',
        ], 200);
    }
}
