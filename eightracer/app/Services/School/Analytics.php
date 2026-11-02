<?php

namespace App\Services\School;

use App\Models\AlumniProgress;
use App\Models\PublishedCohortSummary;
use App\Models\Siswa;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class Analytics
{
    public function query(Request $r)
    {
        $r->validate(['angkatan' => 'nullable|digits:4', 'program_studi' => 'nullable|string|max:100', 'jenis_pt' => 'nullable|in:Negeri,Swasta,Kedinasan,Luar Negeri', 'beasiswa_id' => 'nullable|integer|exists:basis_data_beasiswa,id', 'periode' => 'nullable|string|max:50']);
        $q = AlumniProgress::where('is_current', true)->with(['siswa', 'studyTracer.perguruanTinggi', 'alumniProgressBeasiswa.beasiswa']);
        if ($r->filled('angkatan')) {
            $q->where('tahun_lulus', $r->input('angkatan'));
        }
        if ($r->filled('program_studi')) {
            $q->whereHas('studyTracer', fn ($t) => $t->where('program_studi', $r->input('program_studi')));
        }
        if ($r->filled('jenis_pt')) {
            $q->whereHas('studyTracer.perguruanTinggi', fn ($p) => $p->where('jenis_pt', $r->input('jenis_pt')));
        }
        if ($r->filled('beasiswa_id')) {
            $q->whereHas('alumniProgressBeasiswa', fn ($b) => $b->where('beasiswa_id', $r->input('beasiswa_id')));
        }
        if ($r->filled('periode')) {
            $q->where('periode', $r->input('periode'));
        }

return $q;
    }

    public function admin(Request $r): array
    {
        $q = $this->query($r);
        abort_if((clone $q)->count() > 10000, 422, 'Persempit filter periode atau angkatan.');
        $all = $q->get();
        $latest = $all->sortByDesc(fn ($p) => $p->tanggal_update?->format('Y-m-d').' '.str_pad((string) $p->id, 12, '0', STR_PAD_LEFT))->unique('siswa_id');
        $scholarshipId = $r->filled('beasiswa_id') ? $r->integer('beasiswa_id') : null;
        $byScholarship = [];
        foreach ($latest as $p) {
            foreach ($p->alumniProgressBeasiswa->where('status_beasiswa', 'diterima') as $b) {
                if ($scholarshipId !== null && (int) $b->beasiswa_id !== $scholarshipId) {
                    continue;
                }
                $key = (string) $b->beasiswa_id;
                $byScholarship[$key]['beasiswa_id'] = $b->beasiswa_id;
                $byScholarship[$key]['nama_beasiswa'] = $b->beasiswa_snapshot['nama_beasiswa'] ?? $b->beasiswa?->nama_beasiswa;
                $byScholarship[$key]['ids'][] = $p->siswa_id;
            }
        }
        $scholarships = array_map(fn ($g) => ['beasiswa_id' => $g['beasiswa_id'], 'nama_beasiswa' => $g['nama_beasiswa'], 'jumlah' => count(array_unique($g['ids']))], array_values($byScholarship));

        return ['as_of' => now()->toIso8601String(), 'jumlah_alumni_tercatat' => $latest->count(), 'jumlah_penerima_kipk' => $latest->where('penerima_kipk', true)->count(),
            'distribusi_beasiswa' => $scholarships,
            'per_angkatan' => $latest->groupBy('tahun_lulus')->map(fn ($g, $year) => ['angkatan' => $year, 'jumlah_alumni' => $g->count(), 'penerima_kipk' => $g->where('penerima_kipk', true)->count()])->values(),
            'tren_periode' => $all->groupBy('periode')->map(fn ($g, $period) => [
                'periode' => $period ?: 'Belum dicatat',
                'jumlah_alumni' => $g->unique('siswa_id')->count(),
                'penerima_kipk' => $g->where('penerima_kipk', true)->unique('siswa_id')->count(),
                'beasiswa' => $this->scholarshipTrend($g, $scholarshipId),
            ])->values(),
            'catatan' => 'Hitungan per alumni memakai progres terakhir dalam filter; tren memakai entri per periode. Angka ini tidak menyatakan hubungan sebab akibat.'];
    }

    private function scholarshipTrend(Collection $progress, ?int $scholarshipId): array
    {
        $groups = [];
        $seen = [];
        $latestFirst = $progress->sortByDesc(fn ($p) => $p->tanggal_update?->format('Y-m-d').' '.str_pad((string) $p->id, 12, '0', STR_PAD_LEFT));
        foreach ($latestFirst as $row) {
            foreach ($row->alumniProgressBeasiswa as $award) {
                $id = (int) $award->beasiswa_id;
                if ($scholarshipId !== null && $id !== $scholarshipId) {
                    continue;
                }
                $status = $award->status_beasiswa;
                if (! in_array($status, ['proses', 'diterima', 'ditolak'], true)) {
                    continue;
                }
                // Multiple PT records must not count one alumnus in two statuses.
                $key = $id.':'.$row->siswa_id;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $groups[$id] ??= [
                    'beasiswa_id' => $id,
                    'nama_beasiswa' => $award->beasiswa_snapshot['nama_beasiswa'] ?? $award->beasiswa?->nama_beasiswa,
                    'jumlah_alumni' => 0,
                    'status' => ['proses' => 0, 'diterima' => 0, 'ditolak' => 0],
                ];
                $groups[$id]['jumlah_alumni']++;
                $groups[$id]['status'][$status]++;
            }
        }
        ksort($groups, SORT_NUMERIC);

        return array_values($groups);
    }

    public function createCohort(int $scholarship, int $year, int $userId): PublishedCohortSummary
    {
        $all = AlumniProgress::where('is_current', true)->where('tahun_lulus', (string) $year)->whereHas('siswa', fn ($s) => $s->where('izinkan_analitik', true))
            ->whereHas('alumniProgressBeasiswa', fn ($b) => $b->where('beasiswa_id', $scholarship)->where('status_beasiswa', 'diterima'))
            ->with(['siswa.nilaiRapor', 'alumniProgressBeasiswa'])->orderBy('tanggal_update')->orderBy('id')->get()->unique('siswa_id');
        $minimum = config('prd.minimum_public_cohort');
        abort_unless($all->count() >= $minimum, 422, 'Jumlah alumni berizin belum mencukupi untuk ringkasan publik.');
        $rapor = $all->map(function ($p) {
            $g = $p->siswa->nilaiRapor->whereBetween('semester', [1, 5]);

            return $g->pluck('semester')->unique()->count() === 5 ? $g->avg('nilai_akhir') : null;
        })->filter(fn ($v) => $v !== null);
        $semesters = $all->pluck('semester')->filter(fn ($v) => $v !== null);
        $bands = $all->pluck('kategori_ekonomi')->map(fn ($v) => $v ?? 'tidak_diketahui')->countBy();
        $bandSummary = $bands->every(fn ($n) => $n >= $minimum) ? $bands->map(fn ($n, $band) => ['kategori' => $band, 'jumlah' => $n])->values()->all() : null;
        $summary = ['jumlah_penerima' => $all->count(), 'as_of' => now()->toIso8601String(),
            'rentang_rapor_sma' => $rapor->count() >= $minimum ? ['minimum_bucket' => floor($rapor->min() / 5) * 5, 'maximum_bucket' => min(100, ceil($rapor->max() / 5) * 5), 'unit_bucket' => 5] : null,
            'basis_semester' => 'Semester penerimaan paling awal yang tercatat; belum tentu waktu mulai beasiswa sebenarnya.', 'semester_mulai_umum' => $semesters->count() >= $minimum ? (int) $semesters->countBy()->sortDesc()->keys()->first() : null,
            'ringkasan_kondisi_ekonomi' => $bandSummary, 'catatan' => 'Data hanya dari alumni yang memberi izin agregasi. Rentang dibulatkan; kelompok kecil dan data yang belum tersedia tidak ditampilkan.'];

        return PublishedCohortSummary::create(['beasiswa_id' => $scholarship, 'angkatan' => $year, 'summary' => $summary, 'member_siswa_ids' => $all->pluck('siswa_id')->values()->all(), 'created_by' => $userId, 'is_published' => false]);
    }

    public function published()
    {
        return PublishedCohortSummary::where('is_published', true)->latest('id')->get()->filter(function ($row) {
            $ids = $row->member_siswa_ids;

            return count($ids) >= config('prd.minimum_public_cohort') && Siswa::whereIn('id', $ids)->where('izinkan_analitik', true)->count() === count($ids);
        })->map(fn ($row) => ['id' => $row->id, 'beasiswa_id' => $row->beasiswa_id, 'angkatan' => $row->angkatan, 'ringkasan' => $row->summary])->values();
    }
}
