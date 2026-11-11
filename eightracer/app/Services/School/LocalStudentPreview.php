<?php
namespace App\Services\School;

use Illuminate\Http\Request;
use App\Models\StudyTracer;
use App\Services\School\AlumniAdmissionPath;

class LocalStudentPreview
{
    public function enabled(Request $request): bool
    {
        return app()->environment('local') && config('b4.local_student_preview', false)
            && in_array($request->server('REMOTE_ADDR'), ['127.0.0.1', '::1'], true)
            && $request->user()?->role === 'siswa';
    }

    public function admission(StudyTracer $row): array
    {
        return ['id' => $row->id, 'nama_lengkap' => $row->siswa->nama_lengkap,
            'tahun_lulus' => $row->siswa->tahun_lulus,
            'perguruan_tinggi' => ['nama_pt' => $row->perguruanTinggi?->nama_pt, 'jenis_pt' => $row->perguruanTinggi?->jenis_pt],
            'strata_pendidikan' => $row->strata_pendidikan,
            'program_studi' => $row->program_studi,
            'jalur_masuk' => app(AlumniAdmissionPath::class)->label($row),
            'status_penerimaan' => $row->status_penerimaan,
            'tahun_masuk_pt' => $row->tahun_masuk_pt, 'prestasi' => []];
    }
}
