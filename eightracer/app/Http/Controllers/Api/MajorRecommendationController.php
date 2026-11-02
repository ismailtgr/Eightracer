<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BobotMapelKlaster;
use App\Models\KlasterProdi;
use App\Models\RekomendasiProdi;
use App\Models\Siswa;
use App\Services\School\MajorRecommendation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MajorRecommendationController extends Controller
{
    public function clusters()
    {
        return response()->json(KlasterProdi::with('bobotMapelKlaster')->paginate(20));
    }

    public function saveCluster(Request $r, ?int $id = null)
    {
        $d = $r->validate(['nama_klaster' => 'required|string|max:255', 'is_active' => 'sometimes|boolean', 'deskripsi' => 'nullable|string|max:3000', 'kode_riasec_relevan' => 'nullable|string|regex:/^[RIASEC]{1,6}$/', 'kecerdasan_relevan' => 'nullable|string|max:255',
            'bobot_komponen' => 'required|array:nilai,riasec,multiple_intelligence', 'bobot_komponen.nilai' => 'required|numeric|between:0,100', 'bobot_komponen.riasec' => 'required|numeric|between:0,100', 'bobot_komponen.multiple_intelligence' => 'required|numeric|between:0,100',
            'mapel' => 'present|array|max:100', 'mapel.*' => 'required|array:mata_pelajaran_id,bobot_persentase', 'mapel.*.mata_pelajaran_id' => 'required|integer|distinct|exists:mata_pelajaran,id', 'mapel.*.bobot_persentase' => 'required|numeric|gt:0|max:100']);
        abort_unless(abs(array_sum($d['bobot_komponen']) - 100) < 0.001, 422);
        if ($d['bobot_komponen']['nilai'] > 0) {
            abort_unless(count($d['mapel']) && abs(array_sum(array_column($d['mapel'], 'bobot_persentase')) - 100) < 0.001, 422);
        }
        if ($d['bobot_komponen']['riasec'] > 0) {
            abort_unless(! empty($d['kode_riasec_relevan']), 422);
        }if ($d['bobot_komponen']['multiple_intelligence'] > 0) {
            abort_unless(! empty($d['kecerdasan_relevan']), 422);
        }
        $c = DB::transaction(function () use ($d, $id) {
            $mapel = $d['mapel'];
            unset($d['mapel']);
            $c = $id ? KlasterProdi::lockForUpdate()->findOrFail($id) : new KlasterProdi;
            $c->forceFill($d)->save();
            $c->bobotMapelKlaster()->delete();
            foreach ($mapel as $row) {
                BobotMapelKlaster::create([...$row, 'klaster_id' => $c->id]);
            }

return $c->load('bobotMapelKlaster');
        });

        return response()->json(['data' => $c], $id ? 200 : 201);
    }

    public function archive(int $id)
    {
        KlasterProdi::findOrFail($id)->update(['is_active' => false]);

        return response()->json(['message' => 'Klaster diarsipkan.']);
    }

    public function generate(Request $r, Siswa $siswa, MajorRecommendation $service)
    {
        $d = $r->validate(['semester' => 'required|integer|between:1,5']);

        return response()->json(['data' => $service->generate($siswa, $d['semester'])], 201);
    }

    public function index()
    {
        return response()->json(RekomendasiProdi::with('klaster')->latest('id')->paginate(30));
    }

    public function review(Request $r, string $batch, MajorRecommendation $service)
    {
        $d = $r->validate(['status_review' => 'required|in:disetujui,ditolak', 'catatan_admin' => 'nullable|string|max:2000']);
        $rows = DB::transaction(function () use ($r, $batch, $service, $d) {
            $rows = RekomendasiProdi::where('batch_id', $batch)->lockForUpdate()->get();
            abort_if($rows->isEmpty(), 404);
            abort_unless($rows->every(fn ($row) => $row->status_review === 'pending'), 409);
            if ($d['status_review'] === 'disetujui') {
                [,,,,$fingerprint] = $service->sources($rows->first()->siswa, (int) $rows->first()->semester);
                abort_unless($rows->every(fn ($row) => hash_equals((string) $row->source_fingerprint, $fingerprint)), 409);
            }
            foreach ($rows as $row) {
                $row->update([...$d, 'reviewed_by' => $r->user()->id, 'reviewed_at' => now()]);
            }

return $rows;
        });

        return response()->json(['data' => $rows]);
    }

    public function mine(Request $r)
    {
        $s = $r->user()->siswa;
        abort_unless($s, 404);
        $batch = RekomendasiProdi::where('siswa_id', $s->id)->where('status_review', 'disetujui')->latest('id')->value('batch_id');
        $rows = $batch ? RekomendasiProdi::where('siswa_id', $s->id)->where('batch_id', $batch)->where('status_review', 'disetujui')->orderByDesc('persentase_kesesuaian')->get() : collect();

        return response()->json(['data' => $rows->map(fn ($row) => ['id' => $row->id, 'klaster' => $row->criteria_snapshot['nama_klaster'] ?? null, 'skor_kecocokan' => $row->persentase_kesesuaian, 'alasan' => $row->alasan_rekomendasi, 'komponen' => $row->detail_penilaian, 'tanggal' => $row->tanggal_rekomendasi])]);
    }
}
