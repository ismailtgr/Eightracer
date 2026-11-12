<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudyTracer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AlumniController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'univ'     => 'nullable|string|max:255',
            'prodi'    => 'nullable|string|max:255',
            'jalur'    => 'nullable|string|max:100',
            'search'   => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:500',
        ]);

        $query = StudyTracer::query()
            ->with(['siswa', 'perguruanTinggi', 'prestasi']);

        if (! empty($validated['univ'])) {
            $univ = trim($validated['univ']);
            $query->whereHas('perguruanTinggi', function ($q) use ($univ) {
                $q->whereLike('nama_pt', "%{$univ}%");
            });
        }

        if (! empty($validated['prodi'])) {
            $query->whereLike('program_studi', '%' . trim($validated['prodi']) . '%');
        }

        if (! empty($validated['jalur'])) {
            $query->where('jalur_masuk', $validated['jalur']);
        }

        if (! empty($validated['search'])) {
            $search = trim($validated['search']);
            $query->whereHas('siswa', function ($q) use ($search) {
                $q->whereLike('nama_lengkap', "%{$search}%");
            });
        }

        $perPage = $validated['per_page'] ?? 10;
        $paginated = $query->orderByDesc('id')->paginate($perPage);

        $data = collect($paginated->items())->map(fn($st) => $this->formatAlumni($st, false));

        return response()->json([
            'data' => $data,
            'meta' => [
                'total'        => $paginated->total(),
                'per_page'     => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
            ],
            'filter_options' => $this->buildFilterOptions(),
        ]);
    }

    public function show($id)
    {
        $st = StudyTracer::with(['siswa', 'perguruanTinggi', 'prestasi'])->find($id);

        if (! $st) {
            return response()->json([
                'message' => 'Data alumni tidak ditemukan',
            ], 404);
        }

        return response()->json($this->formatAlumni($st, true));
    }

    public function filterOptions()
    {
        return response()->json($this->buildFilterOptions());
    }

    private function formatAlumni(StudyTracer $st, bool $detail): array
    {
        $tahunLulus = $st->siswa->tahun_lulus ?? null;

        $sertifikat = $st->prestasi->map(fn($p) => [
            'nama_kompetisi' => $p->nama_kompetisi,
            'tingkat'        => $p->tingkat,
            'peringkat'      => $p->peringkat,
            'tahun'          => $p->tahun,
        ])->values();

        $base = [
            'id'                 => $st->id,
            'nama'               => $st->siswa->nama_lengkap ?? null,
            'tahun_lulus'        => $tahunLulus,
            'univ'               => $st->perguruanTinggi->nama_pt ?? null,
            'univ_singkat'       => $st->perguruanTinggi->nama_singkat ?? null,
            'provinsi'           => $st->perguruanTinggi->provinsi ?? null,
            'prodi'              => $st->program_studi,
            'strata'             => $st->strata_pendidikan,
            'jalur_masuk'        => $st->jalur_masuk,
            'sub_jalur'          => $st->sub_jalur_mandiri,
            'pilihan_ke'         => $st->pilihan_ke,
            'status_penerimaan'  => $st->status_penerimaan,
            'nilai_rapor'        => $st->nilai_rapor_pendaftaran,
            'skor_utbk'          => $st->skor_utbk,
            'is_kipk'            => (bool) $st->is_pelamar_kipk,
            'sertifikat'         => $sertifikat,
        ];

        if ($detail) {
            $base['nisn'] = $st->siswa->nisn ?? null;
        }

        return $base;
    }

    private function buildFilterOptions(): array
    {
        $univs = DB::table('study_tracer')
            ->join('perguruan_tinggi', 'study_tracer.perguruan_tinggi_id', '=', 'perguruan_tinggi.id')
            ->select('perguruan_tinggi.nama_pt')
            ->distinct()
            ->orderBy('perguruan_tinggi.nama_pt')
            ->pluck('nama_pt')
            ->values();

        $prodiMap = [];
        $rows = DB::table('study_tracer')
            ->join('perguruan_tinggi', 'study_tracer.perguruan_tinggi_id', '=', 'perguruan_tinggi.id')
            ->select('perguruan_tinggi.nama_pt', 'study_tracer.program_studi')
            ->distinct()
            ->orderBy('perguruan_tinggi.nama_pt')
            ->orderBy('study_tracer.program_studi')
            ->get();

        foreach ($rows as $row) {
            $prodiMap[$row->nama_pt][] = $row->program_studi;
        }

        $jalur = DB::table('study_tracer')
            ->select('jalur_masuk')
            ->distinct()
            ->orderBy('jalur_masuk')
            ->pluck('jalur_masuk')
            ->values();

        return [
            'univ'  => $univs,
            'prodi' => $prodiMap,
            'jalur' => $jalur,
        ];
    }
}