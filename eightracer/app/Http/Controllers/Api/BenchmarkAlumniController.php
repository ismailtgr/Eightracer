<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HasilEligibilitas;
use App\Models\NilaiRapor;
use App\Models\Siswa;
use App\Models\StudyTracer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BenchmarkAlumniController extends Controller
{
    public function compareWithAlumni(Request $request): JsonResponse
    {
        $user = $request->user();

        // Data ownership: selalu dari akun yang login, abaikan siswa_id dari client.
        $siswa = Siswa::with(['nilaiRapor.mataPelajaran'])->where('user_id', $user->id)->first();

        if (! $siswa) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data siswa tidak ditemukan.',
            ], 404);
        }

        $namaProdi = $request->query('nama_prodi');
        $namaPt = $request->query('ptn');

        $queryAlumni = StudyTracer::with(['perguruanTinggi', 'bukuAlumni', 'siswa.nilaiRapor.mataPelajaran'])
            ->where('status_penerimaan', 'diterima');

        if ($namaProdi) {
            $queryAlumni->where('program_studi', 'like', '%' . $namaProdi . '%');
        }

        if ($namaPt) {
            $queryAlumni->whereHas('perguruanTinggi', function ($q) use ($namaPt) {
                $q->where('nama_pt', 'like', '%' . $namaPt . '%');
            });
        }

        $alumniList = $queryAlumni->get();

        $siswaRaporList = $siswa->nilaiRapor;
        $siswaAvgRapor = $siswaRaporList->isNotEmpty()
            ? round((float) $siswaRaporList->avg('nilai_akhir'), 2)
            : 0.0;

        $siswaMapelAvg = [];
        $groupedByMapel = $siswaRaporList->groupBy('mata_pelajaran_id');
        foreach ($groupedByMapel as $mapelId => $scores) {
            $mapelName = $scores->first()->mataPelajaran->nama_mapel ?? ('Mapel #' . $mapelId);
            $siswaMapelAvg[$mapelId] = [
                'nama_mapel' => $mapelName,
                'avg_nilai' => round((float) $scores->avg('nilai_akhir'), 2),
            ];
        }

        $hasilEligibilitas = HasilEligibilitas::where('siswa_id', $siswa->id)
            ->orderByDesc('id')
            ->first();

        $alumniScores = [];
        $alumniMapelAggregator = [];

        foreach ($alumniList as $alumni) {
            $nilaiAlumni = null;

            if ($alumni->nilai_rapor_pendaftaran) {
                $nilaiAlumni = (float) $alumni->nilai_rapor_pendaftaran;
            } elseif ($alumni->siswa && $alumni->siswa->nilaiRapor->isNotEmpty()) {
                $nilaiAlumni = (float) $alumni->siswa->nilaiRapor->avg('nilai_akhir');

                foreach ($alumni->siswa->nilaiRapor as $nRapor) {
                    $mId = $nRapor->mata_pelajaran_id;
                    if (! isset($alumniMapelAggregator[$mId])) {
                        $alumniMapelAggregator[$mId] = [
                            'nama_mapel' => $nRapor->mataPelajaran->nama_mapel ?? ('Mapel #' . $mId),
                            'scores' => [],
                        ];
                    }
                    $alumniMapelAggregator[$mId]['scores'][] = (float) $nRapor->nilai_akhir;
                }
            }

            if ($nilaiAlumni !== null) {
                $alumniScores[] = $nilaiAlumni;
            }
        }

        $avgAlumniRapor = ! empty($alumniScores)
            ? round(array_sum($alumniScores) / count($alumniScores), 2)
            : null;

        $minAlumniRapor = ! empty($alumniScores) ? round(min($alumniScores), 2) : null;
        $maxAlumniRapor = ! empty($alumniScores) ? round(max($alumniScores), 2) : null;

        $gapNilaiRata = $avgAlumniRapor !== null
            ? round($siswaAvgRapor - $avgAlumniRapor, 2)
            : null;

        $peluang = 'Belum Cukup Data Alumni';
        $peluangPersentase = 50.0;

        if ($avgAlumniRapor !== null) {
            if ($siswaAvgRapor >= $avgAlumniRapor + 2) {
                $peluang = 'Tinggi';
                $peluangPersentase = 85.0;
            } elseif ($siswaAvgRapor >= $avgAlumniRapor - 2) {
                $peluang = 'Sedang';
                $peluangPersentase = 65.0;
            } else {
                $peluang = 'Rendah';
                $peluangPersentase = 40.0;
            }
        }

        $gapPerMapel = [];
        foreach ($siswaMapelAvg as $mapelId => $dataMapel) {
            $avgMapelAlumni = null;
            if (isset($alumniMapelAggregator[$mapelId]) && ! empty($alumniMapelAggregator[$mapelId]['scores'])) {
                $avgMapelAlumni = round(array_sum($alumniMapelAggregator[$mapelId]['scores']) / count($alumniMapelAggregator[$mapelId]['scores']), 2);
            }

            $gapPerMapel[] = [
                'mata_pelajaran_id' => $mapelId,
                'nama_mapel' => $dataMapel['nama_mapel'],
                'nilai_siswa' => $dataMapel['avg_nilai'],
                'rata_rata_alumni' => $avgMapelAlumni,
                'gap' => $avgMapelAlumni !== null ? round($dataMapel['avg_nilai'] - $avgMapelAlumni, 2) : null,
            ];
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Analisis perbandingan nilai dengan alumni berhasil dihitung.',
            'data' => [
                'target_pencarian' => [
                    'prodi' => $namaProdi ?? 'Semua Prodi',
                    'ptn' => $namaPt ?? 'Semua PTN',
                ],
                'siswa' => [
                    'id' => $siswa->id,
                    'nama_lengkap' => $siswa->nama_lengkap,
                    'nisn' => $siswa->nisn,
                    'rata_rata_rapor' => $siswaAvgRapor,
                    'skor_eligibilitas' => $hasilEligibilitas ? (float) $hasilEligibilitas->total_skor_akhir : null,
                    'peringkat_eligibilitas' => $hasilEligibilitas ? $hasilEligibilitas->peringkat : null,
                    'status_eligible' => $hasilEligibilitas ? $hasilEligibilitas->status_eligible : null,
                ],
                'benchmark_alumni' => [
                    'total_alumni_pembanding' => count($alumniScores),
                    'rata_rata_nilai_alumni' => $avgAlumniRapor,
                    'nilai_terendah_alumni' => $minAlumniRapor,
                    'nilai_tertinggi_alumni' => $maxAlumniRapor,
                ],
                'gap_analisis' => [
                    'selisih_rata_rata' => $gapNilaiRata,
                    'estimasi_peluang' => $peluang,
                    'estimasi_peluang_persen' => $peluangPersentase,
                    'rincian_per_mapel' => $gapPerMapel,
                ],
            ],
        ], 200);
    }
}
