<?php

namespace App\Services\School;

use App\Models\StudyTracer;
use Illuminate\Http\Request;
use Symfony\Component\Process\Process;

class DigitalBook
{
    public function data(Request $r): array
    {
        $dashboard = app(FinancialDashboard::class);
        $filters = $dashboard->filters($r);
        $q = StudyTracer::where('status_penerimaan', 'diterima')->with('perguruanTinggi')->whereHas('siswa');
        foreach (['perguruan_tinggi_id', 'program_studi', 'jalur_masuk'] as $key) {
            if (isset($filters[$key])) {
                $q->where($key, $filters[$key]);
            }
        }
        if ($year = $filters['tahun_lulus'] ?? $filters['angkatan'] ?? null) {
            $q->whereHas('siswa', fn ($s) => $s->where('tahun_lulus', $year));
        }
        if (array_intersect(array_keys($filters), ['ukt_min', 'ukt_max', 'ukt_lt', 'ipk_min', 'ipk_max', 'periode', 'beasiswa_id', 'status_beasiswa', 'penerima_kipk'])) {
            $q->whereIn('id', $dashboard->latestQuery($r)->select('study_tracer_id'));
        }
        abort_if((clone $q)->count() > 10000, 422, 'Persempit filter laporan.');
        $admissions = $q->with('siswa:id,tahun_lulus')->get();
        $rows = $admissions->groupBy(fn ($t) => json_encode([$t->perguruan_tinggi_id, $t->program_studi, $t->jalur_masuk, $t->siswa->tahun_lulus]))
            ->map(fn ($g) => ['universitas' => $g->first()->perguruanTinggi->nama_pt, 'program_studi' => $g->first()->program_studi, 'jalur_masuk' => $g->first()->jalur_masuk, 'tahun_lulus' => $g->first()->siswa->tahun_lulus, 'jumlah_alumni' => $g->unique('siswa_id')->count()])->values();
        abort_if($rows->count() > 1000, 422, 'Persempit kelompok laporan.');

        return ['title' => 'Eightracer - Buku Digital Alumni', 'as_of' => now()->toIso8601String(), 'filters' => $filters, 'jumlah_alumni_penerimaan' => $admissions->unique('siswa_id')->count(), 'penerimaan' => $rows, 'keuangan' => $dashboard->aggregate($r)];
    }

    public function pdf(Request $r): string
    {
        $data = $this->data($r);
        try {
            $process = new Process([config('b4.python_binary'), base_path('tools/render_alumni_book.py')]);
            $process->setTimeout(30)->setInput(json_encode($data, JSON_THROW_ON_ERROR));
            $process->mustRun();
            $pdf = $process->getOutput();
            if (! str_starts_with($pdf, '%PDF-') || strlen($pdf) > 10 * 1024 * 1024) {
                throw new \RuntimeException('Invalid PDF');
            }

            return $pdf;
        } catch (\Throwable) {
            abort(503, 'Layanan PDF belum tersedia.');
        }
    }
}
