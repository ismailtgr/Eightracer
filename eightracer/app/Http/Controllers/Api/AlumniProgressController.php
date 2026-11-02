<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AlumniProgress;
use App\Models\Siswa;
use App\Services\School\AlumniService;
use Illuminate\Http\Request;

class AlumniProgressController extends Controller
{
    public function index(Request $r)
    {
        $r->validate(['per_page' => 'sometimes|integer|between:1,100', 'tahun_lulus' => 'nullable|digits:4', 'status_pendidikan' => 'nullable|string|max:50']);
        $q = AlumniProgress::with(['siswa', 'studyTracer.perguruanTinggi', 'alumniProgressBeasiswa.beasiswa']);
        foreach (['tahun_lulus', 'status_pendidikan'] as $key) {
            if ($r->filled($key)) {
                $q->where($key, $r->input($key));
            }
        }

return response()->json(['data' => $q->latest('id')->paginate($r->integer('per_page', 20))]);
    }

    public function showBySiswa(int $siswa)
    {
        $s = Siswa::findOrFail($siswa);

        return response()->json(['data' => $s->alumniProgress()->with('alumniProgressBeasiswa.beasiswa')->orderBy('tanggal_update')->get()]);
    }

    public function store(Request $r, int $siswa, AlumniService $service)
    {
        return response()->json(['data' => $service->saveProgress($r, Siswa::findOrFail($siswa))], 201);
    }

    public function timeline(int $siswa, AlumniService $service)
    {
        $s = Siswa::with('studyTracer.perguruanTinggi')->findOrFail($siswa);

        return response()->json(['data' => $s->studyTracer->map(fn ($t) => ['study_tracer_id' => $t->id, 'events' => $service->timeline($t, true)])]);
    }
}
