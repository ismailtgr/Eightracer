<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Services\School\SchoolRecordWriter;
use Illuminate\Http\Request;

class AssessmentController extends Controller
{
    public function history(Siswa $siswa)
    {
        return response()->json(['data' => ['karakteristik' => $siswa->asesmenKarakteristik()->orderByDesc('tanggal_asesmen')->get(), 'riasec' => $siswa->asesmenRiasec()->orderByDesc('tanggal_asesmen')->get(), 'multiple_intelligence' => $siswa->asesmenMultipleIntelligence()->orderByDesc('tanggal_asesmen')->get()]]);
    }

    public function mine(Request $r)
    {
        $s = $r->user()->siswa;
        abort_unless($s, 404);

        return $this->history($s);
    }

    public function store(Request $r, Siswa $siswa)
    {
        return response()->json(['data' => app(SchoolRecordWriter::class)->saveAssessment($r, $siswa)], 201);
    }
}
