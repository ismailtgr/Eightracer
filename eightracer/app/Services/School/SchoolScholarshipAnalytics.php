<?php

namespace App\Services\School;

use App\Models\AlumniProgress;
use App\Models\BasisDataBeasiswa;
use App\Models\SchoolScholarshipObservation;
use App\Models\Siswa;
use App\Models\StudyTracer;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SchoolScholarshipAnalytics
{
    private const KIP = 'KIP/KIP-K';

    public static function sourceProgram(SchoolScholarshipObservation $row): ?string
    {
        $value = mb_strtoupper(trim($row->nilai_sumber));
        $column = preg_replace('/[\s-]+/u', '', mb_strtoupper($row->nama_kolom));
        $token = preg_replace('/[\s-]+/u', '', $value);
        if ((in_array($column, ['KIP', 'KIPK'], true) && in_array($value, ['YA', 'V', '✓', '✔'], true))
            || in_array($token, ['KIP', 'KIPK'], true)) {
            return self::KIP;
        }
        if ($column === 'BEASISWA' && ! preg_match('/\b(TIDAK|BELUM|BUKAN)\b/u', $value)
            && (str_contains($value, 'BEASISWA') || $value === 'PRESTASI BASKET')) {
            return trim(preg_replace('/\s+/u', ' ', $row->nilai_sumber));
        }

        return null;
    }

    private function key(string $label): string
    {
        $token = preg_replace('/[\s\/-]+/u', '', mb_strtoupper(trim($label)));
        return in_array($token, ['KIP', 'KIPK', 'KIPKIPK'], true)
            ? 'kip' : 'program:'.substr(hash('sha256', mb_strtolower(trim($label))), 0, 20);
    }

    private function bounded($query): Collection
    {
        $rows = $query->limit(50001)->get();
        abort_if($rows->count() > 50000, 422, 'Persempit cakupan data sekolah.');
        return $rows;
    }

    private function stats(Collection $values, int $total): array
    {
        $known = $values->filter(fn ($value) => $value !== null)->map(fn ($v) => (float) $v);
        return ['known' => $known->count(), 'unknown' => max(0, $total - $known->count()),
            'minimum' => $known->isEmpty() ? null : $known->min(), 'maximum' => $known->isEmpty() ? null : $known->max(),
            'mean' => $known->isEmpty() ? null : round($known->avg(), 2)];
    }

    public function aggregate(Request $request): array
    {
        $input = $request->validate([
            'angkatan' => 'nullable|digits:4', 'perguruan_tinggi_id' => 'nullable|integer|min:1',
            'program_studi' => 'nullable|string|max:255', 'prodi' => 'nullable|string|max:100',
            'beasiswa' => ['nullable', 'string', 'regex:/^(kip|program:[a-f0-9]{20})$/'],
            'beasiswa_id' => ['nullable', 'string', 'regex:/^([1-9][0-9]*|kip|program:[a-f0-9]{20})$/'],
            'periode' => 'nullable|string|max:50',
        ]);
        $filter = array_filter($input, fn ($v) => $v !== null && $v !== '');
        if (isset($filter['prodi'])) {
            abort_if(isset($filter['program_studi']) && $filter['prodi'] !== $filter['program_studi'], 422);
            $filter['program_studi'] = $filter['prodi'];
        }
        $catalogFilter = null;
        if (isset($filter['beasiswa_id'])) {
            $value = $filter['beasiswa_id'];
            if (ctype_digit($value)) {
                $catalog = BasisDataBeasiswa::find($value);
                abort_unless($catalog, 422, 'Beasiswa tidak ditemukan.');
                $catalogFilter = $catalog->id;
                $value = $this->key($catalog->nama_beasiswa);
            }
            abort_if(isset($filter['beasiswa']) && $filter['beasiswa'] !== $value, 422);
            $filter['beasiswa'] = $value;
        }

        $allStudents = $this->bounded(LocalExampleRecords::exclude(Siswa::query())->whereNotNull('tahun_lulus')->select(['id', 'tahun_lulus']));
        $allIds = $allStudents->pluck('id');
        $allAdmissions = $this->bounded(StudyTracer::where('status_penerimaan', 'diterima')
            ->whereIn('siswa_id', $allIds)->with('perguruanTinggi:id,nama_pt'));
        $allSources = $this->bounded(SchoolScholarshipObservation::whereIn('siswa_id', $allIds));
        $progress = $this->bounded(AlumniProgress::where('is_current', true)->whereIn('siswa_id', $allIds)
            ->whereIn('study_tracer_id', $allAdmissions->pluck('id'))->with('alumniProgressBeasiswa.beasiswa')
            ->orderByDesc('tanggal_update')->orderByDesc('id'));

        $labels = ['kip' => self::KIP];
        $catalogs = BasisDataBeasiswa::orderBy('id')->get(['id', 'nama_beasiswa']);
        foreach ($catalogs as $item) $labels[$this->key($item->nama_beasiswa)] = $item->nama_beasiswa;
        foreach ($allSources as $source) {
            if ($name = self::sourceProgram($source)) $labels[$this->key($name)] = $name;
        }
        foreach ($progress as $p) foreach ($p->alumniProgressBeasiswa as $award) {
            if ($name = $award->beasiswa_snapshot['nama_beasiswa'] ?? $award->beasiswa?->nama_beasiswa) $labels[$this->key($name)] = $name;
        }
        if ($catalogFilter) $labels[$filter['beasiswa']] = $catalog->nama_beasiswa;
        abort_if(isset($filter['beasiswa']) && ! isset($labels[$filter['beasiswa']]), 422, 'Program tidak ditemukan pada data sekolah.');

        $students = $allStudents->when(isset($filter['angkatan']), fn ($g) => $g->where('tahun_lulus', $filter['angkatan']));
        $admissions = $allAdmissions->whereIn('siswa_id', $students->pluck('id'))
            ->when(isset($filter['perguruan_tinggi_id']), fn ($g) => $g->where('perguruan_tinggi_id', $filter['perguruan_tinggi_id']))
            ->when(isset($filter['program_studi']), fn ($g) => $g->filter(fn ($t) => mb_stripos($t->program_studi ?? '', $filter['program_studi']) !== false));
        $scopedAdmission = isset($filter['perguruan_tinggi_id']) || isset($filter['program_studi']);
        if ($scopedAdmission) $students = $students->whereIn('id', $admissions->pluck('siswa_id'));
        $history = $progress->whereIn('siswa_id', $students->pluck('id'))->whereIn('study_tracer_id', $admissions->pluck('id'));
        if (isset($filter['periode'])) {
            $history = $history->where('periode', $filter['periode']);
            $students = $students->whereIn('id', $history->pluck('siswa_id'));
            $admissions = $admissions->whereIn('id', $history->pluck('study_tracer_id'));
        }
        $current = $history->unique('siswa_id');
        // Source spreadsheets have no award semester. They must not fill period-specific results.
        $sources = isset($filter['periode']) ? collect() : $allSources->whereIn('siswa_id', $students->pluck('id'));
        if ($scopedAdmission) $sources = $sources->whereIn('study_tracer_id', $admissions->pluck('id'));
        $members = [];
        foreach ($sources as $source) {
            if ($name = self::sourceProgram($source)) $members[$this->key($name)][$source->siswa_id] = true;
        }
        foreach ($current as $p) {
            if ($p->penerima_kipk === true) $members['kip'][$p->siswa_id] = true;
            foreach ($p->alumniProgressBeasiswa as $award) if ($award->status_beasiswa === 'diterima') {
                if ($name = $award->beasiswa_snapshot['nama_beasiswa'] ?? $award->beasiswa?->nama_beasiswa) $members[$this->key($name)][$p->siswa_id] = true;
            }
        }
        if (isset($filter['beasiswa'])) {
            $ids = array_keys($members[$filter['beasiswa']] ?? []);
            if ($catalogFilter) {
                $ids = $current->filter(fn ($p) => $p->alumniProgressBeasiswa->contains(fn ($award) => $award->status_beasiswa === 'diterima' && (int) $award->beasiswa_id === (int) $catalogFilter))->pluck('siswa_id')->all();
                if ($catalogs->filter(fn ($item) => $this->key($item->nama_beasiswa) === $filter['beasiswa'])->count() === 1) {
                    $ids = array_unique([...$ids, ...$sources->filter(fn ($source) => ($name = self::sourceProgram($source)) && $this->key($name) === $filter['beasiswa'])->pluck('siswa_id')->all()]);
                }
            }
            $students = $students->whereIn('id', $ids);
            $admissions = $admissions->whereIn('siswa_id', $ids);
            $sources = $sources->whereIn('siswa_id', $ids);
            $current = $current->whereIn('siswa_id', $ids);
            $history = $history->whereIn('siswa_id', $ids);
        }
        $scopeIds = array_fill_keys($students->pluck('id')->all(), true);
        foreach ($members as $key => $ids) $members[$key] = array_intersect_key($ids, $scopeIds);
        $kipIds = $members['kip'] ?? [];
        $otherIds = [];
        foreach ($members as $key => $ids) if ($key !== 'kip') $otherIds += $ids;
        $distribution = [];
        foreach ($members as $key => $ids) if ($ids && (! isset($filter['beasiswa']) || $filter['beasiswa'] === $key)) {
            $distribution[] = ['value' => $key, 'label' => $labels[$key], 'count' => count($ids)];
        }
        usort($distribution, fn ($a, $b) => ($b['count'] <=> $a['count']) ?: strcmp($a['label'], $b['label']));
        $cohorts = $students->groupBy('tahun_lulus')->map(fn ($g, $year) => ['label' => (string) $year,
            'count' => count(array_intersect_key($kipIds, array_fill_keys($g->pluck('id')->all(), true)))])->sortBy('label')->values();
        $ranges = $admissions->groupBy(fn ($t) => json_encode([$t->perguruan_tinggi_id, $t->program_studi]))
            ->map(function ($g) use ($current) {
                $values = $current->whereIn('study_tracer_id', $g->pluck('id'));
                $count = $g->unique('siswa_id')->count();
                return ['university' => $g->first()->perguruanTinggi?->nama_pt ?? '-', 'program' => $g->first()->program_studi ?? '-',
                    'count' => $count, 'stats' => $this->stats($values->pluck('nominal_ukt'), $count)];
            })->sortBy(fn ($row) => $row['university'].' '.$row['program'])->values();
        $periods = $history->groupBy('periode')->map(function ($group, $period) use ($filter) {
            $rows = $group->unique('siswa_id');
            $programs = [];
            foreach ($rows as $p) foreach ($p->alumniProgressBeasiswa as $award) {
                $name = $award->beasiswa_snapshot['nama_beasiswa'] ?? $award->beasiswa?->nama_beasiswa;
                if (! $name || (isset($filter['beasiswa']) && $this->key($name) !== $filter['beasiswa'])) continue;
                $programs[$name][$award->status_beasiswa][$p->siswa_id] = true;
            }
            $scholarships = [];
            foreach ($programs as $name => $states) $scholarships[] = ['label' => $name,
                'count' => count(($states['diterima'] ?? []) + ($states['proses'] ?? []) + ($states['ditolak'] ?? [])),
                'processing' => count($states['proses'] ?? []), 'accepted' => count($states['diterima'] ?? []), 'rejected' => count($states['ditolak'] ?? [])];
            return ['label' => (string) $period, 'count' => $rows->count(), 'kipk' => $rows->where('penerima_kipk', true)->count(), 'scholarships' => $scholarships];
        })->sortBy('label')->values();
        $notes = [
            'KIP/KIP-K dan beasiswa memakai catatan sekolah per alumni unik; V pada kolom KIPK dibaca sebagai tanda centang. Ini bukan verifikasi keputusan penyelenggara.',
            'Total alumni adalah profil lulusan yang terdata. Satu alumni dapat memiliki beberapa penerimaan PT. Catatan tanpa identitas cocok tidak dimasukkan ke jumlah alumni.',
            'Filter kampus/prodi hanya memakai catatan yang terhubung ke penerimaan tersebut. UKT/IPK memakai progres terbaru; data yang belum ada tetap kosong.',
            'Periode berarti periode progres kuliah. Catatan sekolah tanpa periode penerimaan beasiswa tidak dimasukkan ke tren semester.',
        ];

        return ['as_of' => now('Asia/Jakarta')->toIso8601String(), 'summary' => ['alumni' => $students->count(),
            'admitted_alumni' => $admissions->unique('siswa_id')->count(), 'admissions' => $admissions->count(),
            'scholarship_non_kip' => count($otherIds), 'kip' => count($kipIds), 'all_aid' => count($otherIds + $kipIds),
            'kip_v_marks' => $sources->filter(fn ($s) => mb_strtoupper(trim($s->nilai_sumber)) === 'V' && self::sourceProgram($s) === self::KIP)->unique('siswa_id')->count(),
            'source_rows' => $sources->count(), 'source_unlinked' => SchoolScholarshipObservation::whereNull('siswa_id')->count()],
            'filters' => ['cohorts' => $allStudents->pluck('tahun_lulus')->unique()->sort()->map(fn ($v) => (string) $v)->values(),
                'scholarships' => collect($labels)->map(fn ($label, $value) => ['value' => (string) ($catalogs->first(fn ($item) => $this->key($item->nama_beasiswa) === $value)?->id ?? $value), 'label' => $label])->sortBy('label')->values()],
            'kip_by_cohort' => $cohorts, 'scholarships' => $distribution,
            'financial' => ['measured_alumni' => $current->count(), 'ukt' => $this->stats($current->pluck('nominal_ukt'), $students->count()),
                'ipk' => $this->stats($current->pluck('ipk_terkini'), $students->count()), 'ranges' => $ranges,
                'ipk_distribution' => collect([[0, 2.5, '< 2,50'], [2.5, 3, '2,50–2,99'], [3, 3.5, '3,00–3,49'], [3.5, 4.01, '3,50–4,00']])->map(fn ($bin) => [
                    'label' => $bin[2], 'count' => $current->filter(fn ($p) => $p->ipk_terkini !== null && (float) $p->ipk_terkini >= $bin[0] && (float) $p->ipk_terkini < $bin[1])->count(),
                ])->values(),
                'ipk_trend' => $history->groupBy('periode')->map(fn ($g, $period) => ['label' => (string) $period,
                    'stats' => $this->stats($g->unique('siswa_id')->pluck('ipk_terkini'), $g->unique('siswa_id')->count())])->sortBy('label')->values()],
            'periods' => $periods, 'notes' => $notes];
    }
}
