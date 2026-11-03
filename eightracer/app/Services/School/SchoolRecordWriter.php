<?php

namespace App\Services\School;

use App\Models\AsesmenKarakteristik;
use App\Models\AsesmenMultipleIntelligence;
use App\Models\AsesmenRiasec;
use App\Models\NilaiRapor;
use App\Models\PrestasiMurid;
use App\Models\Siswa;
use App\Models\StudyTracer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SchoolRecordWriter
{
    public function saveGrades(Request $r, Siswa $siswa)
    {
        $d = $r->validate(['semester' => 'required|integer|between:1,5', 'nilai' => 'required|array|min:1|max:100', 'nilai.*' => 'required|array:mata_pelajaran_id,nilai_akhir',
            'nilai.*.mata_pelajaran_id' => 'required|integer|distinct|exists:mata_pelajaran,id', 'nilai.*.nilai_akhir' => 'required|numeric|between:0,100']);
        DB::transaction(function () use ($d, $siswa) {
            foreach ($d['nilai'] as $row) {
                NilaiRapor::updateOrCreate(['siswa_id' => $siswa->id, 'semester' => $d['semester'], 'mata_pelajaran_id' => $row['mata_pelajaran_id']], ['nilai_akhir' => $row['nilai_akhir']]);
            }
        });

        return $siswa->nilaiRapor()->where('semester', $d['semester'])->get();
    }

    public function saveAssessment(Request $r, Siswa $siswa)
    {
        $rules = ['tanggal_asesmen' => 'required|date|before_or_equal:now', 'tipe_kepribadian' => 'nullable|in:Melankolis,Plegmatis,Koleris,Sanguinis', 'gaya_belajar' => 'nullable|in:Visual,Auditori,Kinestetik,Multimodal', 'iq' => 'nullable|numeric|between:0,300',
            'riasec' => 'nullable|array:realistic_score,investigative_score,artistic_score,social_score,enterprising_score,conventional_score', 'multiple_intelligence' => 'nullable|array|min:1|max:12',
            'multiple_intelligence.*' => 'required|array:jenis_kecerdasan,skor', 'multiple_intelligence.*.jenis_kecerdasan' => 'required|string|max:100|distinct', 'multiple_intelligence.*.skor' => 'required|numeric|between:0,100', 'koreksi_dari_id' => 'nullable|integer'];
        $letters = ['realistic_score' => 'R', 'investigative_score' => 'I', 'artistic_score' => 'A', 'social_score' => 'S', 'enterprising_score' => 'E', 'conventional_score' => 'C'];
        foreach ($letters as $field => $letter) {
            $rules['riasec.'.$field] = 'required_with:riasec|numeric|between:0,100';
        }
        $d = $r->validate($rules);
        abort_unless(($d['riasec'] ?? null) || ($d['multiple_intelligence'] ?? null) || ($d['tipe_kepribadian'] ?? null) || ($d['gaya_belajar'] ?? null) || isset($d['iq']), 422);
        $record = DB::transaction(function () use ($d, $r, $siswa, $letters) {
            Siswa::whereKey($siswa->id)->lockForUpdate()->firstOrFail();
            if (! empty($d['koreksi_dari_id'])) {
                AsesmenKarakteristik::where('siswa_id', $siswa->id)->findOrFail($d['koreksi_dari_id']);
                abort_if(AsesmenKarakteristik::where('supersedes_id', $d['koreksi_dari_id'])->exists(), 409);
            }
            AsesmenKarakteristik::where('siswa_id', $siswa->id)->update(['is_current' => false]);
            $codes = null;
            if (! empty($d['riasec']) && max($d['riasec']) > 0) {
                $scores = $d['riasec'];
                uksort($scores, fn ($a, $b) => ($scores[$b] <=> $scores[$a]) ?: strcmp($letters[$a], $letters[$b]));
                $codes = implode('', array_map(fn ($key) => $letters[$key], array_slice(array_keys($scores), 0, 3)));
            }
            $mi = collect($d['multiple_intelligence'] ?? [])->sortByDesc('skor')->first();
            $record = new AsesmenKarakteristik;
            $record->forceFill(['siswa_id' => $siswa->id, 'tanggal_asesmen' => $d['tanggal_asesmen'], 'tipe_kepribadian' => $d['tipe_kepribadian'] ?? null, 'gaya_belajar' => $d['gaya_belajar'] ?? null, 'iq' => $d['iq'] ?? null,
                'kode_riasec' => $codes, 'kecerdasan_majemuk_dominan' => $mi['jenis_kecerdasan'] ?? null, 'is_current' => false, 'supersedes_id' => $d['koreksi_dari_id'] ?? null, 'created_by' => $r->user()->id])->save();
            if (! empty($d['riasec'])) {
                $row = new AsesmenRiasec;
                $row->forceFill([...$d['riasec'], 'siswa_id' => $siswa->id, 'asesmen_id' => $record->id, 'tanggal_asesmen' => $d['tanggal_asesmen'], 'kode_hasil' => $codes])->save();
            }
            foreach ($d['multiple_intelligence'] ?? [] as $item) {
                $row = new AsesmenMultipleIntelligence;
                $row->forceFill([...$item, 'siswa_id' => $siswa->id, 'asesmen_id' => $record->id, 'tanggal_asesmen' => $d['tanggal_asesmen']])->save();
            }

            $superseded = AsesmenKarakteristik::where('siswa_id', $siswa->id)->whereNotNull('supersedes_id')->pluck('supersedes_id');
            AsesmenKarakteristik::where('siswa_id', $siswa->id)->whereNotIn('id', $superseded)->latest('tanggal_asesmen')->latest('id')->first()->update(['is_current' => true]);

            return $record->fresh();
        });

        return $record;
    }

    public function saveAdmission(Request $r, ?int $id = null)
    {
        $d = $r->validate(['siswa_id' => 'required_without:alumni_baru|nullable|integer|exists:siswa,id', 'alumni_baru' => 'nullable|array:nama_lengkap,jenis_kelamin,nisn,tahun_lulus',
            'alumni_baru.nama_lengkap' => 'required_with:alumni_baru|string|max:150', 'alumni_baru.jenis_kelamin' => 'nullable|in:L,P', 'alumni_baru.nisn' => 'nullable|digits:10|unique:siswa,nisn', 'alumni_baru.tahun_lulus' => 'required_with:alumni_baru|digits:4',
            'perguruan_tinggi_id' => 'required|integer|exists:perguruan_tinggi,id', 'strata_pendidikan' => 'required|in:D3,D4,S1,S2,S3', 'program_studi' => 'required|string|max:255',
            'jalur_masuk' => 'required|in:SNBP,SNBT,PMDP,SIMAMA,SIMAMI,Mandiri,Tes Kedinasan,Reguler,Undangan,Lainnya', 'sub_jalur_mandiri' => 'nullable|in:Rapor,Skor UTBK,Tes Kampus,Konsorsium',
            'pilihan_ke' => 'nullable|integer|between:1,20', 'status_penerimaan' => 'required|in:menunggu,diterima,tidak_diterima', 'nilai_rapor_pendaftaran' => 'nullable|numeric|between:0,100', 'skor_utbk' => 'nullable|numeric|between:0,1000',
            'kontak_wa_terbaru' => 'nullable|string|max:20', 'is_pelamar_kipk' => 'sometimes|boolean', 'tanggal_pengisian' => 'nullable|date|before_or_equal:today', 'tanggal_masuk_pt' => 'nullable|date', 'tahun_masuk_pt' => 'nullable|integer|between:1900,2100',
            'prestasi_ids' => 'sometimes|array|max:30', 'prestasi_ids.*' => 'integer|distinct|exists:prestasi_murid,id', 'izinkan_tampil_di_buku' => 'prohibited']);
        abort_if(isset($d['siswa_id']) && ! empty($d['alumni_baru']), 422);
        if ($d['jalur_masuk'] === 'SNBP' || ($d['jalur_masuk'] === 'Mandiri' && ($d['sub_jalur_mandiri'] ?? null) === 'Rapor')) {
            abort_unless(isset($d['nilai_rapor_pendaftaran']), 422);
        }
        if ($d['jalur_masuk'] === 'SNBT' || ($d['jalur_masuk'] === 'Mandiri' && ($d['sub_jalur_mandiri'] ?? null) === 'Skor UTBK')) {
            abort_unless(isset($d['skor_utbk']), 422);
        }
        if ($d['jalur_masuk'] !== 'Mandiri') {
            abort_if(! empty($d['sub_jalur_mandiri']), 422);
        }
        $record = DB::transaction(function () use ($d, $id) {
            $ids = $d['prestasi_ids'] ?? null;
            unset($d['prestasi_ids']);
            if (! empty($d['alumni_baru'])) {
                abort_if($id, 422);
                $s = Siswa::create([...$d['alumni_baru'], 'user_id' => null]);
                $d['siswa_id'] = $s->id;
            }unset($d['alumni_baru']);
            $t = $id ? StudyTracer::lockForUpdate()->findOrFail($id) : new StudyTracer;
            if ($id) {
                abort_unless((int) $t->siswa_id === (int) $d['siswa_id'], 422);
            }
            $t->fill($d);
            if ($id) {
                $t->forceFill(['izinkan_tampil_di_buku' => false, 'consent_reference' => null, 'consent_at' => null, 'consent_by' => null]);
            }$t->save();
            if ($ids !== null) {
                abort_unless(PrestasiMurid::where('siswa_id', $t->siswa_id)->whereIn('id', $ids)->count() === count($ids), 422);
                $t->prestasi()->sync($ids);
            }

            return $t->load(['siswa', 'perguruanTinggi', 'prestasi']);
        });

        return $record;
    }
}
