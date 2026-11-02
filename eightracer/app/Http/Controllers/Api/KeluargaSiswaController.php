<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\Wali;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KeluargaSiswaController extends Controller
{
    /**
     * Mengambil data orang tua siswa.
     */
    public function getOrangTua(int $siswaId): JsonResponse
    {
        $siswa = Siswa::findOrFail($siswaId);
        $orangTua = $siswa->orangTua;

        return response()->json([
            'status' => 'success',
            'message' => 'Data orang tua berhasil diambil',
            'data' => $orangTua,
        ]);
    }

    /**
     * Menyimpan atau memperbarui data orang tua siswa (Ayah/Ibu).
     */
    public function saveOrangTua(Request $request, int $siswaId): JsonResponse
    {
        $siswa = Siswa::findOrFail($siswaId);

        $validated = $request->validate([
            'jenis_orang_tua' => 'required|in:ayah,ibu',
            'nama' => 'required|string|max:150',
            'kondisi' => 'required|in:hidup,meninggal,bercerai',
            'pekerjaan' => 'nullable|string|max:100',
            'penghasilan' => 'nullable|numeric|between:0,9999999999999',
            'alamat' => 'nullable|string|max:2000',
            'kontak_wa' => 'nullable|string|max:20',
        ]);

        abort_if($validated['kondisi'] !== 'hidup' && ! $siswa->wali()->exists(), 422, 'Lengkapi wali lebih dahulu untuk kondisi orang tua ini.');
        $orangTua = OrangTua::updateOrCreate(
            [
                'siswa_id' => $siswa->id,
                'jenis_orang_tua' => $validated['jenis_orang_tua'],
            ],
            $validated
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Data orang tua berhasil disimpan',
            'data' => $orangTua,
        ], 200);
    }

    /**
     * Mengambil data wali siswa.
     */
    public function getWali(int $siswaId): JsonResponse
    {
        $siswa = Siswa::findOrFail($siswaId);
        $wali = $siswa->wali;

        return response()->json([
            'status' => 'success',
            'message' => 'Data wali berhasil diambil',
            'data' => $wali,
        ]);
    }

    /**
     * Menyimpan atau memperbarui data wali siswa.
     */
    public function saveWali(Request $request, int $siswaId): JsonResponse
    {
        $siswa = Siswa::findOrFail($siswaId);

        $validated = $request->validate([
            'nama' => 'required|string|max:150',
            'hubungan' => 'nullable|string|max:50',
            'pekerjaan' => 'nullable|string|max:100',
            'penghasilan' => 'nullable|numeric|between:0,9999999999999',
            'alamat' => 'nullable|string|max:2000',
            'kontak_wa' => 'nullable|string|max:20',
        ]);

        $wali = Wali::updateOrCreate(
            ['siswa_id' => $siswa->id],
            $validated
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Data wali berhasil disimpan',
            'data' => $wali,
        ], 200);
    }

    /**
     * Mengambil daftar media sosial siswa.
     */
    public function getMediaSosial(int $siswaId): JsonResponse
    {
        $siswa = Siswa::findOrFail($siswaId);
        $medsos = $siswa->mediaSosial;

        return response()->json([
            'status' => 'success',
            'message' => 'Data media sosial berhasil diambil',
            'data' => $medsos,
        ]);
    }

    /**
     * Menambahkan akun media sosial siswa.
     */
    public function addMediaSosial(Request $request, int $siswaId): JsonResponse
    {
        $siswa = Siswa::findOrFail($siswaId);

        $validated = $request->validate([
            'platform' => 'required|string|max:50',
            'username' => 'required|string|max:100',
        ]);

        $medsos = $siswa->mediaSosial()->create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Media sosial berhasil ditambahkan',
            'data' => $medsos,
        ], 201);
    }

    /**
     * Mengambil data riwayat bantuan siswa.
     */
    public function getBantuan(int $siswaId): JsonResponse
    {
        $siswa = Siswa::findOrFail($siswaId);
        $bantuan = $siswa->bantuanSiswa()->with('jenisBantuan')->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Data bantuan siswa berhasil diambil',
            'data' => $bantuan,
        ]);
    }

    /**
     * Menambahkan bantuan siswa.
     */
    public function addBantuan(Request $request, int $siswaId): JsonResponse
    {
        $siswa = Siswa::findOrFail($siswaId);

        $validated = $request->validate([
            'jenis_bantuan_id' => 'required|integer|exists:jenis_bantuan,id', 'nomor_penerima' => 'nullable|string|max:100',
            'tanggal_mulai' => 'nullable|date', 'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal_mulai', 'aktif' => 'required|boolean',
        ]);

        $bantuan = $siswa->bantuanSiswa()->create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Data bantuan siswa berhasil ditambahkan',
            'data' => $bantuan,
        ], 201);
    }
}
