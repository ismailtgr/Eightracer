<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PerguruanTinggi;
use App\Models\BasisDataBeasiswa;
use App\Models\StudyTracer;
use App\Services\School\AlumniAdmissionPath;
use App\Services\School\AlumniTimeline;
use Illuminate\Http\Request;

class AlumniDirectoryController extends Controller
{
    private function base()
    {
        return StudyTracer::query()->where('status_penerimaan', 'diterima');
    }

    private function row(StudyTracer $record): array
    {
        $latest = $record->alumniProgress->first(fn ($p) => (int) $p->siswa_id === (int) $record->siswa_id);

        return [
            'id' => $record->id, 'siswa_id' => $record->siswa_id,
            'nama_lengkap' => $record->siswa?->nama_lengkap,
            'tahun_lulus' => $record->siswa?->tahun_lulus,
            'perguruan_tinggi' => $record->perguruanTinggi?->only(['id', 'nama_pt', 'jenis_pt']),
            'program_studi' => $record->program_studi, 'jalur_masuk' => app(AlumniAdmissionPath::class)->label($record),
            'status_penerimaan' => $record->status_penerimaan,
            'status_terakhir' => $latest?->status_pendidikan,
        ];
    }

    private function relations(): array
    {
        return ['siswa', 'perguruanTinggi', 'alumniProgress' => fn ($q) => $q
            ->where('is_current', true)->orderByRaw('tanggal_update DESC NULLS LAST')->orderByDesc('id')];
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
            'timeline_complete' => 'sometimes|boolean',
        ]);
        $base = $this->base();
        $timelineOnly = $request->boolean('timeline_complete');
        if ($timelineOnly) $base->whereIn('id', app(AlumniTimeline::class)->completeIds());
        $query = (clone $base)->with($this->relations());
        if ($request->filled('search')) {
            $query->whereHas('siswa', fn ($q) => $q->whereLike('nama_lengkap', '%'.$data['search'].'%'));
        }
        $sourcePaths = collect();
        if ($request->filled('jalur_masuk') || $request->boolean('include_filters')) {
            $sourcePaths = (clone $base)->where('jalur_masuk', 'Lainnya')->with(['siswa'])->get()
                ->mapWithKeys(fn ($record) => [$record->id => app(AlumniAdmissionPath::class)->label($record)]);
        }
        if ($request->filled('jalur_masuk')) {
            $query->where(fn ($q) => $q->where('jalur_masuk', $data['jalur_masuk'])
                ->orWhereIn('id', $sourcePaths->filter(fn ($label) => $label === $data['jalur_masuk'])->keys()));
        }
        foreach (['perguruan_tinggi_id', 'program_studi'] as $field) {
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
        if ($timelineOnly) $result['timeline_complete_only'] = true;
        if ($request->boolean('include_filters')) {
            $result['filters'] = [
                'universities' => PerguruanTinggi::whereIn('id', (clone $base)->select('perguruan_tinggi_id'))
                    ->orderBy('nama_pt')->get(['id', 'nama_pt']),
                'study_programs' => (clone $base)->whereNotNull('program_studi')->where('program_studi', '<>', '')
                    ->distinct()->orderBy('program_studi')->pluck('program_studi'),
                'admission_paths' => (clone $base)->whereNotNull('jalur_masuk')->whereNotIn('jalur_masuk', ['', 'Lainnya'])
                    ->distinct()->pluck('jalur_masuk')->merge($sourcePaths->filter()->values())->unique()->sort()->values(),
            ];
        }

        return response()->json($result);
    }

    private function sourceClassroom(StudyTracer $record): ?string
    {
        $notes = json_decode($record->siswa?->catatan ?? '{}', true);
        $value = is_array($notes) ? ($notes['kelas_sumber'] ?? null) : null;
        $classes = is_array($value) ? $value : [$value];
        $classes = array_values(array_unique(array_filter($classes, fn ($value) => is_string($value) && trim($value) !== '')));
        return $classes ? implode(' / ', $classes) : null;
    }

    public function show(Request $request, int $id)
    {
        $request->validate(['timeline_complete' => 'sometimes|boolean']);
        $record = $this->base()->with([...$this->relations(), 'siswa.riwayatKelas.kelas', 'alumniProgress.alumniProgressBeasiswa.beasiswa'])->findOrFail($id);
        $timeline = app(AlumniTimeline::class);
        $complete = $timeline->isComplete($record);
        abort_if($request->boolean('timeline_complete') && !$complete, 404);
        $result = [...$this->row($record),
            'is_published' => (bool) $record->izinkan_tampil_di_buku,
            'timeline_complete' => $complete, 'education_events' => $timeline->educationEvents($record),
            'has_nisn' => filled($record->siswa?->nisn),
            'scholarship_summary' => app(\App\Services\School\AlumniScholarshipEvidence::class)->summarize($record),
            'kelas_terakhir' => $record->siswa?->riwayatKelas->sortByDesc('tanggal_mulai')->first()?->kelas?->nama_kelas
                ?? $this->sourceClassroom($record),
            'strata_pendidikan' => $record->strata_pendidikan,
            'sub_jalur_mandiri' => $record->sub_jalur_mandiri,
            'pilihan_ke' => $record->pilihan_ke, 'tahun_masuk_pt' => $record->tahun_masuk_pt,
            'nilai_rapor_pendaftaran' => $record->nilai_rapor_pendaftaran, 'skor_utbk' => $record->skor_utbk,
            'progress' => $record->alumniProgress->filter(fn ($p) => (int) $p->siswa_id === (int) $record->siswa_id)->map(fn ($p) => [
                'id' => $p->id, 'semester' => $p->semester, 'periode' => $p->periode,
                'revision' => $p->revision, 'tanggal_update' => $p->tanggal_update?->toDateString(),
                'is_published' => $p->is_published, 'publish_financials' => $p->publish_financials,
                'status_pendidikan' => $p->status_pendidikan, 'ipk_terkini' => $p->ipk_terkini,
                'penerima_kipk' => $p->penerima_kipk, 'nominal_ukt' => $p->nominal_ukt, 'nominal_ipi' => $p->nominal_ipi,
                'beasiswa' => $p->alumniProgressBeasiswa->map(fn ($b) => [
                    'beasiswa_id' => $b->beasiswa_id, 'nominal_beasiswa' => $b->nominal_beasiswa,
                    'nama_beasiswa' => $b->beasiswa_snapshot['nama_beasiswa'] ?? $b->beasiswa?->nama_beasiswa,
                    'status' => $b->status_beasiswa,
                ])->values(),
            ])->values(),
        ];

        $result['catalog_options'] = BasisDataBeasiswa::where('is_active', true)->orderBy('nama_beasiswa')->get(['id', 'nama_beasiswa']);

        return response()->json(['data' => $result]);
    }
}
