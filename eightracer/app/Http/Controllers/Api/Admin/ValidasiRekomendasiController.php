<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guru;
use App\Models\RekomendasiMapel;
use App\Models\Siswa;
use App\Models\SkRekomendasiMapel;
use App\Services\RekomendasiMapelService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ValidasiRekomendasiController extends Controller
{
    public const DEFAULT_KONTEN_SURAT = "Surat rekomendasi ini disusun oleh Guru Bimbingan dan Konseling berdasarkan hasil identifikasi potensi, minat, bakat, hasil belajar, serta informasi pendukung lainnya yang relevan. Rekomendasi ini bertujuan sebagai bahan pertimbangan dan referensi bagi murid dalam menentukan pilihan mata pelajaran sesuai dengan rencana studi lanjut dan pengembangan kariernya.\n\nKeputusan akhir mengenai pemilihan mata pelajaran tetap berada pada murid, dengan mempertimbangkan hasil refleksi diri, informasi yang tersedia, serta diskusi dan persetujuan orang tua/wali. Orang tua/wali diharapkan mendampingi murid dalam proses pengambilan keputusan tersebut.\n\nSurat rekomendasi ini tidak bersifat mengikat atau menentukan secara mutlak pilihan murid. Layanan Bimbingan dan Konseling berperan memfasilitasi murid agar mampu mengambil keputusan pendidikan secara mandiri, bertanggung jawab, dan sesuai dengan potensi serta rencana masa depannya.";

    public function __construct(
        private readonly RekomendasiMapelService $rekomendasiService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $search  = trim((string) $request->query('search', ''));
        $kelas   = trim((string) $request->query('kelas', ''));
        $status  = trim((string) $request->query('status', ''));
        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));

        $query = Siswa::with([
            'riwayatKelas.kelas',
            'minatProdi',
            'skRekomendasiMapel.guruBk',
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

        if ($status === 'terkunci' || $status === 'diterbitkan' || $status === 'dikunci') {
            $query->whereHas('rekomendasiMapel', fn ($q) => $q->whereIn('status', ['dikunci', 'diterbitkan']));
        } elseif ($status === 'draft') {
            $query->whereHas('rekomendasiMapel', fn ($q) => $q->where('status', 'draft'));
        }

        $paginator = $query->orderBy('nama_lengkap')->paginate($perPage);

        $items = collect($paginator->items())->map(function (Siswa $s) {
            $rekFirst  = $s->rekomendasiMapel->first();
            $statusRek = $rekFirst?->status ?? 'draft';
            $terkunci  = in_array($statusRek, ['dikunci', 'diterbitkan'], true);

            return [
                'siswa_id'            => $s->id,
                'nisn'                => $s->nisn,
                'nis'                 => $s->nis,
                'nama_lengkap'        => $s->nama_lengkap,
                'kelas'               => $s->riwayatKelas->last()?->kelas?->nama_kelas ?? '-',
                'minat_prodi_utama'   => $s->minatProdi->first()?->nama_prodi ?? '-',
                'paket_4_mapel'       => $s->rekomendasiMapel->take(4)->pluck('mataPelajaran.nama_mapel')->values(),
                'status_validasi'     => $statusRek,
                'terkunci_permanen'   => $terkunci,
                'nomor_sk'            => $s->skRekomendasiMapel?->nomor_sk,
                'tanggal_terbit'      => $s->skRekomendasiMapel?->tanggal_terbit?->format('Y-m-d'),
                'dikunci_pada'        => $rekFirst?->dikunci_pada?->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'ringkasan' => [
                'total_siswa'        => Siswa::count(),
                'status_draft'       => Siswa::whereHas('rekomendasiMapel', fn ($q) => $q->where('status', 'draft'))
                    ->whereDoesntHave('rekomendasiMapel', fn ($q) => $q->whereIn('status', ['dikunci', 'diterbitkan']))
                    ->count(),
                'sk_terbit_terkunci' => Siswa::whereHas('rekomendasiMapel', fn ($q) => $q->whereIn('status', ['dikunci', 'diterbitkan']))->count(),
            ],
            'data' => $items,
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
            'minatProdi',
            'skRekomendasiMapel.guruBk',
            'rekomendasiMapel' => fn ($q) => $q->with(['mataPelajaran', 'dikunciOleh'])->orderByDesc('skor_rekomendasi'),
        ]);

        $tahunAjaran = $siswa->rekomendasiMapel->first()?->tahun_ajaran
            ?? (date('Y') . '/' . ((int) date('Y') + 1));

        $sk = $siswa->skRekomendasiMapel;
        if (!$sk) {
            $guruDefault = $this->ambilAtauBuatGuruBkDefault();
            $sk = SkRekomendasiMapel::create([
                'siswa_id'     => $siswa->id,
                'guru_bk_id'   => $guruDefault?->id,
                'tahun_ajaran' => $tahunAjaran,
                'nomor_sk'     => $this->buatNomorSkOtomatis($siswa->id),
                'konten_surat' => self::DEFAULT_KONTEN_SURAT,
                'status'       => 'draft',
            ]);
            $sk->load('guruBk');
        }

        $rekFirst      = $siswa->rekomendasiMapel->first();
        $statusRek     = $rekFirst?->status ?? 'draft';
        $terkunci      = in_array($statusRek, ['dikunci', 'diterbitkan'], true);
        $petaKurikulum = $this->rekomendasiService->petakanMinatProdiKeKurikulum($siswa->minatProdi);

        $paket4Mapel = $siswa->rekomendasiMapel->take(4)->values()->map(function (RekomendasiMapel $r, int $idx) use ($petaKurikulum) {
            $namaMapel   = $r->mataPelajaran?->nama_mapel ?? '';
            $probFloat   = (float) ($r->probabilitas_penguasaan ?? $r->skor_rekomendasi);
            $persenBulat = (int) round($probFloat);
            $kategori    = $this->rekomendasiService->kategoriPenguasaan($probFloat);
            $infoKur     = $this->rekomendasiService->evaluasiKurikulumProdi($namaMapel, $petaKurikulum);

            return [
                'rekomendasi_id'      => $r->id,
                'mata_pelajaran_id'   => $r->mata_pelajaran_id,
                'peringkat'           => $idx + 1,
                'nama_mapel'          => $namaMapel,
                'kelompok'            => $r->mataPelajaran?->kelompok,
                'skor_rekomendasi'    => (float) $r->skor_rekomendasi,
                'estimasi_penguasaan' => "{$persenBulat}%-{$kategori}",
                'tingkat_rekomendasi' => $r->tingkat_rekomendasi,
                'peran_kurikulum'     => $infoKur['peran'],
                'keterangan_kurikulum'=> $infoKur['keterangan'],
            ];
        });

        $kontenSurat  = $sk->konten_surat ?: self::DEFAULT_KONTEN_SURAT;
        $paragrafList = array_values(array_filter(array_map('trim', preg_split('/\n\s*\n/', $kontenSurat))));

        $guruPenandatangan = $sk->guruBk ?? $rekFirst?->dikunciOleh ?? $this->ambilAtauBuatGuruBkDefault();

        return response()->json([
            'kop_surat' => [
                'baris_1'      => 'PEMERINTAH PROVINSI JAWA TIMUR',
                'baris_2'      => 'DINAS PENDIDIKAN',
                'nama_sekolah' => 'SEKOLAH MENENGAH ATAS NEGERI 8 MALANG',
                'alamat'       => 'Jl. Veteran 37 Telp. (0341) 551096 Fax (0341) 575795 MALANG',
                'kontak'       => 'Website: www.sman8malang.sch.id E-mail: sman8malang@gmail.com',
                'npsn_kodepos' => 'NPSN: 20533640 Kode Pos: 65145',
                'kota'         => 'KOTA MALANG',
            ],
            'identitas_siswa' => [
                'siswa_id'     => $siswa->id,
                'nama_lengkap' => $siswa->nama_lengkap,
                'nisn'         => $siswa->nisn,
                'nis'          => $siswa->nis,
                'kelas'        => $siswa->riwayatKelas->last()?->kelas?->nama_kelas ?? '-',
                'minat_prodi'  => $petaKurikulum,
            ],
            'paket_mata_pelajaran_pilihan' => $paket4Mapel,
            'opsi_semua_mapel_pilihan'     => $siswa->rekomendasiMapel->values()->map(fn (RekomendasiMapel $r, int $idx) => [
                'rekomendasi_id'    => $r->id,
                'mata_pelajaran_id' => $r->mata_pelajaran_id,
                'peringkat'         => $idx + 1,
                'terpilih_paket_4'  => $idx < 4,
                'nama_mapel'        => $r->mataPelajaran?->nama_mapel,
                'kelompok'          => $r->mataPelajaran?->kelompok,
                'skor_rekomendasi'  => (float) $r->skor_rekomendasi,
            ]),
            'keterangan_dan_penegasan' => [
                'judul'         => 'KETERANGAN DAN PENEGASAN',
                'dapat_diedit'  => !$terkunci,
                'nomor_sk'      => $sk->nomor_sk,
                'tahun_ajaran'  => $sk->tahun_ajaran,
                'konten_surat'  => $kontenSurat,
                'paragraf_list' => $paragrafList,
            ],
            'penandatangan' => [
                'guru_bk_id'   => $guruPenandatangan?->id,
                'jabatan'      => strtoupper($guruPenandatangan?->jabatan ?: 'KOORDINATOR BIMBINGAN KONSELING'),
                'nama_lengkap' => $guruPenandatangan?->nama_lengkap ?? 'Dra. Endang Sulastri',
                'nip'          => $guruPenandatangan?->nip ?? '196805121994032008',
                'label_nip'    => 'NIP. ' . ($guruPenandatangan?->nip ?? '196805121994032008'),
                'opsi_guru_bk' => Guru::orderBy('nama_lengkap')->get(['id', 'nip', 'nama_lengkap', 'jabatan']),
            ],
            'status_validasi' => [
                'status_rekomendasi' => $statusRek,
                'status_sk'          => $sk->status,
                'terkunci_permanen'  => $terkunci,
                'tanggal_terbit'     => $sk->tanggal_terbit?->format('Y-m-d'),
                'dikunci_oleh'       => $rekFirst?->dikunciOleh?->nama_lengkap,
                'dikunci_pada'       => $rekFirst?->dikunci_pada?->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    public function simpanDraft(Request $request, Siswa $siswa): JsonResponse
    {
        $terkunci = $siswa->rekomendasiMapel()
            ->whereIn('status', ['dikunci', 'diterbitkan'])
            ->exists();

        if ($terkunci) {
            return response()->json([
                'message' => 'Rekomendasi dan Surat Rekomendasi siswa ini sudah dikunci permanen dan tidak dapat diubah.',
            ], 422);
        }

        $validated = $request->validate([
            'konten_surat'      => 'nullable|string',
            'nomor_sk'          => 'nullable|string|max:100',
            'guru_bk_id'        => 'nullable|integer|exists:guru,id',
            'paket_mapel_ids'   => 'nullable|array|min:1|max:4',
            'paket_mapel_ids.*' => 'integer|exists:mata_pelajaran,id',
        ]);

        DB::transaction(function () use ($siswa, $validated) {
            $this->terapkanPerubahanDraft($siswa, $validated);
        });

        return response()->json([
            'message' => 'Draft Surat Rekomendasi berhasil disimpan.',
            'data'    => $this->show($siswa)->getData(true),
        ]);
    }

    public function validasiDanKunci(Request $request, Siswa $siswa): JsonResponse
    {
        if ($siswa->rekomendasiMapel()->count() === 0) {
            $this->rekomendasiService->generate($siswa);
        }

        $validated = $request->validate([
            'konten_surat'      => 'nullable|string',
            'nomor_sk'          => 'nullable|string|max:100',
            'guru_bk_id'        => 'nullable|integer|exists:guru,id',
            'paket_mapel_ids'   => 'nullable|array|min:1|max:4',
            'paket_mapel_ids.*' => 'integer|exists:mata_pelajaran,id',
        ]);

        DB::transaction(function () use ($siswa, $validated) {
            $sk = $this->terapkanPerubahanDraft($siswa, $validated);

            $guruId = $validated['guru_bk_id']
                ?? $sk->guru_bk_id
                ?? $this->ambilAtauBuatGuruBkDefault()?->id;

            // Kunci permanen seluruh rekomendasi_mapel milik siswa
            $siswa->rekomendasiMapel()->update([
                'status'       => 'diterbitkan',
                'dikunci_oleh' => $guruId,
                'dikunci_pada' => Carbon::now(),
            ]);

            // Terbitkan SK Rekomendasi Mapel
            $sk->update([
                'guru_bk_id'     => $guruId,
                'status'         => 'diterbitkan',
                'tanggal_terbit' => Carbon::today()->format('Y-m-d'),
            ]);
        });

        return response()->json([
            'message' => "Rekomendasi dan Surat Rekomendasi untuk {$siswa->nama_lengkap} berhasil divalidasi dan dikunci permanen.",
            'data'    => $this->show($siswa)->getData(true),
        ]);
    }

    public function bukaKunci(Siswa $siswa): JsonResponse
    {
        DB::transaction(function () use ($siswa) {
            $siswa->rekomendasiMapel()->update([
                'status'       => 'draft',
                'dikunci_oleh' => null,
                'dikunci_pada' => null,
            ]);

            $siswa->skRekomendasiMapel()?->update([
                'status'         => 'draft',
                'tanggal_terbit' => null,
            ]);
        });

        return response()->json([
            'message' => "Kunci rekomendasi untuk {$siswa->nama_lengkap} berhasil dibuka kembali menjadi draft.",
            'data'    => $this->show($siswa)->getData(true),
        ]);
    }

    public function validasiMassal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'siswa_ids'   => 'nullable|array',
            'siswa_ids.*' => 'integer|exists:siswa,id',
            'kelas'       => 'nullable|string',
            'guru_bk_id'  => 'nullable|integer|exists:guru,id',
        ]);

        $query = Siswa::query();
        if (!empty($validated['siswa_ids'])) {
            $query->whereIn('id', $validated['siswa_ids']);
        } elseif (!empty($validated['kelas'])) {
            $query->whereHas('riwayatKelas.kelas', fn ($q) => $q->where('nama_kelas', $validated['kelas']));
        }

        $daftarSiswa = $query->get();
        $guruId      = $validated['guru_bk_id'] ?? $this->ambilAtauBuatGuruBkDefault()?->id;
        $jumlah      = 0;

        DB::transaction(function () use ($daftarSiswa, $guruId, &$jumlah) {
            foreach ($daftarSiswa as $siswa) {
                if ($siswa->rekomendasiMapel()->count() === 0) {
                    $this->rekomendasiService->generate($siswa);
                }

                $tahunAjaran = date('Y') . '/' . ((int) date('Y') + 1);

                $siswa->rekomendasiMapel()->update([
                    'status'       => 'diterbitkan',
                    'dikunci_oleh' => $guruId,
                    'dikunci_pada' => Carbon::now(),
                ]);

                SkRekomendasiMapel::updateOrCreate(
                    ['siswa_id' => $siswa->id],
                    [
                        'guru_bk_id'     => $guruId,
                        'tahun_ajaran'   => $tahunAjaran,
                        'nomor_sk'       => $siswa->skRekomendasiMapel?->nomor_sk ?? $this->buatNomorSkOtomatis($siswa->id),
                        'konten_surat'   => $siswa->skRekomendasiMapel?->konten_surat ?? self::DEFAULT_KONTEN_SURAT,
                        'status'         => 'diterbitkan',
                        'tanggal_terbit' => Carbon::today()->format('Y-m-d'),
                    ]
                );

                $jumlah++;
            }
        });

        return response()->json([
            'message'              => "Berhasil memvalidasi dan mengunci permanen {$jumlah} siswa.",
            'jumlah_tervalidasi'   => $jumlah,
        ]);
    }

    private function terapkanPerubahanDraft(Siswa $siswa, array $validated): SkRekomendasiMapel
    {
        $tahunAjaran = $siswa->rekomendasiMapel()->value('tahun_ajaran')
            ?: (date('Y') . '/' . ((int) date('Y') + 1));

        $guruDefault = $this->ambilAtauBuatGuruBkDefault();

        $sk = SkRekomendasiMapel::firstOrCreate(
            ['siswa_id' => $siswa->id],
            [
                'guru_bk_id'   => $guruDefault?->id,
                'tahun_ajaran' => $tahunAjaran,
                'nomor_sk'     => $this->buatNomorSkOtomatis($siswa->id),
                'konten_surat' => self::DEFAULT_KONTEN_SURAT,
                'status'       => 'draft',
            ]
        );

        $updateSk = [];
        if (array_key_exists('konten_surat', $validated) && $validated['konten_surat'] !== null) {
            $updateSk['konten_surat'] = $validated['konten_surat'];
        }
        if (!empty($validated['nomor_sk'])) {
            $updateSk['nomor_sk'] = $validated['nomor_sk'];
        }
        if (!empty($validated['guru_bk_id'])) {
            $updateSk['guru_bk_id'] = $validated['guru_bk_id'];
        }

        if (!empty($updateSk)) {
            $sk->update($updateSk);
        }

        // Jika Guru BK menyesuaikan 4 mapel pilihan secara manual sebelum dikunci
        if (!empty($validated['paket_mapel_ids'])) {
            $mapelIds = array_values(array_unique($validated['paket_mapel_ids']));
            foreach ($mapelIds as $idx => $mapelId) {
                $skorPrioritas = 99.0 - ($idx * 0.5);
                $siswa->rekomendasiMapel()
                    ->where('mata_pelajaran_id', $mapelId)
                    ->update([
                        'skor_rekomendasi'    => $skorPrioritas,
                        'tingkat_rekomendasi' => 'Sangat Direkomendasikan',
                    ]);
            }
        }

        return $sk->fresh('guruBk');
    }

    private function ambilAtauBuatGuruBkDefault(): ?Guru
    {
        $guru = Guru::first();
        if ($guru) {
            return $guru;
        }

        $user = \App\Models\User::firstOrCreate(
            ['username' => '196805121994032008'],
            ['password' => '196805121994032008', 'role' => 'guru']
        );

        return Guru::create([
            'user_id'      => $user->id,
            'nip'          => '196805121994032008',
            'nama_lengkap' => 'Dra. Endang Sulastri',
            'jabatan'      => 'Koordinator Bimbingan Konseling',
        ]);
    }

    private function buatNomorSkOtomatis(int $siswaId): string
    {
        $urut  = str_pad((string) $siswaId, 3, '0', STR_PAD_LEFT);
        $tahun = date('Y');
        return "421.3/{$urut}/101.6.10.SMAN8/{$tahun}";
    }
}
