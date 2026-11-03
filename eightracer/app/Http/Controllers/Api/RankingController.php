<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HasilEligibilitas;
use App\Models\Siswa;
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

    public function getDetailSkorSiswa(Request $request, $siswaId = null): JsonResponse
    {
        if (empty($siswaId)) {
            $user = $request->user();
            $siswa = $user ? $user->siswa : null;

            if (! $siswa && $user) {
                $siswa = Siswa::where('user_id', $user->id)->first();
            }

            if (! $siswa) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Data profil siswa tidak ditemukan untuk akun ini.',
                ], 404);
            }

            $siswaId = $siswa->id;
        } else {
            $siswa = Siswa::find($siswaId);

            if (! $siswa) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Data siswa tidak ditemukan.',
                ], 404);
            }
        }

        $hasil = HasilEligibilitas::with(['periode', 'detailSkorEligibilitas'])
            ->where('siswa_id', $siswaId)
            ->latest('id')
            ->first();

        if (! $hasil) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data skor eligibilitas siswa belum dikalkulasi.',
            ], 404);
        }

        $detail = $hasil->detailSkorEligibilitas;

        $komponenSkor = [
            'rapor' => [
                'raw' => $detail ? (float) $detail->skor_raw_rapor : 0,
                'normalisasi' => $detail ? (float) $detail->skor_norm_rapor : 0,
            ],
            'tka' => [
                'raw' => $detail ? (float) $detail->skor_raw_tka : 0,
                'normalisasi' => $detail ? (float) $detail->skor_norm_tka : 0,
            ],
            'prestasi' => [
                'raw' => $detail ? (float) $detail->skor_raw_prestasi : 0,
                'normalisasi' => $detail ? (float) $detail->skor_norm_prestasi : 0,
            ],
            'presensi' => [
                'raw' => $detail ? (float) $detail->skor_raw_presensi : 0,
                'normalisasi' => $detail ? (float) $detail->skor_norm_presensi : 0,
            ],
            'karakter' => [
                'raw' => $detail ? (float) $detail->skor_raw_karakter : 0,
                'normalisasi' => $detail ? (float) $detail->skor_norm_karakter : 0,
            ],
            'organisasi' => [
                'raw' => $detail ? (float) $detail->skor_raw_organisasi : 0,
                'normalisasi' => $detail ? (float) $detail->skor_norm_organisasi : 0,
            ],
        ];

        return response()->json([
            'status' => 'success',
            'message' => 'Detail skor eligibilitas siswa berhasil diambil.',
            'data' => [
                'siswa' => [
                    'id' => $siswa->id,
                    'nisn' => $siswa->nisn,
                    'nama_lengkap' => $siswa->nama_lengkap,
                ],
                'hasil_eligibilitas' => [
                    'periode' => $hasil->periode ? $hasil->periode->tahun_ajaran : null,
                    'peringkat' => $hasil->peringkat,
                    'total_skor_akhir' => (float) $hasil->total_skor_akhir,
                    'status_eligible' => $hasil->status_eligible,
                    'tanggal_kalkulasi' => $hasil->tanggal_kalkulasi,
                ],
                'rincian_komponen' => $komponenSkor,
            ],
        ], 200);
    }
}
