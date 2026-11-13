<?php

namespace App\Services\School;

use App\Models\AlumniProgress;
use Illuminate\Http\Request;

class FinancialDashboard
{
    public function filters(Request $r): array
    {
        $d = $r->validate(['tahun_lulus' => 'nullable|digits:4', 'angkatan' => 'nullable|digits:4', 'perguruan_tinggi_id' => 'nullable|integer|exists:perguruan_tinggi,id',
            'program_studi' => 'nullable|string|max:255', 'jalur_masuk' => 'nullable|string|max:50', 'periode' => 'nullable|string|max:50',
            'beasiswa_id' => 'nullable|integer|exists:basis_data_beasiswa,id', 'status_beasiswa' => 'nullable|in:proses,diterima,ditolak', 'penerima_kipk' => 'nullable|boolean',
            'ukt_min' => 'nullable|numeric|between:0,9999999999999', 'ukt_max' => 'nullable|numeric|between:0,9999999999999', 'ukt_lt' => 'nullable|numeric|between:0,9999999999999',
            'ipk_min' => 'nullable|numeric|between:0,4', 'ipk_max' => 'nullable|numeric|between:0,4']);
        abort_if(isset($d['tahun_lulus'], $d['angkatan']) && $d['tahun_lulus'] !== $d['angkatan'], 422);
        foreach ([['ukt_min', 'ukt_max'], ['ipk_min', 'ipk_max']] as [$min, $max]) {
            abort_if(isset($d[$min], $d[$max]) && $d[$min] > $d[$max], 422);
        }
        abort_if(isset($d['ukt_min'], $d['ukt_lt']) && $d['ukt_min'] >= $d['ukt_lt'], 422);

        return array_filter($d, fn ($v) => $v !== null && $v !== '');
    }

    public function latestQuery(Request $r)
    {
        $d = $this->filters($r);
        // Select the latest revision/period before filtering price, university or award status.
        $q = AlumniProgress::where('is_current', true)->whereHas('siswa', fn ($s) => LocalExampleRecords::exclude($s))->whereNotNull('study_tracer_id')
            ->whereNotExists(function ($newer) use ($d) {
                $newer->selectRaw('1')->from('alumni_progress as newer')->whereColumn('newer.siswa_id', 'alumni_progress.siswa_id')->where('newer.is_current', true)->whereNotNull('newer.study_tracer_id');
                if (isset($d['periode'])) {
                    $newer->where('newer.periode', $d['periode']);
                }
                $newer->where(fn ($n) => $n->whereColumn('newer.tanggal_update', '>', 'alumni_progress.tanggal_update')->orWhere(fn ($x) => $x->whereColumn('newer.tanggal_update', 'alumni_progress.tanggal_update')->whereColumn('newer.id', '>', 'alumni_progress.id')));
            });
        if (isset($d['periode'])) {
            $q->where('periode', $d['periode']);
        }
        if ($year = $d['tahun_lulus'] ?? $d['angkatan'] ?? null) {
            $q->where('tahun_lulus', $year);
        }
        $q->whereHas('studyTracer', function ($t) use ($d) {
            $t->where('status_penerimaan', 'diterima');
            foreach (['perguruan_tinggi_id', 'program_studi', 'jalur_masuk'] as $key) {
                if (isset($d[$key])) {
                    $t->where($key, $d[$key]);
                }
            }
        });
        foreach (['ukt_min' => ['nominal_ukt', '>='], 'ukt_max' => ['nominal_ukt', '<='], 'ukt_lt' => ['nominal_ukt', '<'], 'ipk_min' => ['ipk_terkini', '>='], 'ipk_max' => ['ipk_terkini', '<=']] as $key => [$col, $op]) {
            if (isset($d[$key])) {
                $q->where($col, $op, $d[$key]);
            }
        }
        if (array_key_exists('penerima_kipk', $d)) {
            $q->where('penerima_kipk', $r->boolean('penerima_kipk'));
        }
        if (isset($d['beasiswa_id']) || isset($d['status_beasiswa'])) {
            $q->whereHas('alumniProgressBeasiswa', function ($b) use ($d) {
                foreach (['beasiswa_id', 'status_beasiswa'] as $key) {
                    if (isset($d[$key])) {
                        $b->where($key, $d[$key]);
                    }
                }
            });
        }

        return $q;
    }

    private function stats($values, int $total): array
    {
        $known = $values->filter(fn ($v) => $v !== null)->map(fn ($v) => (float) $v);

        return ['jumlah_diketahui' => $known->count(), 'jumlah_belum_tercatat' => $total - $known->count(),
            'minimum' => $known->isEmpty() ? null : $known->min(), 'maksimum' => $known->isEmpty() ? null : $known->max(), 'rata_rata' => $known->isEmpty() ? null : round($known->avg(), 2)];
    }

    public function aggregate(Request $r): array
    {
        $q = $this->latestQuery($r);
        abort_if((clone $q)->count() > 10000, 422, 'Persempit filter.');
        $latest = $q->with(['studyTracer.perguruanTinggi', 'alumniProgressBeasiswa'])->get();
        $groups = $latest->groupBy(fn ($p) => $p->studyTracer->perguruan_tinggi_id.':'.$p->studyTracer->program_studi);
        $rows = $groups->map(function ($g) {
            $t = $g->first()->studyTracer;

            return ['perguruan_tinggi_id' => $t->perguruan_tinggi_id, 'universitas' => $t->perguruanTinggi->nama_pt, 'nama_pt' => $t->perguruanTinggi->nama_pt, 'program_studi' => $t->program_studi,
                'jumlah_alumni' => $g->count(), 'ukt' => $this->stats($g->pluck('nominal_ukt'), $g->count()), 'ipk' => $this->stats($g->pluck('ipk_terkini'), $g->count()), 'jumlah_penerima_kipk' => $g->where('penerima_kipk', true)->count()];
        })->sortBy(fn ($g) => $g['universitas'].' '.$g['program_studi'])->values();
        $history = AlumniProgress::where('is_current', true)->whereIn('study_tracer_id', $latest->pluck('study_tracer_id'))->whereIn('siswa_id', $latest->pluck('siswa_id'))
            ->whereNotNull('semester')->with('studyTracer.perguruanTinggi')->orderByDesc('tanggal_update')->orderByDesc('id')->limit(30001)->get();
        abort_if($history->count() > 30000, 422, 'Persempit filter riwayat.');
        $trend = $history->unique(fn ($p) => $p->siswa_id.':'.$p->study_tracer_id.':'.$p->semester)
            ->groupBy(fn ($p) => $p->studyTracer->perguruan_tinggi_id.':'.$p->studyTracer->program_studi.':'.$p->semester)
            ->map(function ($g) {
                $p = $g->first();

                return ['perguruan_tinggi_id' => $p->studyTracer->perguruan_tinggi_id, 'universitas' => $p->studyTracer->perguruanTinggi->nama_pt, 'program_studi' => $p->studyTracer->program_studi,
                    'semester' => $p->semester, 'jumlah_alumni' => $g->count(), 'ipk' => $this->stats($g->pluck('ipk_terkini'), $g->count())];
            })->sortBy(fn ($g) => $g['universitas'].' '.$g['program_studi'].' '.str_pad((string) $g['semester'], 2, '0', STR_PAD_LEFT))->values();

        $tuition = app(TuitionRanges::class)->aggregate($latest->pluck('studyTracer'), $latest);

        return ['as_of' => now()->toIso8601String(), 'tuition_ranges' => $tuition,
            'ukt_alumni_diketahui' => $latest->whereNotNull('nominal_ukt')->count(), 'filters' => $this->filters($r), 'jumlah_alumni' => $latest->count(),
            'ukt' => $this->stats($latest->pluck('nominal_ukt'), $latest->count()), 'ipk' => $this->stats($latest->pluck('ipk_terkini'), $latest->count()),
            'per_kampus_prodi' => $rows, 'tren_ipk' => $trend, 'catatan' => 'Snapshot memakai progres terbaru per alumni dalam periode yang dipilih. Tren mengikuti riwayat semester kampus alumni pada snapshot; nilai kosong tidak dianggap nol. Tidak menyatakan biaya atau IPK seluruh populasi kampus.'];
    }
}
