<?php

namespace App\Services\Beasiswa;

use App\Models\RekomendasiBeasiswa;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecommendationEditor
{
    public function change(int $batch, int $detail, array $input, bool $delete = false): RekomendasiBeasiswa
    {
        return DB::transaction(function () use ($batch, $detail, $input, $delete) {
            $row = RekomendasiBeasiswa::lockForUpdate()->findOrFail($batch);
            abort_unless((int) $row->siswa_id === (int) $input['siswa_id'], 404);
            \App\Models\Siswa::whereKey($row->siswa_id)->lockForUpdate()->firstOrFail();
            $this->assertLatest($row);
            abort_unless((int) $row->revision === (int) $input['revision'], 409, 'Rekomendasi berubah. Muat ulang sebelum menyimpan.');
            $item = $row->rekomendasiBeasiswaDetail()->lockForUpdate()->findOrFail($detail);
            if ($delete) {
                // Retain the original school recommendation for audit, but exclude it from all feeds.
                $item->delete();
            } else {
                $changes = array_intersect_key($input, array_flip(['alasan_rekomendasi', 'waktu_disarankan', 'is_hidden']));
                foreach (['alasan_rekomendasi', 'waktu_disarankan'] as $field) {
                    if (! array_key_exists($field, $changes)) continue;
                    $changes[$field] = $changes[$field] === null ? null : trim(strip_tags($changes[$field]));
                    if ($field === 'alasan_rekomendasi' && empty($changes[$field])) {
                        throw ValidationException::withMessages([$field => 'Alasan kecocokan wajib diisi.']);
                    }
                    if ($changes[$field] === '') $changes[$field] = null;
                }
                $item->update($changes);
            }
            // Any editorial change withdraws publication until the school reviews it again.
            $row->forceFill(['status_review' => 'pending', 'reviewed_by' => null, 'reviewed_at' => null,
                'revision' => (int) $row->revision + 1])->save();

            return $row->fresh('rekomendasiBeasiswaDetail');
        });
    }

    public function assertLatest(RekomendasiBeasiswa $row): void
    {
        $latest = RekomendasiBeasiswa::where('siswa_id', $row->siswa_id)->where('target_jenjang', $row->target_jenjang)->max('id');
        abort_unless((int) $latest === (int) $row->id, 409, 'Ada draft lebih baru. Muat ulang rekomendasi.');
    }
}
