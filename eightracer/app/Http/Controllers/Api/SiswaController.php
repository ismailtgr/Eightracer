<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiswaController extends Controller
{
    /**
     * Menampilkan daftar siswa (dengan pencarian dan paginasi).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Siswa::with(['user', 'wali', 'orangTua']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('nisn', 'like', "%{$search}%")
                  ->orWhere('nis', 'like', "%{$search}%");
            });
        }

        if ($request->filled('jenis_kelamin')) {
            $query->where('jenis_kelamin', $request->input('jenis_kelamin'));
        }

        $siswa = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'status'  => 'success',
            'message' => 'Data siswa berhasil diambil',
            'data'    => $siswa,
        ]);
    }

    /**
     * Menyimpan data siswa baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id'           => 'nullable|exists:users,id',
            'nisn'              => 'required|string|max:20|unique:siswa,nisn',
            'nis'               => 'nullable|string|max:20|unique:siswa,nis',
            'nama_lengkap'      => 'required|string|max:150',
            'nama_panggilan'    => 'nullable|string|max:50',
            'jenis_kelamin'     => 'required|in:L,P',
            'tempat_lahir'      => 'nullable|string|max:100',
            'tanggal_lahir'     => 'nullable|date',
            'agama'             => 'nullable|string|max:30',
            'golongan_darah'    => 'nullable|string|max:5',
            'alamat_rumah'      => 'nullable|string',
            'tinggal_bersama'   => 'nullable|string|max:50',
            'anak_ke'           => 'nullable|integer',
            'warna_favorit'     => 'nullable|string|max:50',
            'makanan_favorit'   => 'nullable|string|max:100',
            'bahasa_keseharian' => 'nullable|string|max:50',
            'permainan_online'  => 'nullable|string|max:100',
            'permainan_offline' => 'nullable|string|max:100',
            'nilai_tka'         => 'nullable|numeric',
            'catatan'           => 'nullable|string',
        ]);

        $siswa = Siswa::create($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Biodata siswa berhasil ditambahkan',
            'data'    => $siswa,
        ], 201);
    }

    /**
     * Menampilkan detail biodata lengkap siswa termasuk keluarga.
     */
    public function show(int $id): JsonResponse
    {
        $siswa = Siswa::with([
            'user',
            'wali',
            'orangTua',
            'mediaSosial',
            'bantuanSiswa.jenisBantuan',
            'riwayatKelas.kelas',
            'alumniProgress.alumniProgressBeasiswa',
        ])->findOrFail($id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Detail profil siswa berhasil diambil',
            'data'    => $siswa,
        ]);
    }

    /**
     * Memperbarui biodata siswa.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $siswa = Siswa::findOrFail($id);

        $validated = $request->validate([
            'nisn'              => 'sometimes|required|string|max:20|unique:siswa,nisn,' . $siswa->id,
            'nis'               => 'nullable|string|max:20|unique:siswa,nis,' . $siswa->id,
            'nama_lengkap'      => 'sometimes|required|string|max:150',
            'nama_panggilan'    => 'nullable|string|max:50',
            'jenis_kelamin'     => 'sometimes|required|in:L,P',
            'tempat_lahir'      => 'nullable|string|max:100',
            'tanggal_lahir'     => 'nullable|date',
            'agama'             => 'nullable|string|max:30',
            'golongan_darah'    => 'nullable|string|max:5',
            'alamat_rumah'      => 'nullable|string',
            'tinggal_bersama'   => 'nullable|string|max:50',
            'anak_ke'           => 'nullable|integer',
            'warna_favorit'     => 'nullable|string|max:50',
            'makanan_favorit'   => 'nullable|string|max:100',
            'bahasa_keseharian' => 'nullable|string|max:50',
            'permainan_online'  => 'nullable|string|max:100',
            'permainan_offline' => 'nullable|string|max:100',
            'nilai_tka'         => 'nullable|numeric',
            'catatan'           => 'nullable|string',
        ]);

        $siswa->update($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Biodata siswa berhasil diperbarui',
            'data'    => $siswa,
        ]);
    }

    /**
     * Menghapus data siswa.
     */
    public function destroy(int $id): JsonResponse
    {
        $siswa = Siswa::findOrFail($id);
        $siswa->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Data siswa berhasil dihapus',
        ]);
    }
}
