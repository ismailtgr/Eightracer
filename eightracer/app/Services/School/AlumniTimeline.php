<?php

namespace App\Services\School;

use App\Models\StudyTracer;

class AlumniTimeline
{
    public function completeIds(): array
    {
        return StudyTracer::where('status_penerimaan', 'diterima')
            ->whereHas('siswa', fn ($q) => $q->whereNotNull('tanggal_masuk_sekolah')
                ->where(fn ($s) => $s->whereNotNull('tanggal_lulus')->orWhereNotNull('tahun_lulus')))
            ->where(fn ($q) => $q->whereNotNull('tanggal_masuk_pt')->orWhereNotNull('tahun_masuk_pt'))
            ->whereHas('latestCurrentProgress', fn ($q) => $q->whereNotNull('ipk_terkini')
                ->whereNotNull('nominal_ukt')->whereNotNull('penerima_kipk'))
            ->with(['siswa', 'perguruanTinggi', 'latestCurrentProgress'])->get()
            ->filter(fn ($record) => $this->isComplete($record))->pluck('id')->all();
    }

    public function isComplete(StudyTracer $record): bool
    {
        $s = $record->siswa;
        $p = $record->latestCurrentProgress;
        if ($record->status_penerimaan !== 'diterima' || !$s || !$p || !$p->is_current
            || (int) $p->siswa_id !== (int) $s->id
            || !filled($s->nama_lengkap) || !filled($record->perguruanTinggi?->nama_pt)
            || !filled($record->program_studi) || !filled($record->strata_pendidikan)
            || !app(AlumniAdmissionPath::class)->label($record)) return false;

        $today = now('Asia/Jakarta')->toDateString();
        $schoolStart = $s->tanggal_masuk_sekolah?->toDateString();
        $graduation = $s->tanggal_lulus?->toDateString();
        $graduationYear = $graduation ? (int) substr($graduation, 0, 4) : (int) $s->tahun_lulus;
        $college = $record->tanggal_masuk_pt?->toDateString();
        $collegeYear = $college ? (int) substr($college, 0, 4) : (int) $record->tahun_masuk_pt;
        $progressDate = $p->tanggal_update?->toDateString();
        $currentYear = (int) substr($today, 0, 4);
        if (!$schoolStart || !$progressDate || $schoolStart > $today || $progressDate > $today
            || $graduationYear < 1900 || $graduationYear > $currentYear
            || $collegeYear < $graduationYear || $collegeYear > $currentYear
            || (int) substr($schoolStart, 0, 4) > $graduationYear
            || (int) substr($progressDate, 0, 4) < $collegeYear
            || $progressDate < $schoolStart || ($graduation && ($graduation > $today || $schoolStart > $graduation || $progressDate < $graduation))
            || ($college && ($college > $today || ($graduation && $college < $graduation) || $progressDate < $college))
            || ($graduation && $s->tahun_lulus && (int) $s->tahun_lulus !== $graduationYear)
            || ($college && $record->tahun_masuk_pt && (int) $record->tahun_masuk_pt !== $collegeYear)
            || ($p->tahun_lulus && (int) $p->tahun_lulus !== $graduationYear)) return false;

        return filled($p->periode) && $p->semester >= 1 && $p->semester <= 40
            && in_array($p->status_pendidikan, ['aktif', 'cuti', 'lulus', 'nonaktif'], true)
            && $p->ipk_terkini !== null && is_numeric($p->ipk_terkini) && $p->ipk_terkini >= 0 && $p->ipk_terkini <= 4
            && $p->nominal_ukt !== null && is_numeric($p->nominal_ukt) && $p->nominal_ukt >= 0
            && $p->penerima_kipk !== null;
    }

    public function educationEvents(StudyTracer $record): array
    {
        $s = $record->siswa;
        $events = [];
        if ($s?->tanggal_masuk_sekolah) {
            $events[] = ['type' => 'masuk_sekolah', 'label' => 'Masuk SMA',
                'date' => $s->tanggal_masuk_sekolah->toDateString(), 'year' => (int) $s->tanggal_masuk_sekolah->format('Y')];
        }
        if ($s?->tanggal_lulus || $s?->tahun_lulus) {
            $events[] = ['type' => 'lulus_sekolah', 'label' => 'Lulus SMA',
                'date' => $s->tanggal_lulus?->toDateString(), 'year' => $s->tanggal_lulus ? (int) $s->tanggal_lulus->format('Y') : (int) $s->tahun_lulus];
        }
        if ($record->tanggal_masuk_pt || $record->tahun_masuk_pt) {
            $events[] = ['type' => 'masuk_pt', 'label' => 'Masuk Perguruan Tinggi',
                'date' => $record->tanggal_masuk_pt?->toDateString(), 'year' => $record->tanggal_masuk_pt ? (int) $record->tanggal_masuk_pt->format('Y') : (int) $record->tahun_masuk_pt];
        }
        return $events;
    }
}
