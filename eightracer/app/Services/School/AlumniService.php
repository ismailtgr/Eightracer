<?php

namespace App\Services\School;

use App\Models\AlumniProgress;
use App\Models\AlumniProgressBeasiswa;
use App\Models\BasisDataBeasiswa;
use App\Models\Siswa;
use App\Models\StudyTracer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AlumniService
{
    public function saveProgress(Request $r, Siswa $s, ?AlumniProgress $previous = null): AlumniProgress
    {
        $d = $r->validate(['study_tracer_id' => ['required', 'integer', Rule::exists('study_tracer', 'id')->where('siswa_id', $s->id)],
            'periode' => 'required|string|max:50', 'semester' => 'required|integer|between:1,40', 'tahun_lulus' => 'nullable|digits:4', 'status_pendidikan' => 'required|in:aktif,cuti,lulus,nonaktif',
            'ipk_terkini' => 'nullable|numeric|between:0,4', 'penerima_kipk' => 'nullable|boolean', 'nominal_ukt' => 'nullable|numeric|between:0,9999999999999', 'nominal_ipi' => 'nullable|numeric|between:0,9999999999999',
            'tanggal_update' => 'required|date|before_or_equal:today', 'beasiswa' => 'sometimes|array|max:20', 'beasiswa.*' => 'required|array:beasiswa_id,status_beasiswa,nominal_beasiswa',
            'beasiswa.*.beasiswa_id' => 'required|integer|distinct|exists:basis_data_beasiswa,id', 'beasiswa.*.status_beasiswa' => 'required|in:proses,diterima,ditolak', 'beasiswa.*.nominal_beasiswa' => 'nullable|numeric|between:0,9999999999999',
            'kategori_ekonomi' => 'nullable|in:di_bawah_1_juta,1_sampai_3_juta,3_sampai_5_juta,di_atas_5_juta,tidak_diketahui', 'referensi_ekonomi' => 'required_with:kategori_ekonomi|nullable|string|max:500',
            'is_published' => 'prohibited', 'publish_financials' => 'prohibited', 'siswa_id' => 'prohibited']);
        $tracer = StudyTracer::where('siswa_id', $s->id)->findOrFail($d['study_tracer_id']);
        abort_if(isset($d['nominal_ipi']) && mb_strtolower($tracer->jalur_masuk) !== 'mandiri', 422, 'IPI hanya untuk jalur Mandiri.');

        return DB::transaction(function () use ($d, $s, $previous, $tracer) {
            Siswa::whereKey($s->id)->lockForUpdate()->firstOrFail();
            $revision = 1;
            if ($previous) {
                $previous = AlumniProgress::where('siswa_id', $s->id)->lockForUpdate()->findOrFail($previous->id);
                abort_unless($previous->is_current && (int) $previous->study_tracer_id === $tracer->id && $previous->periode === $d['periode'], 409);
                $revision = $previous->revision + 1;
                $previous->update(['is_current' => false, 'is_published' => false]);
            } else {
                abort_if(AlumniProgress::where('study_tracer_id', $tracer->id)->where('periode', $d['periode'])->where('is_current', true)->exists(), 409, 'Periode sudah tercatat. Gunakan koreksi agar riwayat tersimpan.');
            }
            $scholarships = $d['beasiswa'] ?? [];
            unset($d['beasiswa']);
            $d['tahun_lulus'] = $d['tahun_lulus'] ?? ($s->tahun_lulus ? (string) $s->tahun_lulus : null);
            abort_unless($d['tahun_lulus'], 422, 'Lengkapi tahun lulus.');
            $row = AlumniProgress::create([...$d, 'siswa_id' => $s->id, 'revision' => $revision, 'supersedes_id' => $previous?->id, 'is_current' => true]);
            foreach ($scholarships as $item) {
                $catalog = BasisDataBeasiswa::findOrFail($item['beasiswa_id']);
                AlumniProgressBeasiswa::create([...$item, 'alumni_progress_id' => $row->id, 'periode' => $d['periode'], 'beasiswa_snapshot' => $catalog->only(['nama_beasiswa', 'penyelenggara', 'jenjang'])]);
            }

            return $row->fresh(['alumniProgressBeasiswa.beasiswa']);
        });
    }

    public function admission(StudyTracer $t, bool $admin = false): array
    {
        $d = ['id' => $t->id, 'nama_lengkap' => $t->siswa?->nama_lengkap, 'tahun_lulus' => $t->siswa?->tahun_lulus,
            'strata_pendidikan' => $t->strata_pendidikan, 'program_studi' => $t->program_studi, 'perguruan_tinggi' => $t->perguruanTinggi?->only(['id', 'nama_pt', 'jenis_pt']),
            'jalur_masuk' => $t->jalur_masuk, 'sub_jalur_mandiri' => $t->sub_jalur_mandiri, 'status_penerimaan' => $t->status_penerimaan, 'pilihan_ke' => $t->pilihan_ke,
            'tahun_masuk_pt' => $t->tahun_masuk_pt, 'tanggal_masuk_pt' => $t->tanggal_masuk_pt,
            'prestasi' => $t->prestasi
                ->filter(fn ($prestasi) => (int) $prestasi->siswa_id === (int) $t->siswa_id)
                ->map(fn ($prestasi) => $prestasi->only([
                    'id', 'jenis_prestasi', 'nama_kompetisi', 'bidang',
                    'tingkat', 'peringkat', 'penyelenggara', 'tahun',
                ]))->values()];
        if ($admin) {
            $d['kontak_wa_terbaru'] = $t->kontak_wa_terbaru;
            $d['nilai_rapor_pendaftaran'] = $t->nilai_rapor_pendaftaran;
            $d['skor_utbk'] = $t->skor_utbk;
        }

        return $d;
    }

    public function progress(AlumniProgress $p, bool $admin = false): array
    {
        $d = ['id' => $p->id, 'periode' => $p->periode, 'semester' => $p->semester, 'status_pendidikan' => $p->status_pendidikan, 'tanggal_update' => $p->tanggal_update,
            'penerima_kipk' => $p->penerima_kipk, 'ipk_terkini' => $p->ipk_terkini, 'revision' => $p->revision,
            'beasiswa' => $p->alumniProgressBeasiswa->map(fn ($b) => ['beasiswa_id' => $b->beasiswa_id, 'nama_beasiswa' => $b->beasiswa_snapshot['nama_beasiswa'] ?? $b->beasiswa?->nama_beasiswa, 'status' => $b->status_beasiswa])];
        if ($admin || $p->publish_financials) {
            $d['nominal_ukt'] = $p->nominal_ukt;
            $d['nominal_ipi'] = $p->nominal_ipi;
            $d['beasiswa'] = $p->alumniProgressBeasiswa->map(fn ($b) => ['beasiswa_id' => $b->beasiswa_id, 'nama_beasiswa' => $b->beasiswa_snapshot['nama_beasiswa'] ?? $b->beasiswa?->nama_beasiswa, 'status' => $b->status_beasiswa, 'nominal_beasiswa' => $b->nominal_beasiswa]);
        }

        return $d;
    }

    public function timeline(StudyTracer $t, bool $admin = false): array
    {
        $events = [];
        $s = $t->siswa;
        if ($s?->tanggal_masuk_sekolah) {
            $events[] = ['type' => 'masuk_sekolah', 'date' => $s->tanggal_masuk_sekolah->toDateString(), 'label' => 'Masuk sekolah'];
        }
        if ($s?->tanggal_lulus) {
            $events[] = ['type' => 'lulus_sekolah', 'date' => $s->tanggal_lulus->toDateString(), 'label' => 'Lulus sekolah'];
        } elseif ($s?->tahun_lulus) {
            $events[] = ['type' => 'lulus_sekolah', 'date' => null, 'year' => $s->tahun_lulus, 'label' => 'Lulus sekolah', 'granularity' => 'year'];
        }
        if ($t->tanggal_masuk_pt || $t->tahun_masuk_pt) {
            $events[] = ['type' => 'masuk_pt', 'date' => $t->tanggal_masuk_pt?->toDateString(), 'year' => $t->tahun_masuk_pt, 'label' => 'Masuk perguruan tinggi', 'data' => $this->admission($t, $admin)];
        }
        $query = $t->alumniProgress()->with('alumniProgressBeasiswa.beasiswa');
        if (! $admin) {
            $query->where('is_current', true)->where('is_published', true);
        }
        foreach ($query->orderBy('tanggal_update')->get() as $p) {
            $events[] = ['type' => 'progress_semester', 'date' => $p->tanggal_update?->toDateString(), 'label' => $p->periode, 'data' => $this->progress($p, $admin)];
        }
        usort($events, fn ($a, $b) => strcmp($a['date'] ?? ((string) ($a['year'] ?? '9999')), $b['date'] ?? ((string) ($b['year'] ?? '9999'))));

        return $events;
    }
}
