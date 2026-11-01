<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RekomendasiBeasiswa;
use App\Models\Siswa;
use App\Services\Beasiswa\ScholarshipRecommendationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RekomendasiBeasiswaController extends Controller
{
    private function admin(Request $request): void
    {
        abort_unless($request->user()->role === 'administrator', 403);
    }

    public function generate(Request $request, Siswa $siswa, ScholarshipRecommendationService $service)
    {
        $this->admin($request);

        return response()->json(['data' => $service->generate($siswa)], 201);
    }

    public function index(Request $request)
    {
        $this->admin($request);

        return response()->json(RekomendasiBeasiswa::with(['rekomendasiBeasiswaDetail', 'siswa:id,nama_lengkap'])->latest('id')->paginate(15));
    }

    public function review(Request $request, int $rekomendasi, ScholarshipRecommendationService $service)
    {
        $this->admin($request);
        $input = $request->validate(['status_review' => 'required|in:disetujui,ditolak', 'catatan_admin' => 'nullable|string|max:2000']);
        $result = DB::transaction(function () use ($rekomendasi, $input, $request, $service) {
            $row = RekomendasiBeasiswa::lockForUpdate()->findOrFail($rekomendasi);
            abort_unless($row->status_review === 'pending', 409, 'Rekomendasi sudah ditinjau. Buat draft baru untuk revisi.');
            if ($input['status_review'] === 'disetujui') {
                [, , $fingerprint] = $service->sources($row->siswa);
                abort_unless($row->source_fingerprint && hash_equals($row->source_fingerprint, $fingerprint), 409, 'Data sumber berubah. Generate ulang sebelum publikasi.');
                $details = $row->rekomendasiBeasiswaDetail;
                abort_if($details->isEmpty() || $details->contains(fn ($d) => ! ($d->evaluasi_kriteria['lengkap'] ?? false)), 422, 'Data kriteria belum lengkap; rekomendasi tidak dapat dipublikasikan.');
                abort_unless($details->contains('is_recommended', true), 422, 'Belum ada beasiswa yang memenuhi seluruh persyaratan.');
            }
            $row->update($input + ['reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);

            return $row->fresh();
        });

        return response()->json(['data' => $result]);
    }

    public function mine(Request $request)
    {
        abort_unless($request->user()->role === 'siswa', 403);
        $siswa = $request->user()->siswa;
        abort_unless($siswa, 404, 'Akun belum terhubung dengan data siswa.');
        $rows = RekomendasiBeasiswa::where('siswa_id', $siswa->id)->where('status_review', 'disetujui')
            ->with('rekomendasiBeasiswaDetail')->latest('id')->paginate(15);
        // Explicit projection: never serialize student relations, source hashes, or private admin notes.
        $rows->through(fn ($row) => [
            'id' => $row->id, 'tanggal_rekomendasi' => $row->tanggal_rekomendasi, 'status' => 'Terpublikasi',
            'beasiswa' => $row->rekomendasiBeasiswaDetail->map(fn ($detail) => [
                'beasiswa_id' => $detail->beasiswa_id, 'informasi' => $detail->beasiswa_snapshot,
                'skor_kecocokan' => $detail->skor_kecocokan, 'memenuhi_syarat' => $detail->is_recommended,
                'checklist' => $detail->evaluasi_kriteria['checklist'] ?? [],
                'alasan' => $detail->alasan_rekomendasi, 'persiapan' => $detail->persiapan,
            ]),
        ]);

        return response()->json($rows);
    }
}
