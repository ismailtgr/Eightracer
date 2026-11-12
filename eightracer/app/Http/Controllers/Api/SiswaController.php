<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Models\Kelas;
use App\Models\RekomendasiBeasiswa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SiswaController extends Controller
{
    /**
     * Menampilkan daftar siswa (dengan pencarian dan paginasi).
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['search' => 'nullable|string|max:100', 'per_page' => 'sometimes|integer|between:1,100', 'page' => 'sometimes|integer|min:1', 'jenis_kelamin' => 'nullable|in:L,P', 'kelas_id' => 'nullable|integer|exists:kelas,id', 'angkatan' => 'nullable|digits:4', 'rekomendasi_published' => 'nullable|boolean']);
        $query = Siswa::select(['id', 'nama_lengkap', 'nisn', 'nis', 'jenis_kelamin', 'tahun_lulus'])->with(['riwayatKelas' => fn ($q) => $q->whereNull('tanggal_selesai')->with('kelas')]);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->whereLike('nama_lengkap', "%{$search}%")
                    ->orWhereLike('nisn', "%{$search}%")
                    ->orWhereLike('nis', "%{$search}%");
            });
        }

        if ($request->filled('jenis_kelamin')) {
            $query->where('jenis_kelamin', $request->input('jenis_kelamin'));
        }

        if ($request->filled('kelas_id')) {
            $query->whereHas('riwayatKelas', fn ($q) => $q->where('kelas_id', $request->integer('kelas_id'))->whereNull('tanggal_selesai'));
        }
        if ($request->filled('angkatan')) {
            $query->where('tahun_lulus', $request->input('angkatan'));
        }
        if ($request->has('rekomendasi_published')) {
            $latest = RekomendasiBeasiswa::selectRaw('MAX(id)')->groupBy('siswa_id', 'target_jenjang');
            $published = RekomendasiBeasiswa::select('siswa_id')->whereIn('id', $latest)->where('status_review', 'disetujui');
            $request->boolean('rekomendasi_published') ? $query->whereIn('id', $published) : $query->whereNotIn('id', $published);
        }
        $siswa = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'status' => 'success',
            'message' => 'Data siswa berhasil diambil',
            'data' => $siswa,
            'filters' => [
                'cohorts' => Siswa::whereNotNull('tahun_lulus')->distinct()->orderBy('tahun_lulus')->pluck('tahun_lulus'),
                'classrooms' => Kelas::orderBy('nama_kelas')->get(['id', 'nama_kelas']),
            ],
        ]);
    }

    /**
     * Menyimpan data siswa baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['nullable', Rule::exists('users', 'id')->where('role', 'siswa'), 'unique:siswa,user_id'],
            'registration_email' => 'nullable|email:rfc|max:255',
            'tanggal_masuk_sekolah' => 'nullable|date|before_or_equal:today',
            'tanggal_lulus' => 'nullable|date|before_or_equal:today',
            'tahun_lulus' => 'nullable|integer|between:1900,2100',
            'nisn' => 'required|digits:10|unique:siswa,nisn',
            'nis' => 'nullable|string|max:20|unique:siswa,nis',
            'nama_lengkap' => 'required|string|max:150',
            'nama_panggilan' => 'nullable|string|max:50',
            'jenis_kelamin' => 'required|in:L,P',
            'tempat_lahir' => 'nullable|string|max:100',
            'tanggal_lahir' => 'nullable|date|before_or_equal:today',
            'agama' => 'nullable|string|max:30',
            'golongan_darah' => 'nullable|string|max:5',
            'alamat_rumah' => 'nullable|string|max:2000',
            'tinggal_bersama' => 'nullable|string|max:50',
            'anak_ke' => 'nullable|integer|between:1,50',
            'warna_favorit' => 'nullable|string|max:50',
            'makanan_favorit' => 'nullable|string|max:100',
            'bahasa_keseharian' => 'nullable|string|max:50',
            'permainan_online' => 'nullable|string|max:100',
            'permainan_offline' => 'nullable|string|max:100',
            'nilai_tka' => 'nullable|numeric|between:0,100',
            'catatan' => 'nullable|string|max:2000',
        ]);

        if (isset($validated['registration_email'])) {
            $validated['registration_email'] = mb_strtolower(trim($validated['registration_email']));
        }
        $siswa = Siswa::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Biodata siswa berhasil ditambahkan',
            'data' => $siswa,
        ], 201);
    }

    /**
     * Menampilkan detail biodata lengkap siswa termasuk keluarga.
     */
    public function show(int $id): JsonResponse
    {
        $siswa = Siswa::with([
            'user',
            'saudaraTiri', 'hobi', 'citaCita',
            'wali',
            'orangTua',
            'mediaSosial',
            'bantuanSiswa.jenisBantuan',
            'riwayatKelas.kelas',
            'alumniProgress.alumniProgressBeasiswa',
        ])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Detail profil siswa berhasil diambil',
            'data' => $siswa,
        ]);
    }

    /**
     * Memperbarui biodata siswa.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $siswa = Siswa::findOrFail($id);

        $validated = $request->validate([
            'registration_email' => 'nullable|email:rfc|max:255',
            'tanggal_masuk_sekolah' => 'nullable|date|before_or_equal:today',
            'tanggal_lulus' => 'nullable|date|before_or_equal:today',
            'tahun_lulus' => 'nullable|integer|between:1900,2100',
            'nisn' => 'sometimes|required|digits:10|unique:siswa,nisn,'.$siswa->id,
            'nis' => 'nullable|string|max:20|unique:siswa,nis,'.$siswa->id,
            'nama_lengkap' => 'sometimes|required|string|max:150',
            'nama_panggilan' => 'nullable|string|max:50',
            'jenis_kelamin' => 'sometimes|required|in:L,P',
            'tempat_lahir' => 'nullable|string|max:100',
            'tanggal_lahir' => 'nullable|date|before_or_equal:today',
            'agama' => 'nullable|string|max:30',
            'golongan_darah' => 'nullable|string|max:5',
            'alamat_rumah' => 'nullable|string|max:2000',
            'tinggal_bersama' => 'nullable|string|max:50',
            'anak_ke' => 'nullable|integer|between:1,50',
            'warna_favorit' => 'nullable|string|max:50',
            'makanan_favorit' => 'nullable|string|max:100',
            'bahasa_keseharian' => 'nullable|string|max:50',
            'permainan_online' => 'nullable|string|max:100',
            'permainan_offline' => 'nullable|string|max:100',
            'nilai_tka' => 'nullable|numeric|between:0,100',
            'catatan' => 'nullable|string|max:2000',
        ]);

        if (isset($validated['registration_email'])) {
            $validated['registration_email'] = mb_strtolower(trim($validated['registration_email']));
        }
        $siswa->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Biodata siswa berhasil diperbarui',
            'data' => $siswa,
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
            'status' => 'success',
            'message' => 'Data siswa berhasil dihapus',
        ]);
    }
}
