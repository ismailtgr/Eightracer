<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CitaCita;
use App\Models\Hobi;
use App\Models\JenisBantuan;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\PerguruanTinggi;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class ReferenceController extends Controller
{
    private function definition(string $type): array
    {
        $definitions = [
            'kelas' => [Kelas::class, ['tingkat' => 'required|integer|between:10,12', 'nama_kelas' => 'required|string|max:100', 'tahun_ajaran' => 'required|string|regex:/^[0-9]{4}\/[0-9]{4}$/']],
            'mata-pelajaran' => [MataPelajaran::class, ['nama_mapel' => 'required|string|max:255', 'jenis_mapel' => 'required|string|size:1', 'kelompok' => 'nullable|string|max:100', 'tersedia_sebagai_pilihan' => 'sometimes|boolean', 'deskripsi' => 'nullable|string|max:3000']],
            'jenis-bantuan' => [JenisBantuan::class, ['nama_bantuan' => 'required|string|max:255', 'keterangan' => 'nullable|string|max:2000']],
            'hobi' => [Hobi::class, ['nama_hobi' => 'required|string|max:255']],
            'cita-cita' => [CitaCita::class, ['nama_cita_cita' => 'required|string|max:255']],
            'perguruan-tinggi' => [PerguruanTinggi::class, ['nama_pt' => 'required|string|max:255', 'jenis_pt' => 'required|in:Negeri,Swasta,Kedinasan,Luar Negeri', 'kode_pt' => 'nullable|string|max:50', 'lokasi' => 'nullable|array:provinsi,kota,negara', 'lokasi.*' => 'nullable|string|max:100']],
        ];
        abort_unless(isset($definitions[$type]), 404);

        return $definitions[$type];
    }

    public function index(Request $r, string $type)
    {
        [$model] = $this->definition($type);
        $r->validate(['per_page' => 'sometimes|integer|between:1,100']);

        return response()->json($model::orderBy('id')->paginate($r->integer('per_page', 20)));
    }

    public function store(Request $r, string $type)
    {
        [$model,$rules] = $this->definition($type);
        $record = new $model;
        $record->forceFill($r->validate($rules))->save();

        return response()->json(['data' => $record], 201);
    }

    public function update(Request $r, string $type, int $id)
    {
        [$model,$rules] = $this->definition($type);
        $record = $model::findOrFail($id);
        $record->forceFill($r->validate($rules))->save();

        return response()->json(['data' => $record]);
    }

    public function destroy(string $type, int $id)
    {
        [$model] = $this->definition($type);
        try {
            $model::findOrFail($id)->delete();
        } catch (QueryException) {
            abort(409, 'Data masih digunakan.');
        }

return response()->json(['message' => 'Data dihapus.']);
    }
}
