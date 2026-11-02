<?php

namespace App\Services\School;

use App\Models\HasilProgramEligibilitas;
use App\Models\ProgramEligibilitas;
use App\Models\Siswa;
use App\Services\Beasiswa\EligibilityEvaluator;

class SchoolEligibility
{
    public function __construct(private StudentProfile $profiles, private EligibilityEvaluator $evaluator) {}

    public function evaluate(Siswa $s, ProgramEligibilitas $p, ?array $profile = null): array
    {
        $profile = $this->programProfile($s, $p, $profile);
        $evaluation = $this->evaluator->evaluate($profile, $p->kriteria);

        return ['program_id' => $p->id, 'program' => $p->nama, 'version' => $p->version,
            'status' => ! $evaluation['lengkap'] ? 'Belum dinilai' : ($evaluation['memenuhi'] ? 'Eligible' : 'Tidak Eligible'),
            'skor' => $evaluation['skor'], 'evaluasi' => $evaluation, 'source_fingerprint' => hash('sha256', json_encode([$profile, $p->toArray()], JSON_THROW_ON_ERROR))];
    }

    public function programProfile(Siswa $s, ProgramEligibilitas $p, ?array $profile = null): array
    {
        $profile = $profile ?? $this->profiles->build($s);
        $s->loadMissing(['prestasiMurid', 'bantuanSiswa']);
        $profile['skor_prestasi'] = null;
        $achievements = $s->prestasiMurid->where('status_verifikasi', 'terverifikasi');
        if ($p->bobot_prestasi && $p->metode_prestasi && ($s->prestasi_dikonfirmasi || $achievements->isNotEmpty())) {
            $scores = $achievements->map(fn ($a) => $p->bobot_prestasi[$a->tingkat] ?? null);
            if (! $scores->containsStrict(null)) {
                $profile['skor_prestasi'] = $p->metode_prestasi === 'tertinggi' ? ($scores->max() ?? 0) : min(100, $scores->sum());
            }
        }
        $profile['bantuan_sosial_sesuai'] = null;
        if ($p->bantuan_ids && $p->mode_bantuan && ($s->bantuan_dikonfirmasi || $s->bantuanSiswa->isNotEmpty())) {
            $ids = $s->bantuanSiswa->filter(fn ($b) => $b->aktif && (! $b->tanggal_mulai || $b->tanggal_mulai <= today()) && (! $b->tanggal_selesai || $b->tanggal_selesai >= today()))->pluck('jenis_bantuan_id')->all();
            $matched = count(array_intersect($p->bantuan_ids, $ids));
            $profile['bantuan_sosial_sesuai'] = (int) ($p->mode_bantuan === 'semua' ? $matched === count($p->bantuan_ids) : $matched > 0);
        }

        return $profile;
    }

    public function calculate(Siswa $s, ProgramEligibilitas $p): HasilProgramEligibilitas
    {
        $d = $this->evaluate($s, $p);

        return HasilProgramEligibilitas::create(['siswa_id' => $s->id, 'program_id' => $p->id, 'program_version' => $p->version,
            'status' => $d['status'], 'skor' => $d['skor'], 'evaluasi' => $d['evaluasi'], 'source_fingerprint' => $d['source_fingerprint']]);
    }

    public function studentResult(Siswa $s, ProgramEligibilitas $p): array
    {
        $r = $this->evaluate($s, $p);

        return ['program_id' => $p->id, 'nama_program' => $p->nama, 'status' => $r['status'],
            'alasan' => array_map(fn ($check) => ['komponen' => $check['kriteria'], 'status' => $check['status']], $r['evaluasi']['checklist'])];
    }
}
