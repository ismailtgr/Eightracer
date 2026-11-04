<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\School\SchoolScholarshipAnalytics;
use Illuminate\Http\Request;
use Symfony\Component\Process\Process;

class SchoolScholarshipAnalyticsController extends Controller
{
    public function index(Request $request, SchoolScholarshipAnalytics $service)
    {
        return response()->json(['data' => $service->aggregate($request)]);
    }

    public function pdf(Request $request, SchoolScholarshipAnalytics $service)
    {
        $data = $service->aggregate($request);
        $data['applied_filters'] = $request->only(['angkatan', 'prodi', 'program_studi', 'perguruan_tinggi_id', 'beasiswa_id', 'beasiswa', 'periode']);
        try {
            $process = new Process([config('b4.python_binary'), base_path('tools/render_school_analytics.py')]);
            $process->setTimeout(30)->setInput(json_encode($data, JSON_THROW_ON_ERROR))->mustRun();
            $pdf = $process->getOutput();
            if (!str_starts_with($pdf, '%PDF-') || strlen($pdf) > 10 * 1024 * 1024) throw new \RuntimeException('Invalid PDF');
        } catch (\Throwable) {
            abort(503, 'Layanan PDF belum tersedia.');
        }

        return response($pdf)->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="eightracer-analitik-beasiswa.pdf"')
            ->header('Cache-Control', 'no-store, private')->header('X-Content-Type-Options', 'nosniff');
    }
}
