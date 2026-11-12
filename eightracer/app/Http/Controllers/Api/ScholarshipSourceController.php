<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolScholarshipObservation;
use Illuminate\Http\Request;

class ScholarshipSourceController extends Controller
{
    public const NOTE = 'Catatan asli kolom KIP/beasiswa sekolah, bukan konfirmasi penerima atau syarat resmi. UKT, IPK, nominal, dan keputusan beasiswa tidak disimpulkan dari penerimaan PT.';

    public function index(Request $r)
    {
        $d = $r->validate(['include_summary' => 'sometimes|boolean', 'search' => 'nullable|string|max:100', 'tahun_lulus' => 'nullable|integer|between:2024,2026', 'siswa_id' => 'nullable|integer|exists:siswa,id',
            'study_tracer_id' => 'nullable|integer|exists:study_tracer,id', 'per_page' => 'sometimes|integer|between:1,100',
            'jenis_keterangan' => 'nullable|in:tanpa_keterangan,indikator_kip,kode_sumber,referensi_kip,pembebasan_biaya,referensi_beasiswa,keterangan_belum_jelas']);
        $q = SchoolScholarshipObservation::with(['siswa:id,nama_lengkap,tahun_lulus',
            'studyTracer:id,siswa_id,perguruan_tinggi_id,program_studi,strata_pendidikan,jalur_masuk', 'studyTracer.perguruanTinggi:id,nama_pt']);
        foreach (['tahun_lulus', 'siswa_id', 'study_tracer_id', 'jenis_keterangan'] as $field) {
            if (isset($d[$field])) {
                $q->where($field, $d[$field]);
            }
        }
        if ($r->filled('search')) {
            $pattern = '%'.$d['search'].'%';
            $q->where(fn ($filter) => $filter->whereLike('nilai_sumber', $pattern)
                ->orWhereLike('kampus_sumber', $pattern)->orWhereLike('prodi_sumber', $pattern)
                ->orWhereHas('siswa', fn ($student) => $student->whereLike('nama_lengkap', $pattern))
                ->orWhereHas('studyTracer', fn ($admission) => $admission->whereLike('program_studi', $pattern)
                    ->orWhereHas('perguruanTinggi', fn ($campus) => $campus->whereLike('nama_pt', $pattern))));
        }
        $page = $q->orderBy('id')->paginate($r->integer('per_page', 20))->appends($d);
        $page->through(function ($row) {
            $value = $row->toArray();
            $value['sumber'] = ['url' => 'https://docs.google.com/spreadsheets/d/'.$row->source_file_id.'/edit',
                'sheet' => $row->source_sheet, 'cell' => $row->source_column.$row->source_row];
            $value['status_beasiswa'] = 'belum_dikonfirmasi';
            $value['catatan'] = self::NOTE;

            return $value;
        });

        return response()->json($page->toArray() + ($r->boolean('include_summary') ? ['school_summary' => $this->counts()] : []));
    }

    private function counts(): array
    {
        return ['jumlah_catatan' => SchoolScholarshipObservation::count(),
            'terhubung_siswa' => SchoolScholarshipObservation::whereNotNull('siswa_id')->count(),
            'terhubung_penerimaan_pt' => SchoolScholarshipObservation::whereNotNull('study_tracer_id')->count()];
    }

    public function summary()
    {
        return response()->json(['data' => [
            ...$this->counts(),
            'per_angkatan_jenis' => SchoolScholarshipObservation::selectRaw('tahun_lulus, jenis_keterangan, COUNT(*) as jumlah')
                ->groupBy('tahun_lulus', 'jenis_keterangan')->orderBy('tahun_lulus')->orderBy('jenis_keterangan')->get(),
            'referensi' => SchoolScholarshipObservation::selectRaw('tahun_lulus, jenis_keterangan, nilai_sumber, COUNT(*) as jumlah')
                ->whereIn('jenis_keterangan', ['referensi_beasiswa', 'referensi_kip', 'pembebasan_biaya', 'kode_sumber', 'keterangan_belum_jelas'])
                ->groupBy('tahun_lulus', 'jenis_keterangan', 'nilai_sumber')->orderBy('tahun_lulus')->get(),
            'catatan' => self::NOTE,
        ]]);
    }
}
