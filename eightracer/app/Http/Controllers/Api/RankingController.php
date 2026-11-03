<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HasilEligibilitas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RankingController extends Controller
{
    public function getRankingList(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = ($perPage > 0 && $perPage <= 100) ? $perPage : 10;

        $query = HasilEligibilitas::with(['siswa.riwayatKelas.kelas']);

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->whereHas('siswa', function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', '%' . $search . '%')
                  ->orWhere('nisn', 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('kelas')) {
            $kelas = $request->query('kelas');
            $query->whereHas('siswa.riwayatKelas.kelas', function ($q) use ($kelas) {
                $q->where('nama_kelas', 'like', '%' . $kelas . '%');
            });
        }

        if ($request->filled('jurusan_id')) {
            $jurusanId = $request->query('jurusan_id');
            $query->whereHas('siswa.riwayatKelas.kelas', function ($q) use ($jurusanId) {
                $q->where('id', $jurusanId);
            });
        }

        $ranking = $query->orderBy('peringkat', 'asc')
            ->orderByDesc('total_skor_akhir')
            ->paginate($perPage);

        $ranking->getCollection()->transform(function ($item) {
            $siswa = $item->siswa;
            $kelas = null;

            if ($siswa && $siswa->riwayatKelas->isNotEmpty()) {
                $riwayat = $siswa->riwayatKelas->first();
                $kelas = $riwayat->kelas ? $riwayat->kelas->nama_kelas : null;
            }

            return [
                'rank' => $item->peringkat,
                'skor_akhir' => (float) $item->total_skor_akhir,
                'status_eligible' => $item->status_eligible,
                'siswa' => [
                    'id' => $siswa ? $siswa->id : null,
                    'nama_lengkap' => $siswa ? $siswa->nama_lengkap : null,
                    'nisn' => $siswa ? $siswa->nisn : null,
                    'kelas' => $kelas,
                ],
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar ranking eligibilitas berhasil diambil.',
            'data' => $ranking,
        ], 200);
    }
}
