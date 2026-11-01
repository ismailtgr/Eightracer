<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BobotMapelKlaster;
use App\Models\KlasterProdi;
use App\Models\MataPelajaran;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BobotAdminController extends Controller
{
    private const DEFAULT_BOBOT_ELIGIBILITAS = [
        'bobot_rapor' => 40.0,
        'bobot_tka' => 20.0,
        'bobot_prestasi' => 15.0,
        'bobot_presensi' => 10.0,
        'bobot_karakter' => 10.0,
        'bobot_organisasi' => 5.0,
    ];

    public function getBobot(Request $request): JsonResponse
    {
        $bobotEligibilitas = Cache::get('konfigurasi_bobot_eligibilitas', self::DEFAULT_BOBOT_ELIGIBILITAS);

        $bobotMapelKlaster = KlasterProdi::with(['mataPelajaran'])->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Konfigurasi bobot berhasil diambil.',
            'data' => [
                'bobot_eligibilitas' => $bobotEligibilitas,
                'bobot_mapel_klaster' => $bobotMapelKlaster,
            ],
        ], 200);
    }

    public function updateBobot(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bobot_rapor' => ['nullable', 'numeric', 'between:0,100'],
            'bobot_tka' => ['nullable', 'numeric', 'between:0,100'],
            'bobot_prestasi' => ['nullable', 'numeric', 'between:0,100'],
            'bobot_presensi' => ['nullable', 'numeric', 'between:0,100'],
            'bobot_karakter' => ['nullable', 'numeric', 'between:0,100'],
            'bobot_organisasi' => ['nullable', 'numeric', 'between:0,100'],
            'bobot_mapel' => ['nullable', 'array'],
            'bobot_mapel.*.klaster_id' => ['required_with:bobot_mapel', 'exists:klaster_prodi,id'],
            'bobot_mapel.*.mata_pelajaran_id' => ['required_with:bobot_mapel', 'exists:mata_pelajaran,id'],
            'bobot_mapel.*.bobot_persentase' => ['required_with:bobot_mapel', 'numeric', 'between:0,100'],
        ]);

        $hasEligibilitasInput = isset($validated['bobot_rapor'])
            || isset($validated['bobot_tka'])
            || isset($validated['bobot_prestasi'])
            || isset($validated['bobot_presensi'])
            || isset($validated['bobot_karakter'])
            || isset($validated['bobot_organisasi']);

        if ($hasEligibilitasInput) {
            $current = Cache::get('konfigurasi_bobot_eligibilitas', self::DEFAULT_BOBOT_ELIGIBILITAS);

            $bobotRapor = (float) ($validated['bobot_rapor'] ?? $current['bobot_rapor']);
            $bobotTka = (float) ($validated['bobot_tka'] ?? $current['bobot_tka']);
            $bobotPrestasi = (float) ($validated['bobot_prestasi'] ?? $current['bobot_prestasi']);
            $bobotPresensi = (float) ($validated['bobot_presensi'] ?? $current['bobot_presensi']);
            $bobotKarakter = (float) ($validated['bobot_karakter'] ?? $current['bobot_karakter']);
            $bobotOrganisasi = (float) ($validated['bobot_organisasi'] ?? $current['bobot_organisasi']);

            $total = $bobotRapor + $bobotTka + $bobotPrestasi + $bobotPresensi + $bobotKarakter + $bobotOrganisasi;

            if (abs($total - 100.0) > 0.01 && abs($total - 1.0) > 0.0001) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Total penjumlahan bobot 6 komponen eligibilitas harus 100% (saat ini: ' . $total . '%).',
                ], 422);
            }

            $bobotEligibilitas = [
                'bobot_rapor' => $bobotRapor,
                'bobot_tka' => $bobotTka,
                'bobot_prestasi' => $bobotPrestasi,
                'bobot_presensi' => $bobotPresensi,
                'bobot_karakter' => $bobotKarakter,
                'bobot_organisasi' => $bobotOrganisasi,
            ];

            Cache::forever('konfigurasi_bobot_eligibilitas', $bobotEligibilitas);
        } else {
            $bobotEligibilitas = Cache::get('konfigurasi_bobot_eligibilitas', self::DEFAULT_BOBOT_ELIGIBILITAS);
        }

        if (! empty($validated['bobot_mapel'])) {
            DB::transaction(function () use ($validated) {
                foreach ($validated['bobot_mapel'] as $item) {
                    BobotMapelKlaster::updateOrCreate(
                        [
                            'klaster_id' => $item['klaster_id'],
                            'mata_pelajaran_id' => $item['mata_pelajaran_id'],
                        ],
                        [
                            'bobot_persentase' => $item['bobot_persentase'],
                        ]
                    );
                }
            });
        }

        $bobotMapelKlaster = KlasterProdi::with(['mataPelajaran'])->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Konfigurasi bobot berhasil diperbarui.',
            'data' => [
                'bobot_eligibilitas' => $bobotEligibilitas,
                'bobot_mapel_klaster' => $bobotMapelKlaster,
            ],
        ], 200);
    }
}
