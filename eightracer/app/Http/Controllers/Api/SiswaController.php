<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
