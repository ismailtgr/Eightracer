<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RekomendasiBeasiswa;
use App\Models\Siswa;
use App\Services\Auth\SchoolAccess;
use App\Services\Beasiswa\ScholarshipRecommendationService;
use App\Services\Beasiswa\RecommendationEditor;
use App\Services\School\Analytics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RekomendasiBeasiswaController extends Controller
{
    private function admin(Request $request): void
    {
        abort_unless(SchoolAccess::isStaff($request->user()), 403);
    }

    public function generate(Request $request, Siswa $siswa, ScholarshipRecommendationService $service)
    {
        $this->admin($request);

        $input = $request->validate(['target_jenjang' => 'nullable|in:SMA,Kuliah', 'siswa_id' => 'prohibited', 'status_review' => 'prohibited']);

        $draft = $service->generate($siswa, $input['target_jenjang'] ?? null);

        return response()->json([
            'message' => 'Hasil rekomendasi berhasil diperbarui. Siswa mengakses hasil perhitungan secara otomatis.',
            'data' => $draft,
            'meta' => ['generated_at' => now()->toIso8601String(), 'automatic' => true, 'requires_review' => false],
        ], 201);
    }

    public function save(Request $request, Siswa $siswa, ScholarshipRecommendationService $service)
    {
        $this->admin($request);
        abort_if(array_diff(array_keys($request->all()), ['target_jenjang']), 422);
        $input = $request->validate(['target_jenjang'=>'nullable|in:SMA,Kuliah']);
        $record = $service->saveCurrent($siswa, $input['target_jenjang'] ?? null);
        return response()->json([
            'message'=>'Rekomendasi berhasil disimpan. Catatan Guru BK bersifat opsional.',
            'data'=>$record,
            'meta'=>['automatic'=>true,'requires_review'=>false,'saved'=>true],
        ]);
    }

    public function preview(Request $request, Siswa $siswa, ScholarshipRecommendationService $service)
    {
        $this->admin($request);
        $input = $request->validate(['target_jenjang' => 'nullable|in:SMA,Kuliah', 'siswa_id' => 'prohibited', 'status_review' => 'prohibited']);
        $data = $service->preview($siswa, $input['target_jenjang'] ?? null);
        unset($data['source_fingerprint']);

        return response()->json(['data' => $data]);
    }

    public function recipients(Request $request)
    {
        $this->admin($request);
        $request->merge(['penerima_beasiswa' => true]);

        return app(SiswaController::class)->index($request);
    }

    public function index(Request $request)
    {
        $this->admin($request);

        $filters = $request->validate(['siswa_id' => 'nullable|integer|exists:siswa,id', 'target_jenjang' => 'nullable|in:SMA,Kuliah', 'page' => 'sometimes|integer|min:1']);
        $query = RekomendasiBeasiswa::with(['rekomendasiBeasiswaDetail', 'siswa:id,nama_lengkap']);
        foreach (['siswa_id', 'target_jenjang'] as $field) {
            if (isset($filters[$field])) $query->where($field, $filters[$field]);
        }

        return response()->json($query->latest('id')->paginate(15)->appends($filters));
    }

    public function show(Request $request, int $rekomendasi)
    {
        $this->admin($request);
        return response()->json(['data' => RekomendasiBeasiswa::with('rekomendasiBeasiswaDetail')->findOrFail($rekomendasi)]);
    }

    public function review(Request $request, int $rekomendasi, ScholarshipRecommendationService $service)
    {
        $this->admin($request);
        $input = $request->validate(['status_review' => 'required|in:disetujui,ditolak', 'catatan_admin' => 'nullable|string|max:2000', 'siswa_id' => 'sometimes|integer|min:1', 'revision' => 'sometimes|integer|min:1']);
        $result = DB::transaction(function () use ($rekomendasi, $input, $request, $service) {
            $row = RekomendasiBeasiswa::lockForUpdate()->findOrFail($rekomendasi);
            abort_if(isset($input['siswa_id']) && (int) $input['siswa_id'] !== (int) $row->siswa_id, 404);
            Siswa::whereKey($row->siswa_id)->lockForUpdate()->firstOrFail();
            app(RecommendationEditor::class)->assertLatest($row);
            abort_if(isset($input['revision']) && (int) $input['revision'] !== (int) $row->revision, 409, 'Rekomendasi berubah. Muat ulang sebelum menyimpan.');
            abort_unless($row->status_review === 'pending', 409, 'Rekomendasi sudah ditinjau. Buat draft baru untuk revisi.');
            if ($input['status_review'] === 'disetujui') {
                [, , $fingerprint] = $service->sources($row->siswa, $row->target_jenjang);
                abort_unless($row->source_fingerprint && hash_equals($row->source_fingerprint, $fingerprint), 409, 'Data sumber berubah. Generate ulang sebelum publikasi.');
                $details = $row->rekomendasiBeasiswaDetail->where('is_hidden', false);
                abort_if($details->isEmpty() || $details->contains(fn ($d) => ! ($d->evaluasi_kriteria['lengkap'] ?? false)), 422, 'Data kriteria belum lengkap; rekomendasi tidak dapat dipublikasikan.');
                abort_unless($details->contains('is_recommended', true), 422, 'Belum ada beasiswa yang memenuhi seluruh persyaratan.');
            }
            unset($input['siswa_id'], $input['revision']);
            $row->revision = (int) $row->revision + 1;
            $row->update($input + ['reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);

            return $row->fresh(['rekomendasiBeasiswaDetail']);
        });

        return response()->json(['data' => $result]);
    }


    public function updateDetail(Request $request, int $rekomendasi, int $detail, RecommendationEditor $editor)
    {
        $this->admin($request);
        abort_if(array_diff(array_keys($request->all()), ['siswa_id', 'revision', 'alasan_rekomendasi', 'waktu_disarankan', 'is_hidden']), 422);
        $input = $request->validate(['siswa_id' => 'required|integer|min:1', 'revision' => 'required|integer|min:1',
            'alasan_rekomendasi' => 'sometimes|required|string|max:1500', 'waktu_disarankan' => 'sometimes|nullable|string|max:180',
            'is_hidden' => 'sometimes|required|boolean']);
        abort_unless(array_intersect(array_keys($input), ['alasan_rekomendasi', 'waktu_disarankan', 'is_hidden']), 422);
        return response()->json(['data' => $editor->change($rekomendasi, $detail, $input)]);
    }

    public function deleteDetail(Request $request, int $rekomendasi, int $detail, RecommendationEditor $editor)
    {
        $this->admin($request);
        abort_if(array_diff(array_keys($request->all()), ['siswa_id', 'revision']), 422);
        $input = $request->validate(['siswa_id' => 'required|integer|min:1', 'revision' => 'required|integer|min:1']);
        return response()->json(['data' => $editor->change($rekomendasi, $detail, $input, true)]);
    }

    public function mine(Request $request)
    {
        abort_unless($request->user()->role === 'siswa', 403);
        $input = $request->validate(['target_jenjang' => 'nullable|in:SMA,Kuliah', 'siswa_id' => 'prohibited']);
        $student = $request->user()->siswa()->first();
        abort_unless($student, 404, 'Akun belum terhubung dengan data siswa.');
        return response()->json(app(\App\Services\Beasiswa\AutomaticScholarshipRecommendations::class)
            ->forStudent($student, $input['target_jenjang'] ?? null));
    }
}
