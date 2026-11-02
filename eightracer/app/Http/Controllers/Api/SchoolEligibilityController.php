<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProgramEligibilitas;
use App\Models\Siswa;
use App\Services\School\Criteria;
use App\Services\School\SchoolEligibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SchoolEligibilityController extends Controller
{
    public function index()
    {
        return response()->json(ProgramEligibilitas::latest('id')->paginate(20));
    }

    public function save(Request $r, ?int $id = null)
    {
        $d = $r->validate(['nama' => 'required|string|max:255', 'deskripsi' => 'nullable|string|max:2000', 'kriteria' => 'required|array', 'is_active' => 'sometimes|boolean',
            'bobot_prestasi' => 'nullable|array:Sekolah,Kabupaten,Kota,Provinsi,Nasional,Internasional', 'bobot_prestasi.*' => 'numeric|between:0,100', 'metode_prestasi' => 'nullable|in:tertinggi,jumlah_dibatasi_100',
            'bantuan_ids' => 'nullable|array|max:30', 'bantuan_ids.*' => 'integer|distinct|exists:jenis_bantuan,id', 'mode_bantuan' => 'nullable|in:salah_satu,semua']);
        $d['kriteria'] = Criteria::validate($d['kriteria'], false);
        if (collect($d['kriteria'])->contains('tipe_kriteria', 'skor_prestasi')) {
            abort_unless(! empty($d['bobot_prestasi']) && count($d['bobot_prestasi']) === 6 && ! empty($d['metode_prestasi']), 422, 'Isi bobot setiap tingkat dan metode prestasi.');
        }
        if (collect($d['kriteria'])->contains('tipe_kriteria', 'bantuan_sosial_sesuai')) {
            abort_unless(! empty($d['bantuan_ids']) && ! empty($d['mode_bantuan']), 422, 'Pilih jenis dan mode bantuan sosial.');
        }
        $record = DB::transaction(function () use ($id, $d) {
            $p = $id ? ProgramEligibilitas::lockForUpdate()->findOrFail($id) : new ProgramEligibilitas;
            $version = $id ? $p->version + 1 : 1;
            $p->fill($d);
            $p->version = $version;
            $p->save();

            return $p;
        });

        return response()->json(['data' => $record], $id ? 200 : 201);
    }

    public function archive(int $id)
    {
        ProgramEligibilitas::findOrFail($id)->update(['is_active' => false]);

        return response()->json(['message' => 'Program diarsipkan.']);
    }

    public function calculate(Request $r, int $id, SchoolEligibility $service)
    {
        $p = ProgramEligibilitas::where('is_active', true)->findOrFail($id);
        $d = $r->validate(['siswa_ids' => 'required|array|min:1|max:100', 'siswa_ids.*' => 'required|integer|distinct|exists:siswa,id']);
        $results = DB::transaction(fn () => collect($d['siswa_ids'])->map(fn ($s) => $service->calculate(Siswa::findOrFail($s), $p)));

        return response()->json(['data' => $results]);
    }

    public function results(Request $r, int $id, SchoolEligibility $service)
    {
        $p = ProgramEligibilitas::findOrFail($id);
        $r->validate(['per_page' => 'sometimes|integer|between:1,100']);

        return response()->json(Siswa::orderBy('id')->paginate($r->integer('per_page', 20))->through(fn ($s) => ['siswa_id' => $s->id, 'nama_lengkap' => $s->nama_lengkap, ...$service->evaluate($s, $p)]));
    }

    public function mine(Request $r, SchoolEligibility $service)
    {
        $s = $r->user()->siswa;
        abort_unless($s, 404);

        return response()->json(['data' => ProgramEligibilitas::where('is_active', true)->get()->map(fn ($p) => $service->studentResult($s,$p))]);
    }
}
