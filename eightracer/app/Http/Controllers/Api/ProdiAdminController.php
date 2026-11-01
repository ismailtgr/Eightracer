<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KlasterProdi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProdiAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = KlasterProdi::with(['mataPelajaran']);

        if ($request->filled('nama_klaster')) {
            $query->where('nama_klaster', 'like', '%' . $request->query('nama_klaster') . '%');
        }

        if ($request->filled('nama_prodi')) {
            $query->where(function ($q) use ($request) {
                $term = '%' . $request->query('nama_prodi') . '%';
                $q->where('nama_klaster', 'like', $term)
                  ->orWhere('deskripsi', 'like', $term);
            });
        }

        if ($request->filled('kode_riasec')) {
            $query->where('kode_riasec_relevan', 'like', '%' . $request->query('kode_riasec') . '%');
        }

        if ($request->filled('kelompok_rumpun')) {
            $query->where('deskripsi', 'like', '%' . $request->query('kelompok_rumpun') . '%');
        }

        $klaster = $query->paginate(10);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar program studi dan klaster prodi berhasil diambil.',
            'data' => $klaster,
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama_klaster' => ['nullable', 'string', 'max:255'],
            'nama_prodi' => ['nullable', 'string', 'max:255'],
            'kode_riasec_relevan' => ['nullable', 'string', 'max:10'],
            'kecerdasan_relevan' => ['nullable', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'kelompok_rumpun' => ['nullable', 'string', 'max:255'],
        ]);

        $namaKlaster = $validated['nama_klaster'] ?? $validated['nama_prodi'] ?? null;

        if (! $namaKlaster) {
            return response()->json([
                'status' => 'error',
                'message' => 'Nama klaster atau prodi wajib diisi.',
            ], 422);
        }

        $deskripsi = $validated['deskripsi'] ?? $validated['kelompok_rumpun'] ?? null;

        $klaster = KlasterProdi::create([
            'nama_klaster' => $namaKlaster,
            'kode_riasec_relevan' => $validated['kode_riasec_relevan'] ?? null,
            'kecerdasan_relevan' => $validated['kecerdasan_relevan'] ?? null,
            'deskripsi' => $deskripsi,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Data program studi / klaster prodi berhasil ditambahkan.',
            'data' => $klaster,
        ], 201);
    }

    public function show(string|int $id): JsonResponse
    {
        $klaster = KlasterProdi::with(['mataPelajaran', 'bobotMapelKlaster'])->find($id);

        if (! $klaster) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data program studi / klaster prodi tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data detail program studi / klaster prodi berhasil diambil.',
            'data' => $klaster,
        ], 200);
    }

    public function update(Request $request, string|int $id): JsonResponse
    {
        $klaster = KlasterProdi::find($id);

        if (! $klaster) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data program studi / klaster prodi tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'nama_klaster' => ['sometimes', 'string', 'max:255'],
            'nama_prodi' => ['sometimes', 'string', 'max:255'],
            'kode_riasec_relevan' => ['nullable', 'string', 'max:10'],
            'kecerdasan_relevan' => ['nullable', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'kelompok_rumpun' => ['nullable', 'string', 'max:255'],
        ]);

        if (isset($validated['nama_klaster'])) {
            $klaster->nama_klaster = $validated['nama_klaster'];
        } elseif (isset($validated['nama_prodi'])) {
            $klaster->nama_klaster = $validated['nama_prodi'];
        }

        if (array_key_exists('kode_riasec_relevan', $validated)) {
            $klaster->kode_riasec_relevan = $validated['kode_riasec_relevan'];
        }

        if (array_key_exists('kecerdasan_relevan', $validated)) {
            $klaster->kecerdasan_relevan = $validated['kecerdasan_relevan'];
        }

        if (array_key_exists('deskripsi', $validated)) {
            $klaster->deskripsi = $validated['deskripsi'];
        } elseif (array_key_exists('kelompok_rumpun', $validated)) {
            $klaster->deskripsi = $validated['kelompok_rumpun'];
        }

        $klaster->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Data program studi / klaster prodi berhasil diperbarui.',
            'data' => $klaster,
        ], 200);
    }

    public function destroy(string|int $id): JsonResponse
    {
        $klaster = KlasterProdi::find($id);

        if (! $klaster) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data program studi / klaster prodi tidak ditemukan.',
            ], 404);
        }

        $klaster->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Data program studi berhasil dihapus.',
        ], 200);
    }
}
