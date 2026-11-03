<?php

namespace App\Services\Beasiswa;

use App\Models\AlumniProgress;

class ScholarshipHistoryEvidence
{
    public function forScholarship(int $scholarship): array
    {
        $q = AlumniProgress::where('is_current', true)->whereHas('siswa', fn ($s) => $s->where('izinkan_analitik', true))
            ->whereHas('alumniProgressBeasiswa', fn ($b) => $b->where('beasiswa_id', $scholarship));
        if ((clone $q)->count() > 10000) {
            return ['status' => 'insufficient_data', 'catatan' => 'Ringkasan perlu disiapkan pengelola untuk cakupan ini.'];
        }
        $rows = $q->with(['siswa.nilaiRapor', 'studyTracer', 'alumniProgressBeasiswa' => fn ($b) => $b->where('beasiswa_id', $scholarship)->orderByDesc('id')])->orderByDesc('tanggal_update')->orderByDesc('id')->get()->unique('siswa_id')->where('is_published', true);
        $terminal = $rows->filter(fn ($p) => in_array($p->alumniProgressBeasiswa->first()?->status_beasiswa, ['diterima', 'ditolak'], true));
        $accepted = $terminal->filter(fn ($p) => $p->alumniProgressBeasiswa->first()->status_beasiswa === 'diterima');
        $rejected = $terminal->count() - $accepted->count();
        $minimum = max(5, (int) config('b4.minimum_history_group', 5));
        $result = ['status' => 'insufficient_data', 'jumlah_diterima' => null, 'jumlah_ditolak' => null, 'jumlah_hasil_terminal' => null, 'proporsi_diterima_tercatat' => null,
            'rentang_rapor_penerima' => null, 'jalur_penerima' => null,
            'catatan' => 'Hanya catatan terbaru alumni yang memberi izin agregasi dan publikasi. Proporsi kasus tercatat bukan probabilitas siswa atau tingkat keberhasilan seluruh pelamar; data belum tentu mencakup semua aplikasi.'];
        if ($accepted->count() >= $minimum && $rejected >= $minimum) {
            $result = [...$result, 'status' => 'available', 'jumlah_diterima' => $accepted->count(), 'jumlah_ditolak' => $rejected, 'jumlah_hasil_terminal' => $terminal->count(), 'proporsi_diterima_tercatat' => round($accepted->count() / $terminal->count(), 4)];
        }
        $grades = $accepted->map(function ($p) {
            $g = $p->siswa->nilaiRapor->whereBetween('semester', [1, 5]);

            return $g->pluck('semester')->unique()->count() === 5 ? $g->avg('nilai_akhir') : null;
        })->filter(fn ($v) => $v !== null);
        if ($grades->count() >= $minimum) {
            $result['rentang_rapor_penerima'] = ['minimum_bucket' => floor($grades->min() / 5) * 5, 'maximum_bucket' => min(100, ceil($grades->max() / 5) * 5), 'jumlah_dengan_rapor_lengkap' => $grades->count(), 'bucket' => 5];
        }
        $paths = $accepted->groupBy(fn ($p) => $p->studyTracer?->jalur_masuk ?? 'Tidak diketahui')->map->count();
        if ($paths->isNotEmpty() && $paths->every(fn ($n) => $n >= $minimum)) {
            $result['jalur_penerima'] = $paths->map(fn ($n, $path) => ['jalur' => $path, 'jumlah' => $n])->values()->all();
        }

        return $result;
    }
}
