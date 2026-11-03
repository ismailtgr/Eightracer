<?php

namespace App\Services\School;

use App\Models\AlumniProgress;
use App\Models\AsesmenKarakteristik;
use App\Models\BantuanSiswa;
use App\Models\NilaiRapor;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\StudyTracer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BulkImportModules
{
    public const FIELDS = [
        'students' => ['siswa_id', 'nisn', 'nis', 'nama_lengkap', 'jenis_kelamin', 'registration_email', 'tahun_lulus', 'tanggal_lahir', 'alamat_rumah', 'nilai_tka'],
        'grades' => ['siswa_id', 'nisn', 'semester', 'mata_pelajaran_id', 'nilai_akhir'],
        'assessments' => ['siswa_id', 'nisn', 'tanggal_asesmen', 'tipe_kepribadian', 'gaya_belajar', 'iq', 'realistic_score', 'investigative_score', 'artistic_score', 'social_score', 'enterprising_score', 'conventional_score', 'mi_linguistik', 'mi_logika_matematika', 'mi_visual_spasial', 'mi_kinestetik', 'mi_musikal', 'mi_interpersonal', 'mi_intrapersonal', 'mi_naturalis', 'koreksi_dari_id'],
        'alumni' => ['siswa_id', 'nisn', 'perguruan_tinggi_id', 'program_studi', 'strata_pendidikan', 'jalur_masuk', 'sub_jalur_mandiri', 'pilihan_ke', 'status_penerimaan', 'nilai_rapor_pendaftaran', 'skor_utbk', 'tahun_masuk_pt', 'tanggal_masuk_pt', 'is_pelamar_kipk'],
        'financial' => ['siswa_id', 'nisn', 'study_tracer_id', 'periode', 'semester', 'tahun_lulus', 'status_pendidikan', 'ipk_terkini', 'penerima_kipk', 'nominal_ukt', 'nominal_ipi', 'tanggal_update', 'beasiswa_id', 'status_beasiswa', 'nominal_beasiswa'],
        'aid' => ['siswa_id', 'nisn', 'jenis_bantuan_id', 'nomor_penerima', 'tanggal_mulai', 'tanggal_selesai', 'aktif'],
        'economy' => ['siswa_id', 'nisn', 'ayah_nama', 'ayah_kondisi', 'ayah_penghasilan', 'ayah_pekerjaan', 'ibu_nama', 'ibu_kondisi', 'ibu_penghasilan', 'ibu_pekerjaan'],
    ];

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    public function assertSourceLink(string $source, array $row): void
    {
        $link = DB::table('school_import_links')->where(['source' => $source, 'module' => $row['module'], 'external_key' => $row['key']])->first();
        if (! $link) {
            return;
        }
        if ($link->record_table !== $row['record_table']) {
            $this->fail('external_id', 'ID sumber sudah dipetakan ke modul lain.');
        }
        $old = DB::table($row['record_table'])->where('id', $link->record_id)->first();
        if (! $old || (int) ($row['record_table'] === 'siswa' ? $old->id : $old->siswa_id) !== (int) $row['student_id']) {
            $this->fail('external_id', 'ID respons sumber sudah terhubung ke siswa lain atau perlu rekonsiliasi.');
        }
        $identityFields = match ($row['module']) {
            'grades' => ['semester', 'mata_pelajaran_id'], 'financial' => ['study_tracer_id', 'periode'],
            'aid' => ['jenis_bantuan_id'], 'assessments' => ['tanggal_asesmen'],
            'alumni' => ['perguruan_tinggi_id', 'program_studi', 'jalur_masuk', 'tahun_masuk_pt'], default => [],
        };
        foreach ($identityFields as $field) {
            $prior = $field === 'tanggal_asesmen' ? substr((string) $old->$field, 0, 10) : (string) $old->$field;
            if ($prior !== (string) ($row['data'][$field] ?? '')) {
                $this->fail('external_id', 'ID respons tidak boleh dipindahkan ke periode, mapel, atau penerimaan lain. Gunakan koreksi terarah.');
            }
        }
    }

    public function prepare(string $module, array $raw, string $mode): array
    {
        if (! isset(self::FIELDS[$module]) || array_diff(array_keys($raw), [...self::FIELDS[$module], 'external_id'])) {
            $this->fail('kolom', 'Kolom tidak termasuk template modul.');
        }
        $d = array_filter($raw, fn ($v) => $v !== null && $v !== '');
        foreach ($d as $k => $v) {
            if (is_string($v)) {
                $d[$k] = trim($v);
            }
            if (in_array($k, ['penerima_kipk', 'is_pelamar_kipk', 'aktif'], true)) {
                $v = mb_strtolower((string) $v);
                if (! in_array($v, ['1', '0', 'true', 'false', 'ya', 'tidak', 'yes', 'no'], true)) {
                    $this->fail($k, 'Gunakan Ya/Tidak atau 1/0.');
                }
                $d[$k] = in_array($v, ['1', 'true', 'ya', 'yes'], true);
            }
        }
        Validator::make($d, ['siswa_id' => 'nullable|integer|exists:siswa,id', 'nisn' => 'nullable|digits:10', 'external_id' => 'nullable|string|max:100'])->validate();
        $student = isset($d['siswa_id']) ? Siswa::findOrFail($d['siswa_id']) : (isset($d['nisn']) ? Siswa::where('nisn', $d['nisn'])->first() : null);
        if ($student && isset($d['nisn']) && $student->nisn !== $d['nisn']) {
            $this->fail('nisn', 'NISN tidak cocok dengan profil terpilih.');
        }
        if ($module !== 'students' && ! $student) {
            $this->fail('identitas', 'Pilih siswa_id atau NISN yang cocok; nama saja tidak dipakai menggabungkan identitas.');
        }
        $payload = $d;
        unset($payload['siswa_id'], $payload['nisn'], $payload['external_id']);
        $existing = null;
        $table = '';
        $key = '';
        switch ($module) {
            case 'students':
                Validator::make($d, ['nisn' => 'required_without:siswa_id|digits:10', 'nama_lengkap' => ($student ? 'sometimes|' : 'required|').'string|max:150', 'jenis_kelamin' => ($student ? 'sometimes|' : 'required|').'in:L,P',
                    'registration_email' => 'nullable|email:rfc|max:255', 'nis' => 'nullable|string|max:20', 'tahun_lulus' => 'nullable|integer|between:1900,2100', 'tanggal_lahir' => 'nullable|date_format:Y-m-d|before_or_equal:today', 'alamat_rumah' => 'nullable|string|max:2000', 'nilai_tka' => 'nullable|numeric|between:0,100'])->validate();
                if (isset($d['nisn'])) {
                    $payload['nisn'] = $d['nisn'];
                }
                if (isset($payload['registration_email'])) {
                    $payload['registration_email'] = mb_strtolower($payload['registration_email']);
                }
                $existing = $student;
                $table = 'siswa';
                $key = 'student:'.($student?->id ?? $d['nisn']);
                break;
            case 'grades':
                Validator::make($d, ['semester' => 'required|integer|between:1,5', 'mata_pelajaran_id' => 'required|integer|exists:mata_pelajaran,id', 'nilai_akhir' => 'required|numeric|between:0,100'])->validate();
                $existing = NilaiRapor::where('siswa_id', $student->id)->where('semester', $d['semester'])->where('mata_pelajaran_id', $d['mata_pelajaran_id'])->first();
                $table = 'nilai_rapor';
                $key = 'grade:'.$student->id.':'.$d['semester'].':'.$d['mata_pelajaran_id'];
                break;
            case 'assessments':
                $rules = ['tanggal_asesmen' => 'required|date_format:Y-m-d|before_or_equal:today', 'tipe_kepribadian' => 'nullable|in:Melankolis,Plegmatis,Koleris,Sanguinis', 'gaya_belajar' => 'nullable|in:Visual,Auditori,Kinestetik,Multimodal', 'iq' => 'nullable|numeric|between:0,300', 'koreksi_dari_id' => 'nullable|integer'];
                $riasec = ['realistic_score', 'investigative_score', 'artistic_score', 'social_score', 'enterprising_score', 'conventional_score'];
                $present = array_intersect($riasec, array_keys($d));
                if ($present && count($present) !== 6) {
                    $this->fail('riasec', 'Enam skor RIASEC diperlukan; skor kosong tidak dibuat menjadi nol.');
                }
                foreach ($riasec as $f) {
                    $rules[$f] = 'nullable|numeric|between:0,100';
                }
                foreach (self::FIELDS['assessments'] as $f) {
                    if (str_starts_with($f, 'mi_')) {
                        $rules[$f] = 'nullable|numeric|between:0,100';
                    }
                }
                Validator::make($d, $rules)->validate();
                $payload = array_intersect_key($payload, array_flip(['tanggal_asesmen', 'tipe_kepribadian', 'gaya_belajar', 'iq', 'koreksi_dari_id']));
                if ($present) {
                    $payload['riasec'] = array_intersect_key($d, array_flip($riasec));
                }
                foreach ($d as $f => $v) {
                    if (str_starts_with($f, 'mi_')) {
                        $payload['multiple_intelligence'][] = ['jenis_kecerdasan' => substr($f, 3), 'skor' => $v];
                    }
                }
                if (! array_intersect(array_keys($payload), ['tipe_kepribadian', 'gaya_belajar', 'iq', 'riasec', 'multiple_intelligence'])) {
                    $this->fail('asesmen', 'Isi data asesmen yang benar-benar tersedia.');
                }
                if (isset($d['koreksi_dari_id'])) {
                    $prior = AsesmenKarakteristik::where('siswa_id', $student->id)->find($d['koreksi_dari_id']);
                    if (! $prior || AsesmenKarakteristik::where('supersedes_id', $prior->id)->exists()) {
                        $this->fail('koreksi_dari_id', 'Versi koreksi harus milik siswa ini dan belum digantikan.');
                    }
                }
                $existing = AsesmenKarakteristik::where('siswa_id', $student->id)->whereDate('tanggal_asesmen', $d['tanggal_asesmen'])->latest('id')->first();
                if ($existing && (! isset($d['koreksi_dari_id']) || (int) $d['koreksi_dari_id'] !== $existing->id)) {
                    $this->fail('koreksi_dari_id', 'Tanggal tes sudah ada; koreksi harus menunjuk versi lama secara eksplisit.');
                }
                $table = 'asesmen_karakteristik';
                $key = 'assessment:'.$student->id.':'.$d['tanggal_asesmen'];
                break;
            case 'alumni':
                Validator::make($d, ['perguruan_tinggi_id' => 'required|integer|exists:perguruan_tinggi,id', 'program_studi' => 'required|string|max:255', 'strata_pendidikan' => 'required|in:D3,D4,S1,S2,S3',
                    'jalur_masuk' => 'required|in:SNBP,SNBT,PMDP,SIMAMA,SIMAMI,Mandiri,Tes Kedinasan,Reguler,Undangan,Lainnya', 'status_penerimaan' => 'required|in:menunggu,diterima,tidak_diterima', 'tahun_masuk_pt' => 'required|integer|between:1900,2100',
                    'sub_jalur_mandiri' => 'nullable|in:Rapor,Skor UTBK,Tes Kampus,Konsorsium', 'pilihan_ke' => 'nullable|integer|between:1,20',
                    'nilai_rapor_pendaftaran' => 'nullable|numeric|between:0,100', 'skor_utbk' => 'nullable|numeric|between:0,1000', 'tanggal_masuk_pt' => 'nullable|date_format:Y-m-d'])->validate();
                if ($d['jalur_masuk'] !== 'Mandiri' && isset($d['sub_jalur_mandiri'])) {
                    $this->fail('sub_jalur_mandiri', 'Subjalur hanya diisi untuk jalur Mandiri.');
                }
                if (($d['jalur_masuk'] === 'SNBP' || ($d['sub_jalur_mandiri'] ?? null) === 'Rapor') && ! isset($d['nilai_rapor_pendaftaran'])) {
                    $this->fail('nilai_rapor_pendaftaran', 'Nilai rapor jalur pendaftaran diperlukan.');
                }
                if (($d['jalur_masuk'] === 'SNBT' || ($d['sub_jalur_mandiri'] ?? null) === 'Skor UTBK') && ! isset($d['skor_utbk'])) {
                    $this->fail('skor_utbk', 'Skor UTBK asli diperlukan.');
                }
                $lookup = array_intersect_key($d, array_flip(['perguruan_tinggi_id', 'program_studi', 'jalur_masuk', 'tahun_masuk_pt']));
                $existing = StudyTracer::where('siswa_id', $student->id)->where($lookup)->first();
                $payload['siswa_id'] = $student->id;
                $table = 'study_tracer';
                $key = 'admission:'.$student->id.':'.hash('sha256', json_encode($lookup));
                break;
            case 'financial':
                Validator::make($d, ['study_tracer_id' => 'required|integer|exists:study_tracer,id', 'periode' => 'required|string|max:50', 'semester' => 'required|integer|between:1,40', 'tahun_lulus' => 'nullable|digits:4', 'status_pendidikan' => 'required|in:aktif,cuti,lulus,nonaktif',
                    'ipk_terkini' => 'nullable|numeric|between:0,4', 'nominal_ukt' => 'nullable|numeric|between:0,9999999999999', 'nominal_ipi' => 'nullable|numeric|between:0,9999999999999', 'tanggal_update' => 'required|date_format:Y-m-d|before_or_equal:today',
                    'beasiswa_id' => 'nullable|integer|exists:basis_data_beasiswa,id', 'status_beasiswa' => 'required_with:beasiswa_id|in:proses,diterima,ditolak', 'nominal_beasiswa' => 'nullable|numeric|between:0,9999999999999'])->validate();
                $admission = StudyTracer::where('siswa_id', $student->id)->find($d['study_tracer_id']);
                if (! $admission) {
                    $this->fail('study_tracer_id', 'Penerimaan PT bukan milik alumni terpilih.');
                }
                if (isset($d['nominal_ipi']) && $admission->jalur_masuk !== 'Mandiri') {
                    $this->fail('nominal_ipi', 'IPI hanya dicatat untuk jalur Mandiri.');
                }
                if (! ($d['tahun_lulus'] ?? $student->tahun_lulus)) {
                    $this->fail('tahun_lulus', 'Tahun lulus yang diketahui diperlukan.');
                }
                if (isset($d['beasiswa_id'])) {
                    $payload['beasiswa'] = [array_intersect_key($d, array_flip(['beasiswa_id', 'status_beasiswa', 'nominal_beasiswa']))];
                }
                unset($payload['beasiswa_id'], $payload['status_beasiswa'], $payload['nominal_beasiswa']);
                $existing = AlumniProgress::where('study_tracer_id', $d['study_tracer_id'])->where('periode', $d['periode'])->where('is_current', true)->first();
                if ($existing) {
                    $payload = [...$existing->only(['tahun_lulus', 'status_pendidikan', 'ipk_terkini', 'penerima_kipk', 'nominal_ukt', 'nominal_ipi', 'kategori_ekonomi', 'referensi_ekonomi']), ...$payload];
                    $oldAwards = $existing->alumniProgressBeasiswa()->orderBy('id')->get()->map(fn ($b) => $b->only(['beasiswa_id', 'status_beasiswa', 'nominal_beasiswa']))->keyBy('beasiswa_id');
                    foreach ($payload['beasiswa'] ?? [] as $award) {
                        $oldAwards[$award['beasiswa_id']] = $award;
                    }
                    $payload['beasiswa'] = $oldAwards->values()->all();
                }
                $table = 'alumni_progress';
                $key = 'finance:'.$d['study_tracer_id'].':'.$d['periode'];
                break;
            case 'aid':
                Validator::make($d, ['jenis_bantuan_id' => 'required|integer|exists:jenis_bantuan,id', 'nomor_penerima' => 'nullable|string|max:100', 'tanggal_mulai' => 'nullable|date_format:Y-m-d', 'tanggal_selesai' => 'nullable|date_format:Y-m-d|after_or_equal:tanggal_mulai', 'aktif' => 'required|boolean'])->validate();
                $existing = BantuanSiswa::where('siswa_id', $student->id)->where('jenis_bantuan_id', $d['jenis_bantuan_id'])->first();
                $table = 'bantuan_siswa';
                $key = 'aid:'.$student->id.':'.$d['jenis_bantuan_id'];
                break;
            case 'economy':
                foreach (['ayah', 'ibu'] as $parent) {
                    Validator::make($d, [$parent.'_nama' => 'required|string|max:150', $parent.'_kondisi' => 'required|in:hidup,meninggal,bercerai', $parent.'_penghasilan' => 'nullable|numeric|between:0,9999999999999', $parent.'_pekerjaan' => 'nullable|string|max:100'])->validate();
                    if ($d[$parent.'_kondisi'] !== 'hidup' && ! $student->wali()->exists()) {
                        $this->fail('wali', 'Lengkapi wali untuk kondisi keluarga ini melalui modul ekonomi.');
                    }
                }
                $existing = $student->orangTua()->first();
                $table = 'siswa';
                $key = 'economy:'.$student->id;
                break;
        }
        if ($existing && $mode === 'insert_only') {
            $this->fail('mode', 'Data sudah ada. Gunakan upsert setelah meninjau sumber.');
        }
        $fingerprint = hash('sha256', json_encode([$student?->getAttributes(), $existing?->getAttributes(), $module === 'economy' ? $student->orangTua()->orderBy('id')->get()->toArray() : null, $module === 'financial' && $existing ? $existing->alumniProgressBeasiswa()->orderBy('id')->get()->toArray() : null], JSON_THROW_ON_ERROR));

        return ['module' => $module, 'data' => $payload, 'student_id' => $student?->id, 'existing_id' => $existing?->id, 'record_table' => $table,
            'key' => (string) ($d['external_id'] ?? $key), 'natural_key' => $key, 'target_fingerprint' => $fingerprint, 'raw' => $d];
    }

    public function apply(array $row, User $actor): int
    {
        $d = $row['data'];
        $s = $row['student_id'] ? Siswa::findOrFail($row['student_id']) : null;
        $request = Request::create('/', 'POST', $d);
        $request->setUserResolver(fn () => $actor);
        $writer = app(SchoolRecordWriter::class);

        return match ($row['module']) {
            'students' => (int) tap($s ?? new Siswa, fn ($record) => $record->fill($d)->save())->id,
            'grades' => (int) tap($writer->saveGrades($this->gradeRequest($row, $actor), $s), fn () => null)->firstWhere('mata_pelajaran_id', $d['mata_pelajaran_id'])->id,
            'assessments' => (int) $writer->saveAssessment($request, $s)->id,
            'alumni' => (int) $writer->saveAdmission($request, $row['existing_id'])->id,
            'financial' => (int) app(AlumniService::class)->saveProgress($request, $s, $row['existing_id'] ? AlumniProgress::findOrFail($row['existing_id']) : null)->id,
            'aid' => (int) tap($row['existing_id'] ? BantuanSiswa::findOrFail($row['existing_id']) : new BantuanSiswa, fn ($record) => $record->fill([...$d, 'siswa_id' => $s->id])->save())->id,
            'economy' => $this->saveEconomy($s, $d),
        };
    }

    private function gradeRequest(array $row, User $actor): Request
    {
        $d = $row['data'];
        $r = Request::create('/', 'POST', ['semester' => $d['semester'], 'nilai' => [['mata_pelajaran_id' => $d['mata_pelajaran_id'], 'nilai_akhir' => $d['nilai_akhir']]]]);
        $r->setUserResolver(fn () => $actor);

        return $r;
    }

    private function saveEconomy(Siswa $s, array $d): int
    {
        foreach (['ayah', 'ibu'] as $parent) {
            $values = ['jenis_orang_tua' => $parent];
            foreach (['nama', 'kondisi', 'penghasilan', 'pekerjaan'] as $key) {
                if (array_key_exists($parent.'_'.$key, $d)) {
                    $values[$key] = $d[$parent.'_'.$key];
                }
            }
            OrangTua::updateOrCreate(['siswa_id' => $s->id, 'jenis_orang_tua' => $parent], $values);
        }

        return $s->id;
    }
}
