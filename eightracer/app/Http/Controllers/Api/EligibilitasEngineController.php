<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DetailSkorEligibilitas;
use App\Models\HasilEligibilitas;
use App\Models\PeriodeEligibilitas;
use App\Models\Siswa;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EligibilitasEngineController extends Controller
{
    private const DEFAULT_BOBOT = [
        'bobot_rapor' => 40.0,
        'bobot_tka' => 20.0,
        'bobot_prestasi' => 15.0,
        'bobot_presensi' => 10.0,
        'bobot_karakter' => 10.0,
        'bobot_organisasi' => 5.0,
    ];

    public function kalkulasiOtomatis(Request $request): JsonResponse
    {
        $periodeId = $request->input('periode_id');

        if ($periodeId) {
            $periode = PeriodeEligibilitas::find($periodeId);
        } else {
            $periode = PeriodeEligibilitas::where('is_active', true)->first();
            if (! $periode) {
                $periode = PeriodeEligibilitas::firstOrCreate(
                    ['tahun_ajaran' => '2025/2026'],
                    ['kuota_siswa' => 50, 'is_active' => true]
                );
            }
        }

        if (! $periode) {
            return response()->json([
                'status' => 'error',
                'message' => 'Periode eligibilitas tidak ditemukan.',
            ], 404);
        }

        $bobot = Cache::get('konfigurasi_bobot_eligibilitas', self::DEFAULT_BOBOT);

        $wRapor = ($bobot['bobot_rapor'] ?? 40.0) / 100;
        $wTka = ($bobot['bobot_tka'] ?? 20.0) / 100;
        $wPrestasi = ($bobot['bobot_prestasi'] ?? 15.0) / 100;
        $wPresensi = ($bobot['bobot_presensi'] ?? 10.0) / 100;
        $wKarakter = ($bobot['bobot_karakter'] ?? 10.0) / 100;
        $wOrganisasi = ($bobot['bobot_organisasi'] ?? 5.0) / 100;

        $siswaList = Siswa::with([
            'nilaiRapor',
            'prestasiMurid',
            'rekapPresensi',
            'catatanKarakter',
            'riwayatOrganisasi',
        ])->get();

        if ($siswaList->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tidak ada data siswa untuk dikalkulasi.',
            ], 404);
        }

        $kalkulasiData = [];

        foreach ($siswaList as $siswa) {
            $skorRawRapor = $siswa->nilaiRapor->isNotEmpty()
                ? (float) $siswa->nilaiRapor->avg('nilai_akhir')
                : 0.0;

            $skorRawTka = (float) ($siswa->nilai_tka ?? 0.0);

            $skorRawPrestasi = 0.0;
            if ($siswa->prestasiMurid->isNotEmpty()) {
                $bobotTingkat = [
                    'internasional' => 100.0,
                    'nasional' => 90.0,
                    'provinsi' => 80.0,
                    'kabupaten' => 70.0,
                    'kota' => 70.0,
                    'sekolah' => 60.0,
                ];

                $totalSkorPrestasi = 0.0;
                foreach ($siswa->prestasiMurid as $prestasi) {
                    $tingkat = strtolower(trim((string) $prestasi->tingkat));
                    $baseSkor = $bobotTingkat[$tingkat] ?? 60.0;
                    if ($prestasi->bobot_persentase) {
                        $baseSkor = (float) $prestasi->bobot_persentase;
                    }
                    $totalSkorPrestasi += $baseSkor;
                }
                $skorRawPrestasi = min(100.0, $totalSkorPrestasi / $siswa->prestasiMurid->count());
            }

            $skorRawPresensi = 0.0;
            if ($siswa->rekapPresensi->isNotEmpty()) {
                $totalKehadiran = 0.0;
                $count = 0;
                foreach ($siswa->rekapPresensi as $presensi) {
                    if ($presensi->persentase_kehadiran !== null) {
                        $totalKehadiran += (float) $presensi->persentase_kehadiran;
                        $count++;
                    } else {
                        $hadir = (int) $presensi->jumlah_hadir;
                        $totalHari = $hadir + (int) $presensi->jumlah_sakit + (int) $presensi->jumlah_izin + (int) $presensi->jumlah_alpa;
                        if ($totalHari > 0) {
                            $totalKehadiran += ($hadir / $totalHari) * 100;
                            $count++;
                        }
                    }
                }
                $skorRawPresensi = $count > 0 ? ($totalKehadiran / $count) : 0.0;
            }

            $skorRawKarakter = 85.0;
            if ($siswa->catatanKarakter->isNotEmpty()) {
                $poinPengurang = (int) $siswa->catatanKarakter->sum('poin_disiplin');
                $skorRawKarakter = max(0.0, min(100.0, 100.0 - $poinPengurang));
            }

            $skorRawOrganisasi = 0.0;
            if ($siswa->riwayatOrganisasi->isNotEmpty()) {
                $totalSkorOrg = 0.0;
                foreach ($siswa->riwayatOrganisasi as $org) {
                    $jabatan = strtolower(trim((string) $org->jabatan));
                    if (str_contains($jabatan, 'ketua')) {
                        $totalSkorOrg += 95.0;
                    } elseif (str_contains($jabatan, 'wakil')) {
                        $totalSkorOrg += 90.0;
                    } elseif (str_contains($jabatan, 'sekretaris') || str_contains($jabatan, 'bendahara')) {
                        $totalSkorOrg += 85.0;
                    } else {
                        $totalSkorOrg += (float) ($org->bobot_jabatan ?? 75.0);
                    }
                }
                $skorRawOrganisasi = min(100.0, $totalSkorOrg / $siswa->riwayatOrganisasi->count());
            }

            $skorNormRapor = $skorRawRapor;
            $skorNormTka = $skorRawTka;
            $skorNormPrestasi = $skorRawPrestasi;
            $skorNormPresensi = $skorRawPresensi;
            $skorNormKarakter = $skorRawKarakter;
            $skorNormOrganisasi = $skorRawOrganisasi;

            $totalSkorAkhir = (
                ($skorNormRapor * $wRapor) +
                ($skorNormTka * $wTka) +
                ($skorNormPrestasi * $wPrestasi) +
                ($skorNormPresensi * $wPresensi) +
                ($skorNormKarakter * $wKarakter) +
                ($skorNormOrganisasi * $wOrganisasi)
            );

            $kalkulasiData[] = [
                'siswa' => $siswa,
                'skor_raw_rapor' => round($skorRawRapor, 4),
                'skor_norm_rapor' => round($skorNormRapor, 4),
                'skor_raw_tka' => round($skorRawTka, 4),
                'skor_norm_tka' => round($skorNormTka, 4),
                'skor_raw_prestasi' => round($skorRawPrestasi, 4),
                'skor_norm_prestasi' => round($skorNormPrestasi, 4),
                'skor_raw_presensi' => round($skorRawPresensi, 4),
                'skor_norm_presensi' => round($skorNormPresensi, 4),
                'skor_raw_karakter' => round($skorRawKarakter, 4),
                'skor_norm_karakter' => round($skorNormKarakter, 4),
                'skor_raw_organisasi' => round($skorRawOrganisasi, 4),
                'skor_norm_organisasi' => round($skorNormOrganisasi, 4),
                'total_skor_akhir' => round($totalSkorAkhir, 4),
            ];
        }

        usort($kalkulasiData, function ($a, $b) {
            return $b['total_skor_akhir'] <=> $a['total_skor_akhir'];
        });

        $kuota = (int) $periode->kuota_siswa;
        $now = Carbon::now();
        $hasilResponse = [];

        DB::transaction(function () use ($kalkulasiData, $periode, $kuota, $now, &$hasilResponse) {
            $rank = 1;
            foreach ($kalkulasiData as $item) {
                $statusEligible = $rank <= $kuota ? 'Eligible' : 'Non-Eligible';

                $hasil = HasilEligibilitas::updateOrCreate(
                    [
                        'siswa_id' => $item['siswa']->id,
                        'periode_id' => $periode->id,
                    ],
                    [
                        'total_skor_akhir' => $item['total_skor_akhir'],
                        'peringkat' => $rank,
                        'status_eligible' => $statusEligible,
                        'tanggal_kalkulasi' => $now,
                    ]
                );

                DetailSkorEligibilitas::updateOrCreate(
                    [
                        'hasil_id' => $hasil->id,
                    ],
                    [
                        'skor_raw_rapor' => $item['skor_raw_rapor'],
                        'skor_norm_rapor' => $item['skor_norm_rapor'],
                        'skor_raw_tka' => $item['skor_raw_tka'],
                        'skor_norm_tka' => $item['skor_norm_tka'],
                        'skor_raw_prestasi' => $item['skor_raw_prestasi'],
                        'skor_norm_prestasi' => $item['skor_norm_prestasi'],
                        'skor_raw_presensi' => $item['skor_raw_presensi'],
                        'skor_norm_presensi' => $item['skor_norm_presensi'],
                        'skor_raw_karakter' => $item['skor_raw_karakter'],
                        'skor_norm_karakter' => $item['skor_norm_karakter'],
                        'skor_raw_organisasi' => $item['skor_raw_organisasi'],
                        'skor_norm_organisasi' => $item['skor_norm_organisasi'],
                    ]
                );

                $hasilResponse[] = [
                    'peringkat' => $rank,
                    'siswa' => [
                        'id' => $item['siswa']->id,
                        'nama_lengkap' => $item['siswa']->nama_lengkap,
                        'nisn' => $item['siswa']->nisn,
                    ],
                    'total_skor_akhir' => $item['total_skor_akhir'],
                    'status_eligible' => $statusEligible,
                    'detail_skor' => [
                        'rapor' => $item['skor_norm_rapor'],
                        'tka' => $item['skor_norm_tka'],
                        'prestasi' => $item['skor_norm_prestasi'],
                        'presensi' => $item['skor_norm_presensi'],
                        'karakter' => $item['skor_norm_karakter'],
                        'organisasi' => $item['skor_norm_organisasi'],
                    ],
                ];

                $rank++;
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Kalkulasi otomatis ranking eligibilitas berhasil dijalankan.',
            'periode' => [
                'id' => $periode->id,
                'tahun_ajaran' => $periode->tahun_ajaran,
                'kuota_siswa' => $periode->kuota_siswa,
            ],
            'total_siswa_dikalkulasi' => count($hasilResponse),
            'data' => $hasilResponse,
        ], 200);
    }
}
