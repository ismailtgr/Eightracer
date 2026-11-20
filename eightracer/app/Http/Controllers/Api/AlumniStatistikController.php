<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AlumniStatistikController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'jalur' => 'nullable|string|max:100',
            'tahun' => 'nullable|integer|min:2000|max:2100',
        ]);

        $baseQuery = DB::table('study_tracer')
            ->join('perguruan_tinggi', 'study_tracer.perguruan_tinggi_id', '=', 'perguruan_tinggi.id')
            ->join('siswa', 'study_tracer.siswa_id', '=', 'siswa.id')
            ->where('study_tracer.status_penerimaan', 'diterima');

        if (! empty($validated['jalur'])) {
            $baseQuery->where('study_tracer.jalur_masuk', $validated['jalur']);
        }

        if (! empty($validated['tahun'])) {
            $baseQuery->where('siswa.tahun_lulus', (string) $validated['tahun']);
        }

        $totalNasional = (clone $baseQuery)->count();

        $rows = (clone $baseQuery)
            ->select(
                'perguruan_tinggi.provinsi',
                'perguruan_tinggi.id as pt_id',
                'perguruan_tinggi.nama_pt',
                'perguruan_tinggi.nama_singkat',
                'study_tracer.program_studi',
                DB::raw('COUNT(*) as total')
            )
            ->groupBy(
                'perguruan_tinggi.provinsi',
                'perguruan_tinggi.id',
                'perguruan_tinggi.nama_pt',
                'perguruan_tinggi.nama_singkat',
                'study_tracer.program_studi'
            )
            ->get();

        $provinsiNullCount = (clone $baseQuery)
            ->whereNull('perguruan_tinggi.provinsi')
            ->count();

        $provinsiMap = [];

        foreach ($rows as $row) {
            $prov = $row->provinsi;
            if ($prov === null) {
                continue;
            }

            if (! isset($provinsiMap[$prov])) {
                $provinsiMap[$prov] = [
                    'provinsi'     => $prov,
                    'total_alumni' => 0,
                    'kampus_map'   => [],
                ];
            }

            $provinsiMap[$prov]['total_alumni'] += $row->total;

            $ptId = $row->pt_id;
            if (! isset($provinsiMap[$prov]['kampus_map'][$ptId])) {
                $provinsiMap[$prov]['kampus_map'][$ptId] = [
                    'perguruan_tinggi_id' => $ptId,
                    'nama_pt'             => $row->nama_pt,
                    'nama_singkat'        => $row->nama_singkat,
                    'total_alumni'        => 0,
                    'prodi_counts'        => [],
                ];
            }

            $provinsiMap[$prov]['kampus_map'][$ptId]['total_alumni'] += $row->total;

            $prodi = $row->program_studi;
            if (! isset($provinsiMap[$prov]['kampus_map'][$ptId]['prodi_counts'][$prodi])) {
                $provinsiMap[$prov]['kampus_map'][$ptId]['prodi_counts'][$prodi] = 0;
            }
            $provinsiMap[$prov]['kampus_map'][$ptId]['prodi_counts'][$prodi] += $row->total;
        }

        $perProvinsi = [];
        foreach ($provinsiMap as $prov => $data) {
            $kampus = [];
            foreach ($data['kampus_map'] as $pt) {
                $prodiFavorit = null;
                $max = 0;
                foreach ($pt['prodi_counts'] as $prodi => $count) {
                    if ($count > $max) {
                        $max = $count;
                        $prodiFavorit = $prodi;
                    }
                }

                $kampus[] = [
                    'perguruan_tinggi_id' => $pt['perguruan_tinggi_id'],
                    'nama_pt'             => $pt['nama_pt'],
                    'nama_singkat'        => $pt['nama_singkat'],
                    'total_alumni'        => $pt['total_alumni'],
                    'prodi_favorit'       => $prodiFavorit,
                ];
            }

            usort($kampus, fn($a, $b) => $b['total_alumni'] <=> $a['total_alumni']);

            $persentase = $totalNasional > 0
                ? round(($data['total_alumni'] / $totalNasional) * 100, 2)
                : 0;

            $perProvinsi[] = [
                'provinsi'     => $prov,
                'total_alumni' => $data['total_alumni'],
                'persentase'   => $persentase,
                'kampus'       => $kampus,
            ];
        }

        usort($perProvinsi, fn($a, $b) => $b['total_alumni'] <=> $a['total_alumni']);

        return response()->json([
            'total_alumni_nasional' => $totalNasional,
            'provinsi_null_count'   => $provinsiNullCount,
            'per_provinsi'          => $perProvinsi,
            'filter'                => [
                'jalur' => $validated['jalur'] ?? null,
                'tahun' => $validated['tahun'] ?? null,
            ],
        ]);
    }
}