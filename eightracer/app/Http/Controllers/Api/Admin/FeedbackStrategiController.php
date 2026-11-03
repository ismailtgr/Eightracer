<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CatatanBimbingan;
use App\Models\Guru;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeedbackStrategiController extends Controller
{

    public function index(Request $request): JsonResponse
    {
        $search       = trim((string) $request->query('search', ''));
        $kelas        = trim((string) $request->query('kelas', ''));
        $statusFilter = trim((string) $request->query('status', ''));
        $siswaIdPilih = $request->query('siswa_id');

        $query = Siswa::with([
            'riwayatKelas.kelas',
            'minatProdi',
            'citaCita',
            'catatanBimbingan.guruBk',
            'rekomendasiMapel' => fn ($q) => $q->with('mataPelajaran')->orderByDesc('skor_rekomendasi'),
        ]);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('nisn', 'like', "%{$search}%")
                  ->orWhere('nis', 'like', "%{$search}%");
            });
        }

        if ($kelas !== '') {
            $query->whereHas('riwayatKelas.kelas', fn ($q) => $q->where('nama_kelas', $kelas));
        }

        $semuaSiswa = $query->orderBy('nama_lengkap')->get();

        $daftarFormatted = $semuaSiswa->map(fn (Siswa $s) => $this->formatKartuPesertaDidik($s))->values();

        if (in_array($statusFilter, ['belum_ditinjau', 'tersimpan', 'dilewati'], true)) {
            $daftarFormatted = $daftarFormatted->where('status_feedback', $statusFilter)->values();
        }

        $pesertaAktif = null;
        if ($siswaIdPilih) {
            $pesertaAktif = $daftarFormatted->firstWhere('siswa_id', (int) $siswaIdPilih);
        }
        if (!$pesertaAktif) {
            $pesertaAktif = $daftarFormatted->firstWhere('status_feedback', 'belum_ditinjau')
                ?? $daftarFormatted->first();
        }

        if ($pesertaAktif) {
            $idxAktif = $daftarFormatted->search(fn ($item) => $item['siswa_id'] === $pesertaAktif['siswa_id']);
            $pesertaAktif['siswa_sebelumnya_id'] = ($idxAktif !== false && $idxAktif > 0)
                ? $daftarFormatted[$idxAktif - 1]['siswa_id']
                : null;
            $pesertaAktif['siswa_berikutnya_id'] = ($idxAktif !== false && isset($daftarFormatted[$idxAktif + 1]))
                ? $daftarFormatted[$idxAktif + 1]['siswa_id']
                : null;
        }

        $totalSiswa    = $semuaSiswa->count();
        $jmlTersimpan  = $semuaSiswa->filter(fn (Siswa $s) => $s->catatanBimbingan->whereNull('mata_pelajaran_id')->last()?->status === 'tersimpan')->count();
        $jmlDilewati   = $semuaSiswa->filter(fn (Siswa $s) => $s->catatanBimbingan->whereNull('mata_pelajaran_id')->last()?->status === 'dilewati')->count();
        $jmlBelum      = max(0, $totalSiswa - $jmlTersimpan - $jmlDilewati);

        $direktoriGuru = Guru::orderBy('nama_lengkap')->get()->map(fn (Guru $g) => [
            'id'              => $g->id,
            'inisial'         => $g->inisial,
            'nama_lengkap'    => $g->nama_lengkap,
            'nip'             => $g->nip,
            'label_nip'       => 'NIP: ' . $g->nip,
            'jabatan'         => $g->jabatan ?? 'Guru Bimbingan dan Konseling',
            'foto_profil_url' => $g->foto_profil_url,
        ])->values();

        return response()->json([
            'judul'    => 'Feedback Strategi Belajar',
            'subjudul' => 'Menyediakan catatan evaluasi atau saran strategi belajar tambahan secara khusus apabila diperlukan berdasarkan perkembangan nilai belajar siswa',
            'ringkasan' => [
                'total_siswa'           => $totalSiswa,
                'sudah_diberi_feedback' => $jmlTersimpan,
                'dilewati'              => $jmlDilewati,
                'belum_ditinjau'        => $jmlBelum,
            ],
            'peserta_didik_aktif'  => $pesertaAktif,
            'daftar_peserta_didik' => $daftarFormatted,
            'direktori_guru_bk'    => $direktoriGuru,
        ]);
    }

    public function show(Siswa $siswa): JsonResponse
    {
        $siswa->load([
            'riwayatKelas.kelas',
            'minatProdi',
            'citaCita',
            'nilaiRapor' => fn ($q) => $q->where('semester', 1)->with('mataPelajaran'),
            'catatanBimbingan.guruBk',
            'catatanBimbingan.mataPelajaran',
            'rekomendasiMapel' => fn ($q) => $q->with('mataPelajaran')->orderByDesc('skor_rekomendasi'),
        ]);

        return response()->json([
            'peserta_didik' => $this->formatKartuPesertaDidik($siswa),
            'perkembangan_nilai_semester_1' => $siswa->nilaiRapor->map(fn ($n) => [
                'mata_pelajaran' => $n->mataPelajaran?->nama_mapel,
                'nilai_akhir'    => (float) $n->nilai_akhir,
            ])->values(),
            'riwayat_catatan_bimbingan' => $siswa->catatanBimbingan->map(fn (CatatanBimbingan $c) => [
                'id'                     => $c->id,
                'mata_pelajaran'         => $c->mataPelajaran?->nama_mapel ?? 'Umum / Keseluruhan',
                'catatan'                => $c->konten,
                'strategi_belajar'       => $c->strategi_belajar,
                'alokasi_jam_per_minggu' => $c->alokasi_jam_per_minggu,
                'status'                 => $c->status,
                'guru_bk'                => $c->guruBk?->nama_lengkap,
                'updated_at'             => $c->updated_at?->format('Y-m-d H:i:s'),
            ])->values(),
        ]);
    }

    public function simpanFeedback(Request $request, Siswa $siswa): JsonResponse
    {
        $validated = $request->validate([
            'catatan'                => 'nullable|string',
            'konten'                 => 'nullable|string',
            'strategi_belajar'       => 'nullable|string',
            'alokasi_jam_per_minggu' => 'nullable|integer|min:1|max:40',
            'mata_pelajaran_id'      => 'nullable|integer|exists:mata_pelajaran,id',
            'guru_bk_id'             => 'nullable|integer|exists:guru,id',
        ]);

        $isiCatatan = $validated['catatan'] ?? $validated['konten'] ?? null;
        $guruId     = $validated['guru_bk_id'] ?? $this->ambilGuruBkId($request);
        $mapelId    = $validated['mata_pelajaran_id'] ?? null;

        $catatan = DB::transaction(function () use ($siswa, $isiCatatan, $validated, $guruId, $mapelId) {
            $rec = CatatanBimbingan::updateOrCreate(
                [
                    'siswa_id'          => $siswa->id,
                    'mata_pelajaran_id' => $mapelId,
                ],
                [
                    'guru_bk_id'             => $guruId,
                    'konten'                 => $isiCatatan,
                    'strategi_belajar'       => $validated['strategi_belajar'] ?? null,
                    'alokasi_jam_per_minggu' => $validated['alokasi_jam_per_minggu'] ?? null,
                    'status'                 => 'tersimpan',
                ]
            );

            if ($mapelId === null && $isiCatatan !== null) {
                $siswa->update(['catatan' => $isiCatatan]);
            }

            if ($mapelId !== null && $isiCatatan !== null) {
                $siswa->rekomendasiMapel()
                    ->where('mata_pelajaran_id', $mapelId)
                    ->update(['catatan_evaluasi_akademik' => $isiCatatan]);
            }

            return $rec->fresh(['guruBk', 'mataPelajaran']);
        });

        $siswa->load(['riwayatKelas.kelas', 'minatProdi', 'citaCita', 'catatanBimbingan.guruBk', 'rekomendasiMapel.mataPelajaran']);

        return response()->json([
            'message'                  => "Feedback strategi belajar untuk {$siswa->nama_lengkap} berhasil disimpan.",
            'data'                     => $this->formatKartuPesertaDidik($siswa),
            'peserta_didik_berikutnya' => $this->cariPesertaDidikBerikutnya($siswa->id),
        ]);
    }

    public function lewati(Request $request, Siswa $siswa): JsonResponse
    {
        $guruId = $request->input('guru_bk_id') ?? $this->ambilGuruBkId($request);

        DB::transaction(function () use ($siswa, $guruId) {
            $existing = CatatanBimbingan::where('siswa_id', $siswa->id)
                ->whereNull('mata_pelajaran_id')
                ->first();

            if ($existing) {
                $existing->update(['status' => 'dilewati']);
            } else {
                CatatanBimbingan::create([
                    'siswa_id'          => $siswa->id,
                    'guru_bk_id'        => $guruId,
                    'mata_pelajaran_id' => null,
                    'konten'            => null,
                    'status'            => 'dilewati',
                ]);
            }
        });

        $siswa->load(['riwayatKelas.kelas', 'minatProdi', 'citaCita', 'catatanBimbingan.guruBk', 'rekomendasiMapel.mataPelajaran']);

        return response()->json([
            'message'                  => "Pemberian feedback untuk {$siswa->nama_lengkap} dilewati.",
            'data'                     => $this->formatKartuPesertaDidik($siswa),
            'peserta_didik_berikutnya' => $this->cariPesertaDidikBerikutnya($siswa->id),
        ]);
    }

    public function destroy(CatatanBimbingan $catatanBimbingan): JsonResponse
    {
        $catatanBimbingan->delete();

        return response()->json([
            'message' => 'Catatan feedback strategi belajar berhasil dihapus.',
        ]);
    }

    private function formatKartuPesertaDidik(Siswa $s): array
    {
        $peminatan = $s->minatProdi->first()?->nama_prodi
            ?? $s->citaCita->first()?->nama_cita_cita
            ?? 'Belum ditentukan';

        $catatanUmum = $s->catatanBimbingan
            ->whereNull('mata_pelajaran_id')
            ->last();

        $statusFeedback = $catatanUmum?->status ?? 'belum_ditinjau';
        $isiCatatan     = $catatanUmum?->konten ?? $s->catatan;

        return [
            'siswa_id'             => $s->id,
            'label_header'         => 'PESERTA DIDIK',
            'nama_lengkap'         => $s->nama_lengkap,
            'nisn'                 => $s->nisn,
            'nis'                  => $s->nis,
            'kelas'                => $s->riwayatKelas->last()?->kelas?->nama_kelas ?? '-',
            'peminatan'            => $peminatan,
            'label_peminatan'      => "Peminatan: {$peminatan}",
            'paket_4_mapel'        => $s->rekomendasiMapel->take(4)->pluck('mataPelajaran.nama_mapel')->values(),
            'catatan_opsional'     => $isiCatatan,
            'strategi_belajar'     => $catatanUmum?->strategi_belajar,
            'alokasi_jam_mingguan' => $catatanUmum?->alokasi_jam_per_minggu,
            'status_feedback'      => $statusFeedback, // "belum_ditinjau" | "tersimpan" | "dilewati"
            'ditinjau_oleh'        => $catatanUmum?->guruBk?->nama_lengkap,
            'updated_at'           => $catatanUmum?->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function cariPesertaDidikBerikutnya(int $currentSiswaId): ?array
    {
        $berikutnya = Siswa::with([
            'riwayatKelas.kelas',
            'minatProdi',
            'citaCita',
            'catatanBimbingan.guruBk',
            'rekomendasiMapel' => fn ($q) => $q->with('mataPelajaran')->orderByDesc('skor_rekomendasi'),
        ])
            ->where('id', '!=', $currentSiswaId)
            ->whereDoesntHave('catatanBimbingan', fn ($q) => $q->whereNull('mata_pelajaran_id'))
            ->orderBy('nama_lengkap')
            ->first();

        return $berikutnya ? $this->formatKartuPesertaDidik($berikutnya) : null;
    }

    private function ambilGuruBkId(Request $request): int
    {
        $user = $request->user();
        if ($user) {
            $guruLogin = Guru::where('user_id', $user->id)->first();
            if ($guruLogin) {
                return $guruLogin->id;
            }
        }

        $guru = Guru::first();
        if ($guru) {
            return $guru->id;
        }

        $userDefault = User::firstOrCreate(
            ['username' => '196805121994032008'],
            ['password' => '196805121994032008', 'role' => 'guru']
        );

        return Guru::create([
            'user_id'      => $userDefault->id,
            'nip'          => '196805121994032008',
            'nama_lengkap' => 'Dra. Endang Sulastri',
            'jabatan'      => 'Koordinator Bimbingan Konseling',
        ])->id;
    }
}