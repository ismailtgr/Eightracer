<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AlumniProgress;
use App\Models\StudyTracer;
use App\Services\School\AlumniService;
use App\Services\School\AlumniAdmissionPath;
use App\Services\School\AlumniTimeline;
use App\Services\School\SchoolRecordWriter;
use Illuminate\Http\Request;

class StudyTracerController extends Controller
{
    private function query(Request $r, bool $public = false)
    {
        $r->validate(['search' => 'nullable|string|max:100', 'angkatan' => 'nullable|digits:4', 'jenis_pt' => 'nullable|in:Negeri,Swasta,Kedinasan,Luar Negeri', 'program_studi' => 'nullable|string|max:100', 'jalur_masuk' => 'nullable|string|max:50', 'perguruan_tinggi_id' => 'nullable|integer', 'penerima_kipk' => 'nullable|boolean', 'beasiswa_id' => 'nullable|integer|exists:basis_data_beasiswa,id', 'per_page' => 'sometimes|integer|between:1,100']);
        $r->validate(['page' => 'sometimes|integer|min:1', 'include_filters' => 'sometimes|boolean']);
        $q = StudyTracer::with(['siswa', 'perguruanTinggi']);
        if ($public) {
            $q->with('prestasi')->where('izinkan_tampil_di_buku', true)->where('status_penerimaan', 'diterima');
        }
        if ($r->filled('search')) {
            $term = $r->input('search');
            $q->whereHas('siswa', fn ($s) => $s->where('nama_lengkap', 'ilike', '%'.$term.'%'));
        }
        if ($r->filled('angkatan')) {
            $q->whereHas('siswa', fn ($s) => $s->where('tahun_lulus', $r->input('angkatan')));
        }
        if ($r->filled('jenis_pt')) {
            $q->whereHas('perguruanTinggi', fn ($p) => $p->where('jenis_pt', $r->input('jenis_pt')));
        }
        foreach (['program_studi', 'perguruan_tinggi_id'] as $field) {
            if ($r->filled($field)) {
                $q->where($field, $r->input($field));
            }
        }
        if ($r->filled('jalur_masuk')) {
            $fallback = StudyTracer::where('jalur_masuk', 'Lainnya')->when($public, fn ($t) => $t->where('izinkan_tampil_di_buku', true)->where('status_penerimaan', 'diterima'))
                ->with('siswa')->get()->filter(fn ($t) => app(AlumniAdmissionPath::class)->label($t) === $r->input('jalur_masuk'))->pluck('id');
            $q->where(fn ($t) => $t->where('jalur_masuk', $r->input('jalur_masuk'))->orWhereIn('id', $fallback));
        }
        if ($r->has('penerima_kipk') || $r->filled('beasiswa_id')) {
            $q->whereHas('alumniProgress', function ($p) use ($r, $public) {
                $p->where('is_current', true);
                if ($public) {
                    $p->where('is_published', true);
                }if ($r->has('penerima_kipk')) {
                    $p->where('penerima_kipk', $r->boolean('penerima_kipk'));
                }if ($r->filled('beasiswa_id')) {
                    $p->whereHas('alumniProgressBeasiswa', fn ($b) => $b->where('beasiswa_id', $r->input('beasiswa_id'))->where('status_beasiswa', 'diterima'));
                }
            });
        }

        return $q;
    }

    public function index(Request $r)
    {
        return response()->json($this->query($r)->latest('id')->paginate($r->integer('per_page', 20)));
    }

    public function show(int $id)
    {
        return response()->json(['data' => StudyTracer::with(['siswa', 'perguruanTinggi', 'prestasi'])->findOrFail($id)]);
    }

    public function save(Request $r, ?int $id = null)
    {
        return response()->json(['data' => app(SchoolRecordWriter::class)->saveAdmission($r, $id)], $id ? 200 : 201);
    }

    public function destroy(int $id)
    {
        $t = StudyTracer::findOrFail($id);
        abort_if($t->alumniProgress()->exists(), 409, 'Riwayat progres harus dipertahankan.');
        $t->delete();

        return response()->json(['message' => 'Data dihapus.']);
    }

    public function publish(Request $r, int $id)
    {
        $d = $r->validate(['is_published' => 'required|boolean', 'consent_reference' => 'required_if:is_published,true|nullable|string|max:500', 'persetujuan_dikonfirmasi' => 'exclude_unless:is_published,true|required|accepted']);
        $t = StudyTracer::findOrFail($id);
        abort_if($d['is_published'] && $t->status_penerimaan !== 'diterima', 422);
        $t->forceFill(['izinkan_tampil_di_buku' => $d['is_published'], 'consent_reference' => $d['consent_reference'] ?? null, 'consent_at' => $d['is_published'] ? now() : null, 'consent_by' => $d['is_published'] ? $r->user()->id : null])->save();

        return response()->json(['message' => 'Izin publikasi diperbarui.']);
    }

    public function studentIndex(Request $r, AlumniService $service)
    {
        $preview = app(\App\Services\School\LocalStudentPreview::class);
        if ($preview->enabled($r)) {
            $result = $this->query($r)->where('status_penerimaan', 'diterima')->latest('id')
                ->paginate($r->integer('per_page', 20))->through(fn ($t) => $preview->admission($t))->toArray();
            return response()->json($result + ['preview' => true, 'notice' => 'Pratinjau lokal data alumni; izin publikasi belum diubah.']);
        }
        $query = $this->query($r, true)->with(['alumniProgress' => fn ($q) => $q->where('is_current', true)->where('is_published', true)
            ->with('alumniProgressBeasiswa.beasiswa')->orderByDesc('tanggal_update')->orderByDesc('id')]);
        $result = $query->latest('id')->paginate($r->integer('per_page', 20))->through(fn ($t) => [
            ...$service->admission($t), 'latest_progress' => $t->alumniProgress->first() ? $service->progress($t->alumniProgress->first()) : null,
        ])->toArray();
        if ($r->boolean('include_filters')) {
            $visible = StudyTracer::where('izinkan_tampil_di_buku', true)->where('status_penerimaan', 'diterima')->with(['siswa', 'perguruanTinggi'])->get();
            $result['filters'] = [
                'cohorts' => $visible->pluck('siswa.tahun_lulus')->filter()->unique()->sort()->map(fn ($v) => (string) $v)->values(),
                'universities' => $visible->map(fn ($t) => ['value' => (string) $t->perguruan_tinggi_id, 'label' => $t->perguruanTinggi->nama_pt])->unique('value')->sortBy('label')->values(),
                'programs' => $visible->pluck('program_studi')->filter()->unique()->sort()->values(),
                'paths' => $visible->map(fn ($t) => app(AlumniAdmissionPath::class)->label($t))->filter()->unique()->sort()->values(),
                'scholarships' => AlumniProgress::where('is_current', true)->where('is_published', true)->whereIn('study_tracer_id', $visible->pluck('id'))
                    ->with('alumniProgressBeasiswa.beasiswa')->get()->flatMap(fn ($p) => $p->alumniProgressBeasiswa->where('status_beasiswa', 'diterima'))
                    ->map(fn ($a) => ['value' => (string) $a->beasiswa_id, 'label' => $a->beasiswa_snapshot['nama_beasiswa'] ?? $a->beasiswa?->nama_beasiswa])->unique('value')->values(),
            ];
        }
        $result['notice'] = 'Menampilkan alumni yang telah dipublikasikan sekolah; progres dan nominal mengikuti izin publikasi masing-masing.';
        return response()->json($result);
    }

    public function studentShow(Request $request, int $id, AlumniService $service)
    {
        $preview = app(\App\Services\School\LocalStudentPreview::class);
        if ($preview->enabled($request)) {
            $row = StudyTracer::where('status_penerimaan', 'diterima')->with(['siswa', 'perguruanTinggi'])->findOrFail($id);
            if (\App\Services\School\LocalExampleRecords::isAlumniExample($row->siswa)) {
                $progress = $row->alumniProgress()->where('is_current', true)->with('alumniProgressBeasiswa.beasiswa')->orderBy('semester')->get()
                    ->map(fn ($p) => $service->progress($p))->values();
                $education = app(AlumniTimeline::class)->educationEvents($row);
                $timeline = collect($education)->map(fn ($event) => [...$event, 'granularity' => $event['date'] ? 'date' : 'year'])
                    ->concat($progress->map(fn ($p) => ['type' => 'progress_semester', 'date' => $p['tanggal_update'], 'label' => $p['periode'], 'data' => $p]))->values();
                return response()->json(['preview' => true, 'data' => ['masuk_pt' => [...$preview->admission($row),
                    'strata_pendidikan' => $row->strata_pendidikan, 'nilai_rapor_pendaftaran' => $row->nilai_rapor_pendaftaran,
                    'skor_utbk' => $row->skor_utbk], 'progress' => $progress, 'education_events' => $education, 'timeline' => $timeline]]);
            }
            return response()->json(['preview' => true, 'data' => ['masuk_pt' => $preview->admission($row),
                'progress' => [], 'education_events' => [], 'timeline' => []]]);
        }
        $t = StudyTracer::where('izinkan_tampil_di_buku', true)->where('status_penerimaan', 'diterima')->with(['siswa', 'perguruanTinggi', 'prestasi'])->findOrFail($id);

        return response()->json(['data' => ['masuk_pt' => $service->admission($t), 'progress' => $t->alumniProgress()->where('is_current', true)->where('is_published', true)->with('alumniProgressBeasiswa.beasiswa')->orderByDesc('tanggal_update')->orderByDesc('id')->get()->map(fn ($p) => $service->progress($p)), 'education_events' => app(AlumniTimeline::class)->educationEvents($t), 'timeline' => $service->timeline($t)]]);
    }

    public function progress(Request $r, int $id, AlumniService $service)
    {
        $p = AlumniProgress::findOrFail($id);

        return response()->json(['data' => $service->saveProgress($r, $p->siswa, $p)], 201);
    }

    public function publishProgress(Request $r, int $id)
    {
        $d = $r->validate(['is_published' => 'required|boolean', 'publish_financials' => 'required|boolean', 'consent_reference' => 'required_if:is_published,true|nullable|string|max:500', 'persetujuan_dikonfirmasi' => 'exclude_unless:is_published,true|required|accepted']);
        $p = AlumniProgress::findOrFail($id);
        abort_unless($p->is_current, 409);
        abort_if(! $d['is_published'] && $d['publish_financials'], 422);
        $p->forceFill(['is_published' => $d['is_published'], 'publish_financials' => $d['publish_financials'], 'consent_reference' => $d['consent_reference'] ?? null, 'consent_at' => $d['is_published'] ? now() : null, 'consent_by' => $d['is_published'] ? $r->user()->id : null])->save();

        return response()->json(['message' => 'Izin periode diperbarui.']);
    }
}
