<?php

namespace App\Services\School;

use App\Models\Siswa;

class StudentProfile
{
    public const RELATIONS = ['nilaiRapor', 'orangTua', 'riwayatKelas.kelas', 'alumniProgress.studyTracer.perguruanTinggi', 'rekapPresensi', 'prestasiMurid', 'riwayatOrganisasi', 'catatanKarakter', 'bantuanSiswa'];

    public function build(Siswa $s): array
    {
        $s->loadMissing(self::RELATIONS);
        $grades = $s->nilaiRapor->whereBetween('semester', [1, 5]);
        $parents = $s->orangTua;
        $grade12 = $s->nilaiRapor->whereIn('semester', [5, 6]);
        $class = $s->riwayatKelas->filter(fn ($c) => ! $c->tanggal_selesai && $c->tanggal_mulai <= today())->sortByDesc('tanggal_mulai')->first();
        $progress = $s->alumniProgress->where('is_current', true)->sortByDesc(fn ($p) => $p->tanggal_update?->format('Y-m-d').' '.str_pad((string) $p->id, 20, '0', STR_PAD_LEFT))->first();
        $college = $progress && $progress->studyTracer?->status_penerimaan === 'diterima' && in_array(mb_strtolower((string) $progress->status_pendidikan), ['aktif', 'cuti'], true);
        $achievements = $s->prestasiMurid->where('status_verifikasi', 'terverifikasi');

        return ['jenjang' => $college ? 'Kuliah' : (! $s->tanggal_lulus && ! $s->tahun_lulus && $class && in_array((int) $class->kelas?->tingkat, [10, 11, 12], true) ? 'SMA' : null),
            'rata_rapor_kelas_12' => $grade12->pluck('semester')->unique()->count() === 2 ? $grade12->avg('nilai_akhir') : null,
            'nama_perguruan_tinggi' => $college ? $progress->studyTracer?->perguruanTinggi?->nama_pt : null,
            'strata_pendidikan' => $college ? $progress->studyTracer?->strata_pendidikan : null,
            'status_kuliah' => $progress ? mb_strtolower((string) $progress->status_pendidikan) : null,
            'nilai_tka' => $s->nilai_tka, 'rata_rapor' => $grades->pluck('semester')->unique()->count() === 5 ? $grades->avg('nilai_akhir') : null,
            'penghasilan_orang_tua' => $parents->pluck('jenis_orang_tua')->unique()->count() === 2 && $parents->every(fn ($p) => $p->penghasilan !== null) ? $parents->sum('penghasilan') : null,
            'persentase_kehadiran' => $s->rekapPresensi->sortByDesc('semester')->first()?->persentase_kehadiran,
            'jumlah_prestasi' => $s->prestasi_dikonfirmasi || $achievements->isNotEmpty() ? $achievements->count() : null,
            'jumlah_organisasi' => $s->organisasi_dikonfirmasi || $s->riwayatOrganisasi->isNotEmpty() ? $s->riwayatOrganisasi->count() : null,
            'poin_karakter' => $s->karakter_dikonfirmasi || $s->catatanKarakter->isNotEmpty() ? $s->catatanKarakter->sum('poin_disiplin') : null,
            'ipk' => $college ? $progress->ipk_terkini : null, 'semester_kuliah' => $college ? $progress->semester : null,
            // Only a current named program can supply status_eligible for scholarship criteria.
            'status_eligible' => null];
    }
}
