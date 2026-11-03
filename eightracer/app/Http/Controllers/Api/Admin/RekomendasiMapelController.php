<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\MataPelajaran;
use App\Models\RekomendasiMapel;
use App\Models\Siswa;
use App\Services\RekomendasiMapelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RekomendasiMapelController extends Controller
{
    public function __construct(
        private readonly RekomendasiMapelService $rekomendasiService
    ) {}

    public function dashboard(): JsonResponse
    {
        $totalSiswa = Siswa::count();

        $siswaBelumAdaRek = Siswa::doesntHave('rekomendasiMapel')->get();
        foreach ($siswaBelumAdaRek as $s) {
            $this->rekomendasiService->generate($s);
        }

        $drafUsulan = Siswa::whereHas('rekomendasiMapel', fn ($q) => $q->where('status', 'draft'))
            ->whereDoesntHave('rekomendasiMapel', fn ($q) => $q->whereIn('status', ['dikunci', 'diterbitkan']))
            ->count();

        $skTerbitTerkunci = Siswa::whereHas('rekomendasiMapel', fn ($q) => $q->whereIn('status', ['dikunci', 'diterbitkan']))
            ->count();

        $semuaSiswa = Siswa::with([
            'rekomendasiMapel' => fn ($q) => $q->orderByDesc('skor_rekomendasi'),
        ])->get();

        $mapelPilihan = MataPelajaran::where('tersedia_sebagai_pilihan', true)->get();
        $hitungPerMapel = [];
        foreach ($mapelPilihan as $mp) {
            $hitungPerMapel[$mp->id] = 0;
        }

        $jumlahSesuai          = 0;
        $jumlahPerluPenyesuaian = 0;
        $jumlahPerluPeninjauan  = 0;
        $akumulasiKecocokan     = 0.0;
        $siswaTerhitung         = 0;

        foreach ($semuaSiswa as $siswa) {
            $top4 = $siswa->rekomendasiMapel->take(4);
            if ($top4->isEmpty()) {
                continue;
            }

            foreach ($top4 as $rek) {
                if (isset($hitungPerMapel[$rek->mata_pelajaran_id])) {
                    $hitungPerMapel[$rek->mata_pelajaran_id]++;
                }
            }

            $rataSkorPaket = (float) $top4->avg('skor_rekomendasi');
            $akumulasiKecocokan += $rataSkorPaket;
            $siswaTerhitung++;

            if ($rataSkorPaket >= 75) {
                $jumlahSesuai++;
            } elseif ($rataSkorPaket >= 65) {
                $jumlahPerluPenyesuaian++;
            } else {
                $jumlahPerluPeninjauan++;
            }
        }

        $pembagiSiswa = max(1, $siswaTerhitung);

        $distribusiMapel = $mapelPilihan->map(function (MataPelajaran $mp) use ($hitungPerMapel, $pembagiSiswa) {
            $jml    = $hitungPerMapel[$mp->id] ?? 0;
            $persen = round(($jml / $pembagiSiswa) * 100, 1);

            return [
                'mata_pelajaran_id' => $mp->id,
                'nama_mapel'        => $mp->nama_mapel,
                'kelompok'          => $mp->kelompok,
                'jumlah_siswa'      => $jml,
                'persentase'        => $persen,
                'label'             => "{$persen}% ({$jml} Siswa)",
            ];
        })->sortByDesc('jumlah_siswa')->values();

        $tingkatKecocokan = $siswaTerhitung > 0
            ? round($akumulasiKecocokan / $siswaTerhitung, 1)
            : 0.0;

        return response()->json([
            'kartu_statistik' => [
                'total_siswa_terdata'     => $totalSiswa,
                'draf_usulan_rekomendasi' => $drafUsulan,
                'sk_terbit_dan_terkunci'  => $skTerbitTerkunci,
            ],
            'distribusi_mata_pelajaran_peminatan' => [
                'subjudul' => 'Preferensi pilihan siswa Angkatan ' . date('Y') . '/' . ((int) date('Y') + 1),
                'items'    => $distribusiMapel,
            ],
            'analisis_keselarasan_peminatan' => [
                'subjudul'                 => 'Kesesuaian RIASEC dan Nilai Rapor',
                'tingkat_kecocokan_persen' => $tingkatKecocokan,
                'label_tengah'             => "{$tingkatKecocokan}%",
                'rincian'                  => [
                    'sesuai' => [
                        'jumlah_siswa' => $jumlahSesuai,
                        'persentase'   => round(($jumlahSesuai / $pembagiSiswa) * 100, 1),
                        'label'        => "{$jumlahSesuai} Siswa",
                    ],
                    'perlu_penyesuaian' => [
                        'jumlah_siswa' => $jumlahPerluPenyesuaian,
                        'persentase'   => round(($jumlahPerluPenyesuaian / $pembagiSiswa) * 100, 1),
                        'label'        => "{$jumlahPerluPenyesuaian} Siswa",
                    ],
                    'perlu_peninjauan' => [
                        'jumlah_siswa' => $jumlahPerluPeninjauan,
                        'persentase'   => round(($jumlahPerluPeninjauan / $pembagiSiswa) * 100, 1),
                        'label'        => "{$jumlahPerluPeninjauan} Siswa",
                    ],
                ],
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $search  = trim((string) $request->query('search', ''));
        $kelas   = trim((string) $request->query('kelas', ''));
        $status  = trim((string) $request->query('status', ''));
        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));

        $query = Siswa::with([
            'riwayatKelas.kelas',
            'asesmenKarakteristik',
            'minatProdi',
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
            $query->whereHas('riwayatKelas.kelas', function ($q) use ($kelas) {
                $q->where('nama_kelas', $kelas);
            });
        }

        if (in_array($status, ['draft', 'dikunci', 'diterbitkan'], true)) {
            $query->whereHas('rekomendasiMapel', function ($q) use ($status) {
                $q->where('status', $status);
            });
        }

        $paginator = $query->orderBy('nama_lengkap')->paginate($perPage);

        $dataSiswa = collect($paginator->items())->map(function (Siswa $s) {
            $kelasAktif  = $s->riwayatKelas->last()?->kelas?->nama_kelas;
            $karakter    = $s->asesmenKarakteristik->last();
            $rekomendasi = $s->rekomendasiMapel;
            $statusRek   = $rekomendasi->first()?->status ?? 'belum_digenerate';

            $paketTeratas = $rekomendasi->take(4)->map(function (RekomendasiMapel $r) {
                $persenBulat = (int) round((float) $r->probabilitas_penguasaan);
                $kategori    = $this->rekomendasiService->kategoriPenguasaan((float) $r->probabilitas_penguasaan);

                return [
                    'id'                      => $r->id,
                    'mata_pelajaran'          => $r->mataPelajaran?->nama_mapel,
                    'skor_rekomendasi'        => (float) $r->skor_rekomendasi,
                    'probabilitas_penguasaan' => (float) $r->probabilitas_penguasaan,
                    'label_penguasaan'        => "{$persenBulat}%-{$kategori}",
                    'tingkat_rekomendasi'     => $r->tingkat_rekomendasi,
                ];
            })->values();

            return [
                'siswa_id'                  => $s->id,
                'nisn'                      => $s->nisn,
                'nis'                       => $s->nis,
                'nama_lengkap'              => $s->nama_lengkap,
                'kelas'                     => $kelasAktif,
                'kode_riasec'               => $karakter?->kode_riasec,
                'mi_dominan'                => $karakter?->kecerdasan_majemuk_dominan,
                'minat_prodi'               => $s->minatProdi->map(fn ($p) => [
                    'prioritas'        => $p->prioritas,
                    'nama_prodi'       => $p->nama_prodi,
                    'perguruan_tinggi' => $p->perguruan_tinggi,
                ])->values(),
                'status'                    => $statusRek,
                'paket_rekomendasi_teratas' => $paketTeratas,
            ];
        });

        $totalSiswa        = Siswa::count();
        $siswaSudahDikunci = Siswa::whereHas('rekomendasiMapel', fn ($q) => $q->whereIn('status', ['dikunci', 'diterbitkan']))->count();
        $siswaMasihDraft   = Siswa::whereHas('rekomendasiMapel', fn ($q) => $q->where('status', 'draft'))->count();

        return response()->json([
            'ringkasan' => [
                'total_siswa'        => $totalSiswa,
                'status_draft'       => $siswaMasihDraft,
                'status_tervalidasi' => $siswaSudahDikunci,
                'belum_digenerate'   => max(0, $totalSiswa - $siswaMasihDraft - $siswaSudahDikunci),
            ],
            'data' => $dataSiswa,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    public function show(Siswa $siswa): JsonResponse
    {
        if ($siswa->rekomendasiMapel()->count() === 0) {
            $this->rekomendasiService->generate($siswa);
        }

        $siswa->load([
            'riwayatKelas.kelas',
            'nilaiRapor' => fn ($q) => $q->where('semester', 1)->with('mataPelajaran'),
            'asesmenRiasec',
            'asesmenMultipleIntelligence',
            'asesmenKarakteristik',
            'minatProdi',
            'rekomendasiMapel' => fn ($q) => $q->with(['mataPelajaran', 'dikunciOleh'])->orderByDesc('skor_rekomendasi'),
        ]);

        $karakter      = $siswa->asesmenKarakteristik->last();
        $riasec        = $siswa->asesmenRiasec->last();
        $status        = $siswa->rekomendasiMapel->first()?->status ?? 'draft';
        $petaKurikulum = $this->rekomendasiService->petakanMinatProdiKeKurikulum($siswa->minatProdi);

        return response()->json([
            'siswa' => [
                'id'             => $siswa->id,
                'nisn'           => $siswa->nisn,
                'nis'            => $siswa->nis,
                'nama_lengkap'   => $siswa->nama_lengkap,
                'nama_panggilan' => $siswa->nama_panggilan,
                'jenis_kelamin'  => $siswa->jenis_kelamin,
                'kelas'          => $siswa->riwayatKelas->last()?->kelas?->nama_kelas,
            ],
            'fondasi_penilaian' => [
                'nilai_rapor_semester_1' => $siswa->nilaiRapor->map(fn ($n) => [
                    'mata_pelajaran' => $n->mataPelajaran?->nama_mapel,
                    'nilai_akhir'    => (float) $n->nilai_akhir,
                ])->values(),
                'riasec' => $riasec ? [
                    'kode_hasil'          => $riasec->kode_hasil ?? $karakter?->kode_riasec,
                    'realistic_score'     => (float) $riasec->realistic_score,
                    'investigative_score' => (float) $riasec->investigative_score,
                    'artistic_score'      => (float) $riasec->artistic_score,
                    'social_score'        => (float) $riasec->social_score,
                    'enterprising_score'  => (float) $riasec->enterprising_score,
                    'conventional_score'  => (float) $riasec->conventional_score,
                ] : null,
                'multiple_intelligence' => [
                    'dominan' => $karakter?->kecerdasan_majemuk_dominan,
                    'detail'  => $siswa->asesmenMultipleIntelligence->map(fn ($m) => [
                        'jenis_kecerdasan' => $m->jenis_kecerdasan,
                        'skor'             => (float) $m->skor,
                    ])->values(),
                ],
                'minat_prodi' => $siswa->minatProdi->map(fn ($p) => [
                    'prioritas'        => $p->prioritas,
                    'nama_prodi'       => $p->nama_prodi,
                    'perguruan_tinggi' => $p->perguruan_tinggi,
                ])->values(),
                'acuan_kurikulum_merdeka' => $petaKurikulum,
            ],
            'status_rekomendasi'       => $status,
            'paket_utama_4_mapel'      => $siswa->rekomendasiMapel->take(4)->pluck('mataPelajaran.nama_mapel')->values(),
            'daftar_rekomendasi_mapel' => $siswa->rekomendasiMapel->values()->map(
                fn (RekomendasiMapel $r, int $idx) => $this->formatItemRekomendasi($r, $idx + 1, $petaKurikulum)
            ),
        ]);
    }

    public function showItem(RekomendasiMapel $rekomendasi): JsonResponse
    {
        $rekomendasi->load(['mataPelajaran', 'siswa']);

        return response()->json([
            'siswa' => [
                'id'           => $rekomendasi->siswa?->id,
                'nis'          => $rekomendasi->siswa?->nis,
                'nama_lengkap' => $rekomendasi->siswa?->nama_lengkap,
            ],
            'data' => $this->formatItemRekomendasi($rekomendasi),
        ]);
    }

    public function generate(Request $request, Siswa $siswa): JsonResponse
    {
        $tahunAjaran = $request->input('tahun_ajaran', date('Y') . '/' . ((int) date('Y') + 1));

        $sudahDikunci = $siswa->rekomendasiMapel()
            ->whereIn('status', ['dikunci', 'diterbitkan'])
            ->exists();

        if ($sudahDikunci) {
            return response()->json([
                'message' => 'Rekomendasi siswa ini sudah dikunci/diterbitkan. Buka kunci terlebih dahulu jika ingin menghitung ulang.',
            ], 422);
        }

        $hasil = $this->rekomendasiService->generate($siswa, $tahunAjaran, true);

        return response()->json([
            'message'         => 'Draf rekomendasi mapel pilihan berhasil dihitung ulang.',
            'siswa_id'        => $siswa->id,
            'nama_siswa'      => $siswa->nama_lengkap,
            'jumlah_mapel'    => $hasil->count(),
            'paket_4_teratas' => $hasil->take(4)->map(fn ($r) => $this->formatItemRekomendasi($r))->values(),
        ]);
    }

    public function generateAll(Request $request): JsonResponse
    {
        $tahunAjaran = $request->input('tahun_ajaran', date('Y') . '/' . ((int) date('Y') + 1));
        $diproses    = 0;
        $dilewati    = 0;

        foreach (Siswa::cursor() as $siswa) {
            $terkunci = $siswa->rekomendasiMapel()
                ->where('tahun_ajaran', $tahunAjaran)
                ->whereIn('status', ['dikunci', 'diterbitkan'])
                ->exists();

            if ($terkunci) {
                $dilewati++;
                continue;
            }

            $this->rekomendasiService->generate($siswa, $tahunAjaran);
            $diproses++;
        }

        return response()->json([
            'message'                 => 'Proses generate draf rekomendasi seluruh siswa selesai.',
            'siswa_diproses'          => $diproses,
            'siswa_dilewati_terkunci' => $dilewati,
        ]);
    }

    public function updateDraf(Request $request, RekomendasiMapel $rekomendasi): JsonResponse
    {
        if (in_array($rekomendasi->status, ['dikunci', 'diterbitkan'], true)) {
            return response()->json([
                'message' => 'Rekomendasi sudah dikunci dan tidak dapat diubah.',
            ], 422);
        }

        $validated = $request->validate([
            'alasan_rekomendasi'              => 'nullable|string',
            'catatan_penjelasan'              => 'nullable|string',
            'strategi_belajar'                => 'nullable|string',
            'rekomendasi_alokasi_jam_mandiri' => 'nullable|string',
            'fokus_penguatan_materi'          => 'nullable|string',
            'catatan_evaluasi_akademik'       => 'nullable|string',
            'tingkat_rekomendasi'             => 'nullable|string|max:100',
            'probabilitas_penguasaan'         => 'nullable|numeric|min:0|max:100',
        ]);

        $payload = array_filter([
            'alasan_rekomendasi'        => $validated['catatan_penjelasan'] ?? $validated['alasan_rekomendasi'] ?? null,
            'strategi_belajar'          => $validated['rekomendasi_alokasi_jam_mandiri'] ?? $validated['strategi_belajar'] ?? null,
            'fokus_penguatan_materi'    => $validated['fokus_penguatan_materi'] ?? null,
            'catatan_evaluasi_akademik' => $validated['catatan_evaluasi_akademik'] ?? null,
            'tingkat_rekomendasi'       => $validated['tingkat_rekomendasi'] ?? null,
            'probabilitas_penguasaan'   => $validated['probabilitas_penguasaan'] ?? null,
        ], fn ($v) => $v !== null);

        $rekomendasi->update($payload);
        $rekomendasi->load('mataPelajaran');

        return response()->json([
            'message' => 'Draf rekomendasi berhasil diperbarui.',
            'data'    => $this->formatItemRekomendasi($rekomendasi),
        ]);
    }

    private function formatItemRekomendasi(RekomendasiMapel $r, ?int $peringkat = null, ?array $petaKurikulum = null): array
    {
        $namaMapel   = $r->mataPelajaran?->nama_mapel ?? '';
        $probFloat   = (float) ($r->probabilitas_penguasaan ?? $r->skor_rekomendasi);
        $persenBulat = (int) round($probFloat);
        $kategori    = $this->rekomendasiService->kategoriPenguasaan($probFloat);
        $label       = "{$persenBulat}%-{$kategori}";

        if ($petaKurikulum === null && $r->siswa_id) {
            $minatProdi    = $r->siswa?->minatProdi ?? \App\Models\MinatProdiSiswa::where('siswa_id', $r->siswa_id)->orderBy('prioritas')->get();
            $petaKurikulum = $this->rekomendasiService->petakanMinatProdiKeKurikulum($minatProdi);
        }

        $infoKurikulum = !empty($petaKurikulum)
            ? $this->rekomendasiService->evaluasiKurikulumProdi($namaMapel, $petaKurikulum)
            : null;

        $alokasiJam = $r->strategi_belajar
            ?: $this->rekomendasiService->buatRekomendasiAlokasiJam($namaMapel, $probFloat);

        $fokusPenguatan = $r->fokus_penguatan_materi
            ?: $this->rekomendasiService->buatFokusPenguatanMateri($namaMapel, $probFloat);

        $catatanEvaluasi = $r->catatan_evaluasi_akademik
            ?: $this->rekomendasiService->buatCatatanEvaluasiAkademik($namaMapel, $probFloat, $probFloat, $probFloat);

        return [
            'id'                      => $r->id,
            'peringkat'               => $peringkat,
            'direkomendasikan_paket'  => $peringkat !== null ? $peringkat <= 4 : null,
            'mata_pelajaran_id'       => $r->mata_pelajaran_id,
            'nama_mapel'              => $namaMapel,
            'judul_modal'             => strtoupper($namaMapel),
            'kelompok'                => $r->mataPelajaran?->kelompok,
            'skor_rekomendasi'        => (float) $r->skor_rekomendasi,
            'tingkat_rekomendasi'     => $r->tingkat_rekomendasi,
            'status'                  => $r->status,
            'tahun_ajaran'            => $r->tahun_ajaran,
            'tanggal_rekomendasi'     => $r->tanggal_rekomendasi?->format('Y-m-d'),
            'keselarasan_kurikulum'   => $infoKurikulum ? [
                'peran'      => $infoKurikulum['peran'],
                'skor_prodi' => $infoKurikulum['skor'],
                'keterangan' => $infoKurikulum['keterangan'],
            ] : null,

            'estimasi_penguasaan_materi' => [
                'persentase'         => $persenBulat,
                'persentase_decimal' => $probFloat,
                'kategori'           => $kategori,
                'label'              => $label,
                'catatan_penjelasan' => $r->alasan_rekomendasi,
            ],

            'silabus_fase_f' => [
                'deskripsi_fokus' => $r->mataPelajaran?->deskripsi,
                'topik_utama'     => $r->mataPelajaran?->topik_utama ?? [],
            ],

            'strategi_belajar_mandiri' => [
                'rekomendasi_alokasi_jam_mandiri' => $alokasiJam,
                'fokus_penguatan_materi'          => $fokusPenguatan,
            ],

            'catatan_evaluasi_akademik' => $catatanEvaluasi,
        ];
    }
}
