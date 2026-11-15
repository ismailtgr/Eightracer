<?php
namespace App\Services\Beasiswa;

use App\Models\BasisDataBeasiswa;
use App\Models\RekomendasiBeasiswa;
use App\Models\Siswa;
use App\Services\School\StudentProfile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProgramScholarshipAnalysis
{
    public function __construct(private ScholarshipRecommendationService $recommendations, private GeminiScholarshipExplainer $gemini) {}

    public function candidate(Siswa $student, BasisDataBeasiswa $program): array
    {
        $preview = $this->recommendations->preview($student, $program->jenjang === 'Kuliah' ? 'Kuliah' : 'SMA', $program->id);
        abort_unless(count($preview['kandidat']) === 1, 422, 'Program tidak tersedia untuk analisis saat ini.');
        return $preview;
    }

    public function reason(array $e): string
    {
        return !$e['lengkap'] ? 'Sebagian data belum tersedia. Lengkapi data sebelum menyimpulkan kecocokan.'
            : ($e['memenuhi'] ? 'Data yang tersedia memenuhi persyaratan yang tercatat.' : 'Ada persyaratan yang belum terpenuhi. Periksa rincian evaluasi.');
    }

    private function record(Siswa $student, BasisDataBeasiswa $program)
    {
        return RekomendasiBeasiswa::where('siswa_id', $student->id)->where('prompt_version', 'staff-program-v1')
            ->whereHas('rekomendasiBeasiswaDetail', fn($q) => $q->where('beasiswa_id', $program->id))->latest('id');
    }

    public function note(Siswa $student, BasisDataBeasiswa $program): ?array
    {
        $record = $this->record($student, $program)->with('rekomendasiBeasiswaDetail')->first();
        if (!$record) return null;
        $detail = $record->rekomendasiBeasiswaDetail->firstWhere('beasiswa_id', $program->id);
        return ['revision'=>(int)$record->revision, 'reason'=>$detail->alasan_rekomendasi, 'timing'=>$detail->waktu_disarankan];
    }

    public function batch(BasisDataBeasiswa $program, int $after = 0): array
    {
        $query = app(ActiveScholarshipStudents::class)->restrict(Siswa::query());
        $total = (clone $query)->count();
        $students = $query->where('id', '>', $after)->orderBy('id')->limit(11)->get();
        $more = $students->count() > 10;
        $data = [];
        foreach ($students->take(10) as $student) {
            $student->loadMissing(StudentProfile::RELATIONS);
            $preview = $this->candidate($student, $program);
            $candidate = $preview['kandidat'][0];
            $class = $student->riwayatKelas->filter(fn($r) => $r->tanggal_mulai <= today() && (!$r->tanggal_selesai || $r->tanggal_selesai > today()))->sortByDesc('tanggal_mulai')->first()?->kelas;
            // Only the criterion outcomes and display identity leave the server, never raw financial data.
            $data[] = ['id'=>$student->id, 'name'=>$student->nama_lengkap, 'classroom'=>$class?->nama_kelas,
                'preview'=>['data'=>array_diff_key($preview, ['source_fingerprint'=>true])],
                'fingerprint'=>$preview['source_fingerprint'], 'reason'=>$this->reason($candidate['evaluasi']),
                'note'=>$this->note($student, $program)];
        }
        return ['data'=>$data, 'total'=>$total, 'next_cursor'=>$more ? $students->take(10)->last()->id : null,
            'analysed_at'=>now()->toIso8601String()];
    }

    public function explain(Siswa $student, BasisDataBeasiswa $program): array
    {
        $preview = $this->candidate($student, $program);
        $candidate = $preview['kandidat'][0];
        $fallback = ['reason'=>$this->reason($candidate['evaluasi']), 'preparations'=>['Periksa persyaratan resmi dan lengkapi data yang belum tersedia.'], 'llmUsed'=>false];
        $key = 'staff-program-ai-v1:'.$student->id.':'.$program->id.':'.$preview['source_fingerprint'];
        if (is_array($cached = Cache::get($key))) return $cached;
        $lock = Cache::lock($key.':lock', 25);
        if (!$lock->get()) return $fallback;
        try {
            unset($candidate['evaluasi']['pemeringkatan_ml']);
            try {
                $result = $this->gemini->explain([$candidate], 15)[$program->id];
                $answer = ['reason'=>$result['alasan'], 'preparations'=>$result['persiapan'], 'llmUsed'=>true];
            } catch (\Throwable) { $answer = $fallback; }
            Cache::put($key, $answer, now()->addSeconds($answer['llmUsed'] ? 900 : 60));
            return $answer;
        } finally { $lock->release(); }
    }

    public function saveNote(Siswa $student, BasisDataBeasiswa $program, array $input): array
    {
        $lock=Cache::lock('staff-program-note:'.$student->id.':'.$program->id, 30);
        abort_unless($lock->get(),409,'Rekomendasi berubah. Muat ulang sebelum menyimpan.');
        try {
            $preview=$this->candidate($student,$program);
            abort_unless(hash_equals($preview['source_fingerprint'],$input['fingerprint']),409,'Data sumber berubah. Hitung ulang rekomendasi.');
            return DB::transaction(function() use($student,$program,$input,$preview) {
                $record=$this->record($student,$program)->lockForUpdate()->first();
                abort_unless(($record ? (int)$record->revision : 0)===$input['revision'],409,'Rekomendasi berubah. Muat ulang sebelum menyimpan.');
                $candidate=$preview['kandidat'][0]; $e=$candidate['evaluasi'];
                if (!$record) $record=new RekomendasiBeasiswa(['siswa_id'=>$student->id,'target_jenjang'=>$preview['target_jenjang'],'tanggal_rekomendasi'=>today(),'status_review'=>'pending','prompt_version'=>'staff-program-v1']);
                $record->source_fingerprint=$preview['source_fingerprint'];
                $record->revision=$input['revision']+1; $record->save();
                $record->rekomendasiBeasiswaDetail()->updateOrCreate(['beasiswa_id'=>$program->id], [
                    'skor_kecocokan'=>$e['skor'],'is_recommended'=>$e['memenuhi'],'evaluasi_kriteria'=>$e,'beasiswa_snapshot'=>$candidate['beasiswa'],
                    'alasan_rekomendasi'=>trim($input['reason'] ?? '') ?: $this->reason($e), 'waktu_disarankan'=>$input['timing'] ?? null,'persiapan'=>[],
                ]);
                return $this->note($student,$program);
            });
        } finally { $lock->release(); }
    }
}
