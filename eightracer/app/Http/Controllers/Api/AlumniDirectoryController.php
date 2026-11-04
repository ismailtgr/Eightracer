<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PerguruanTinggi;
use App\Models\StudyTracer;
use Illuminate\Http\Request;

class AlumniDirectoryController extends Controller
{
    private function base()
    {
        return StudyTracer::query()->where('status_penerimaan', 'diterima');
    }

    private function row(StudyTracer $record): array
    {
        $latest = $record->alumniProgress->first();

        return [
            'id' => $record->id, 'siswa_id' => $record->siswa_id,
            'nama_lengkap' => $record->siswa?->nama_lengkap,
            'tahun_lulus' => $record->siswa?->tahun_lulus,
            'perguruan_tinggi' => $record->perguruanTinggi?->only(['id', 'nama_pt', 'jenis_pt']),
            'program_studi' => $record->program_studi, 'jalur_masuk' => $record->jalur_masuk,
            'status_penerimaan' => $record->status_penerimaan,
            'status_terakhir' => $latest?->status_pendidikan,
        ];
    }

    private function relations(): array
    {
        return ['siswa', 'perguruanTinggi', 'alumniProgress' => fn ($q) => $q
            ->where('is_current', true)->orderByDesc('tanggal_update')->orderByDesc('id')];
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'search' => 'nullable|string|max:100',
            'perguruan_tinggi_id' => 'nullable|integer|min:1',
            'program_studi' => 'nullable|string|max:255',
            'jalur_masuk' => 'nullable|string|max:50',
            'tahun_lulus' => 'nullable|digits:4',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|between:1,100',
            'include_filters' => 'sometimes|boolean',
        ]);
        $query = $this->base()->with($this->relations());
        if ($request->filled('search')) {
            $query->whereHas('siswa', fn ($q) => $q->where('nama_lengkap', 'like', '%'.$data['search'].'%'));
        }
        foreach (['perguruan_tinggi_id', 'program_studi', 'jalur_masuk'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $data[$field]);
            }
        }
        if ($request->filled('tahun_lulus')) {
            $query->whereHas('siswa', fn ($q) => $q->where('tahun_lulus', $data['tahun_lulus']));
        }
        $page = $query->orderByDesc('id')->paginate($request->integer('per_page', 8))
            ->through(fn ($record) => $this->row($record));
        $result = $page->toArray();
        if ($request->boolean('include_filters')) {
            $result['filters'] = [
                'universities' => PerguruanTinggi::whereIn('id', $this->base()->select('perguruan_tinggi_id'))
                    ->orderBy('nama_pt')->get(['id', 'nama_pt']),
                'study_programs' => $this->base()->whereNotNull('program_studi')->where('program_studi', '<>', '')
                    ->distinct()->orderBy('program_studi')->pluck('program_studi'),
                'admission_paths' => $this->base()->whereNotNull('jalur_masuk')->where('jalur_masuk', '<>', '')
                    ->distinct()->orderBy('jalur_masuk')->pluck('jalur_masuk'),
            ];
        }

        return response()->json($result);
    }

    public function show(int $id)
    {
        $record = $this->base()->with([...$this->relations(), 'siswa.riwayatKelas.kelas', 'alumniProgress.alumniProgressBeasiswa.beasiswa'])->findOrFail($id);
        $result = [...$this->row($record),
            'has_nisn' => filled($record->siswa?->nisn),
            'kelas_terakhir' => $record->siswa?->riwayatKelas->sortByDesc('tanggal_mulai')->first()?->kelas?->nama_kelas,
            'strata_pendidikan' => $record->strata_pendidikan,
            'sub_jalur_mandiri' => $record->sub_jalur_mandiri,
            'pilihan_ke' => $record->pilihan_ke, 'tahun_masuk_pt' => $record->tahun_masuk_pt,
            'nilai_rapor_pendaftaran' => $record->nilai_rapor_pendaftaran, 'skor_utbk' => $record->skor_utbk,
            'progress' => $record->alumniProgress->map(fn ($p) => [
                'id' => $p->id, 'semester' => $p->semester, 'periode' => $p->periode,
                'status_pendidikan' => $p->status_pendidikan, 'ipk_terkini' => $p->ipk_terkini,
                'penerima_kipk' => $p->penerima_kipk, 'nominal_ukt' => $p->nominal_ukt, 'nominal_ipi' => $p->nominal_ipi,
                'beasiswa' => $p->alumniProgressBeasiswa->map(fn ($b) => [
                    'nama_beasiswa' => $b->beasiswa_snapshot['nama_beasiswa'] ?? $b->beasiswa?->nama_beasiswa,
                    'status' => $b->status_beasiswa,
                ])->values(),
            ])->values(),
        ];

        return response()->json(['data' => $result]);
    }
}
