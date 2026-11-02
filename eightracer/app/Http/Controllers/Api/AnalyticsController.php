<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PublishedCohortSummary;
use App\Models\Siswa;
use App\Services\School\Analytics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function index(Request $r, Analytics $a)
    {
        return response()->json(['data' => $a->admin($r)]);
    }

    public function consent(Request $r, Siswa $siswa)
    {
        $d = $r->validate(['izinkan' => 'required|boolean', 'referensi' => 'required_if:izinkan,true|nullable|string|max:500', 'persetujuan_dikonfirmasi' => 'exclude_unless:izinkan,true|required|accepted']);
        DB::transaction(function () use ($siswa, $d) {
            $siswa->forceFill(['izinkan_analitik' => $d['izinkan'], 'referensi_izin_analitik' => $d['referensi'] ?? null, 'izin_analitik_at' => $d['izinkan'] ? now() : null])->save();
            if (! $d['izinkan']) {
                PublishedCohortSummary::whereJsonContains('member_siswa_ids', $siswa->id)->update(['is_published' => false]);
            }
        });

        return response()->json(['message' => 'Izin agregasi diperbarui.']);
    }

    public function cohorts()
    {
        return response()->json(PublishedCohortSummary::latest('id')->paginate(20));
    }

    public function create(Request $r, Analytics $a)
    {
        $d = $r->validate(['beasiswa_id' => 'required|integer|exists:basis_data_beasiswa,id', 'angkatan' => 'required|integer|between:1900,2100']);

        return response()->json(['data' => $a->createCohort($d['beasiswa_id'], $d['angkatan'], $r->user()->id)], 201);
    }

    public function publish(Request $r, int $id, Analytics $a)
    {
        $d = $r->validate(['is_published' => 'required|boolean']);
        $row = PublishedCohortSummary::findOrFail($id);
        if ($d['is_published']) {
            abort_unless(count($row->member_siswa_ids) >= config('prd.minimum_public_cohort') && Siswa::whereIn('id', $row->member_siswa_ids)->where('izinkan_analitik', true)->count() === count($row->member_siswa_ids), 422);
        }
        $row->update($d);

        return response()->json(['data' => $row]);
    }

    public function published(Analytics $a)
    {
        return response()->json(['data' => $a->published()]);
    }

    public function export(Request $r, Analytics $a)
    {
        $q = $a->query($r);
        abort_if((clone $q)->count() > 5000, 422, 'Persempit filter sebelum ekspor.');
        $rows = $q->orderBy('id')->get();

        return response()->streamDownload(function () use ($rows) {
            $stream = fopen('php://output', 'wb');
            fputcsv($stream, ['periode', 'tahun_lulus', 'program_studi', 'nama_pt', 'penerima_kipk', 'ipk', 'ukt', 'ipi']);
            foreach ($rows as $p) {
                $cells = [$p->periode, $p->tahun_lulus, $p->studyTracer?->program_studi, $p->studyTracer?->perguruanTinggi?->nama_pt, $p->penerima_kipk === null ? 'Belum diketahui' : ($p->penerima_kipk ? 'Ya' : 'Tidak'), $p->ipk_terkini, $p->nominal_ukt, $p->nominal_ipi];
                $cells = array_map(fn ($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'".$v : $v, $cells);
                fputcsv($stream, $cells);
            }fclose($stream);
        }, 'analitik-beasiswa.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, private']);
    }
}
