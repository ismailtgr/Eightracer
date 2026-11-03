<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\Siswa;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManajemenDataController extends Controller
{
    private const RIASEC_LABEL = [
        'R' => 'Realistic',
        'I' => 'Investigative',
        'A' => 'Artistic',
        'S' => 'Social',
        'E' => 'Enterprising',
        'C' => 'Conventional',
    ];

    private const MI_LABEL = [
        'logis_matematis' => 'Logis-Matematis',
        'linguistik'      => 'Verbal-Linguistik',
        'spasial'         => 'Visual-Spasial',
        'musikal'         => 'Musikal',
        'kinestetik'      => 'Kinestetik',
        'interpersonal'   => 'Interpersonal',
        'intrapersonal'   => 'Intrapersonal',
        'naturalis'       => 'Naturalis',
    ];

    public function index(Request $request): JsonResponse
    {
        $search  = trim((string) $request->query('search', ''));
        $mi      = trim((string) $request->query('mi', ''));
        $riasec  = trim((string) $request->query('riasec', ''));
        $kelas   = trim((string) $request->query('kelas', ''));
        $perPage = max(1, min(100, (int) $request->query('per_page', 10)));

        $query = Siswa::with([
            'riwayatKelas.kelas',
            'nilaiRapor' => fn ($q) => $q->where('semester', 1),
            'asesmenKarakteristik',
            'asesmenRiasec',
            'asesmenMultipleIntelligence',
            'minatProdi',
        ]);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('nisn', 'like', "%{$search}%")
                  ->orWhere('nis', 'like', "%{$search}%")
                  ->orWhereHas('riwayatKelas.kelas', fn ($kq) => $kq->where('nama_kelas', 'like', "%{$search}%"));
            });
        }

        if ($kelas !== '') {
            $query->whereHas('riwayatKelas.kelas', function ($q) use ($kelas) {
                $q->where('nama_kelas', $kelas);
            });
        }

        if ($mi !== '') {
            $query->where(function ($q) use ($mi) {
                $q->whereHas('asesmenKarakteristik', fn ($kq) => $kq->where('kecerdasan_majemuk_dominan', 'like', "%{$mi}%"))
                  ->orWhereHas('asesmenMultipleIntelligence', fn ($mq) => $mq->where('jenis_kecerdasan', 'like', "%{$mi}%"));
            });
        }

        if ($riasec !== '') {
            $huruf = strtoupper(substr($riasec, 0, 1));
            $query->where(function ($q) use ($riasec, $huruf) {
                $q->whereHas('asesmenKarakteristik', fn ($kq) => $kq->where('kode_riasec', 'like', "{$huruf}%")->orWhere('kode_riasec', 'like', "%{$riasec}%"))
                  ->orWhereHas('asesmenRiasec', fn ($rq) => $rq->where('kode_hasil', 'like', "{$huruf}%")->orWhere('kode_hasil', 'like', "%{$riasec}%"));
            });
        }

        $paginator = $query->orderBy('nama_lengkap')->paginate($perPage);

        $rows = collect($paginator->items())->map(function (Siswa $s) {
            $kelasAktif = $s->riwayatKelas->last()?->kelas?->nama_kelas ?? '-';
            $rataRapor  = $s->nilaiRapor->count() > 0
                ? round((float) $s->nilaiRapor->avg('nilai_akhir'), 1)
                : null;

            $karakter    = $s->asesmenKarakteristik->last();
            $riasecRec   = $s->asesmenRiasec->last();
            $kodeRiasec  = strtoupper(trim((string) ($riasecRec?->kode_hasil ?? $karakter?->kode_riasec ?? '')));
            $hurufUtama  = substr($kodeRiasec, 0, 1);
            $labelRiasec = self::RIASEC_LABEL[$hurufUtama] ?? ($kodeRiasec !== '' ? $kodeRiasec : '-');

            $miRaw = $karakter?->kecerdasan_majemuk_dominan;
            if ($miRaw) {
                $miDominan = collect(explode(',', $miRaw))
                    ->map(fn ($k) => trim($k))
                    ->filter()
                    ->map(fn ($k) => self::MI_LABEL[strtolower($k)] ?? ucfirst($k))
                    ->implode(', ');
            } elseif ($s->asesmenMultipleIntelligence->isNotEmpty()) {
                $miDominan = $s->asesmenMultipleIntelligence
                    ->sortByDesc('skor')
                    ->take(2)
                    ->map(fn ($m) => self::MI_LABEL[$m->jenis_kecerdasan] ?? ucfirst($m->jenis_kecerdasan))
                    ->implode(', ');
            } else {
                $miDominan = '-';
            }

            $prodiUtama = $s->minatProdi->first()?->nama_prodi ?? '-';

            return [
                'id'                 => $s->id,
                'nisn'               => $s->nisn,
                'nis'                => $s->nis,
                'nama_siswa'         => $s->nama_lengkap,
                'kelas'              => $kelasAktif,
                'nilai_rapor'        => $rataRapor,
                'riasec'             => $labelRiasec,
                'kode_riasec'        => $kodeRiasec ?: null,
                'kecerdasan_majemuk' => $miDominan,
                'minat_prodi'        => $prodiUtama,
                'minat_prodi_list'   => $s->minatProdi->map(fn ($p) => [
                    'prioritas'        => $p->prioritas,
                    'nama_prodi'       => $p->nama_prodi,
                    'perguruan_tinggi' => $p->perguruan_tinggi,
                ])->values(),
            ];
        });

        return response()->json([
            'data' => $rows,
            'opsi_filter' => [
                'kelas'  => Kelas::orderBy('nama_kelas')->pluck('nama_kelas')->values(),
                'riasec' => array_values(self::RIASEC_LABEL),
                'mi'     => array_values(self::MI_LABEL),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'keterangan'   => sprintf('Menampilkan %d dari %d data siswa', $rows->count(), $paginator->total()),
            ],
        ]);
    }

    public function show(Siswa $siswa): JsonResponse
    {
        $siswa->load([
            'riwayatKelas.kelas',
            'saudaraTiri',
            'hobi',
            'citaCita',
            'minatProdi',
            'mediaSosial',
            'prestasiMurid',
            'orangTua',
            'wali',
            'bantuanSiswa.jenisBantuan',
        ]);

        $riwayatTerakhir = $siswa->riwayatKelas->last();
        $kelasAktif      = $riwayatTerakhir?->kelas?->nama_kelas ?? '-';

        $kataNama = preg_split('/\s+/', trim($siswa->nama_lengkap)) ?: [];
        $inisial  = strtoupper(
            substr($kataNama[0] ?? 'S', 0, 1) .
            substr($kataNama[1] ?? ($kataNama[0] ?? 'W'), 0, 1)
        );

        $tglLahirStr = $siswa->tanggal_lahir ? $siswa->tanggal_lahir->format('Y-m-d') : null;
        $tglLahirIndo = $siswa->tanggal_lahir
            ? $siswa->tanggal_lahir->locale('id')->translatedFormat('d F Y')
            : null;
        $tempatTglLahir = trim(
            ($siswa->tempat_lahir ?: '') .
            ($siswa->tempat_lahir && $tglLahirIndo ? ', ' : '') .
            ($tglLahirIndo ?: '')
        ) ?: '-';

        $statusSaudaraTiri = $siswa->saudaraTiri->last()?->status ?? 'Tidak';

        $daftarHobi = $siswa->hobi->pluck('nama_hobi')->filter()->values();
        $hobiStr    = $daftarHobi->isNotEmpty() ? $daftarHobi->implode(', ') : '-';

        $daftarCita = $siswa->citaCita
            ->sortBy(fn ($c) => $c->pivot?->prioritas ?? 99)
            ->map(fn ($c) => [
                'prioritas'      => $c->pivot?->prioritas,
                'nama_cita_cita' => $c->nama_cita_cita,
            ])
            ->values();

        $citaNamaList = $daftarCita->pluck('nama_cita_cita')->filter()->values();
        if ($citaNamaList->isEmpty() && $siswa->minatProdi->isNotEmpty()) {
            $citaNamaList = $siswa->minatProdi->pluck('nama_prodi')->filter()->values();
        }
        $citaCitaStr = $citaNamaList->isNotEmpty() ? $citaNamaList->implode(' & ') : '-';

        $gameBadges = [];
        if ($siswa->permainan_online) {
            $gameBadges[] = "Online ({$siswa->permainan_online})";
        }
        if ($siswa->permainan_offline) {
            $gameBadges[] = "Offline ({$siswa->permainan_offline})";
        }

        $waMedia = $siswa->mediaSosial->first(
            fn ($m) => strtolower((string) $m->jenis_media) === 'whatsapp' || !empty($m->nomor_wa)
        );
        $nomorWaSiswa = $waMedia?->nomor_wa ?: ($waMedia?->username ?: null);

        $daftarMediaSosial = $siswa->mediaSosial->map(fn ($m) => [
            'id'          => $m->id,
            'jenis_media' => $m->jenis_media,
            'username'    => $m->username,
            'nomor_wa'    => $m->nomor_wa,
        ])->values();

        $daftarPrestasiSmp = $siswa->prestasiMurid->map(fn ($p) => [
            'id'             => $p->id,
            'jenis_prestasi' => $p->jenis_prestasi,
            'nama_kompetisi' => $p->nama_kompetisi,
            'tingkat'        => $p->tingkat,
        ])->values();

        $ayah = $siswa->orangTua->firstWhere('jenis_orang_tua', 'ayah');
        $ibu  = $siswa->orangTua->firstWhere('jenis_orang_tua', 'ibu');
        $wali = $siswa->wali;

        $kondisiAyah = $ayah?->kondisi ?: ($ayah ? 'Hidup' : null);
        $kondisiIbu  = $ibu?->kondisi  ?: ($ibu  ? 'Hidup' : null);

        $kondisiKeluarga = ($ayah && $ibu)
            ? 'Lengkap'
            : ($siswa->tinggal_bersama ?: 'Wali / Orang Tua Tunggal');

        $ayahDanPekerjaan = $ayah
            ? trim($ayah->nama . ($ayah->pekerjaan ? " ({$ayah->pekerjaan})" : ''))
            : '-';
        $ibuDanPekerjaan = $ibu
            ? trim($ibu->nama . ($ibu->pekerjaan ? " ({$ibu->pekerjaan})" : ''))
            : '-';

        $gajiAyah = (float) ($ayah?->penghasilan ?? 0);
        $gajiIbu  = (float) ($ibu?->penghasilan ?? 0);
        $totalPenghasilan = $gajiAyah + $gajiIbu;

        $formatRupiah = fn (float $nominal): string => $nominal > 0
            ? 'Rp ' . number_format($nominal, 0, ',', '.') . ' / bln'
            : '-';

        $kontakOrtuUtama = $ayah?->kontak_wa ?: ($ibu?->kontak_wa ?: ($wali?->kontak_wa ?: '-'));
        $alamatOrtuUtama = $ayah?->alamat ?: ($ibu?->alamat ?: ($siswa->alamat_rumah ?: '-'));

        $daftarBantuanLengkap = $siswa->bantuanSiswa->map(fn ($b) => [
            'id'              => $b->id,
            'nama_bantuan'    => $b->jenisBantuan?->nama_bantuan,
            'nomor_penerima'  => $b->nomor_penerima,
            'tanggal_mulai'   => $b->tanggal_mulai?->format('Y-m-d'),
            'tanggal_selesai' => $b->tanggal_selesai?->format('Y-m-d'),
            'aktif'           => (bool) $b->aktif,
        ])->values();

        $namaBantuanAktif = $siswa->bantuanSiswa
            ->filter(fn ($b) => $b->aktif)
            ->map(fn ($b) => strtoupper((string) $b->jenisBantuan?->nama_bantuan))
            ->values();

        $punyaBantuan = function (array $keywords) use ($namaBantuanAktif): bool {
            foreach ($namaBantuanAktif as $nama) {
                foreach ($keywords as $kw) {
                    if (str_contains($nama, strtoupper($kw))) {
                        return true;
                    }
                }
            }
            return false;
        };

        $dataWali = $wali ? [
            'id'                    => $wali->id,
            'nama'                  => $wali->nama,
            'hubungan'              => $wali->hubungan,
            'pekerjaan'             => $wali->pekerjaan,
            'penghasilan'           => $wali->penghasilan !== null ? (float) $wali->penghasilan : null,
            'penghasilan_formatted' => $formatRupiah((float) ($wali->penghasilan ?? 0)),
            'alamat'                => $wali->alamat,
            'kontak_wa'             => $wali->kontak_wa,
        ] : null;

        return response()->json([
            'header' => [
                'id'           => $siswa->id,
                'inisial'      => $inisial,
                'nama_lengkap' => $siswa->nama_lengkap,
                'nisn'         => $siswa->nisn,
                'nis'          => $siswa->nis,
                'kelas'        => $kelasAktif,
            ],

            'biodata_murid' => [
                'nama_lengkap'         => $siswa->nama_lengkap,
                'nama_panggilan'       => $siswa->nama_panggilan ?? '-',
                'kelas_aktif'          => $kelasAktif,
                'riwayat_kelas'        => $siswa->riwayatKelas->map(fn ($rk) => [
                    'id'              => $rk->id,
                    'kelas_id'        => $rk->kelas_id,
                    'nama_kelas'      => $rk->kelas?->nama_kelas,
                    'tingkat'         => $rk->kelas?->tingkat,
                    'tahun_ajaran'    => $rk->kelas?->tahun_ajaran,
                    'tanggal_mulai'   => $rk->tanggal_mulai?->format('Y-m-d'),
                    'tanggal_selesai' => $rk->tanggal_selesai?->format('Y-m-d'),
                    'keterangan'      => $rk->keterangan,
                ])->values(),
                'opsi_pindah_kelas'    => Kelas::orderBy('nama_kelas')->get(['id', 'nama_kelas', 'tingkat', 'tahun_ajaran']),
                'nisn'                 => $siswa->nisn,
                'nis'                  => $siswa->nis,
                'jenis_kelamin'        => $siswa->jenis_kelamin,
                'tempat_lahir'         => $siswa->tempat_lahir,
                'tanggal_lahir'        => $tglLahirStr,
                'tempat_tanggal_lahir' => $tempatTglLahir,
                'agama'                => $siswa->agama ?? '-',
                'golongan_darah'       => $siswa->golongan_darah ?? '-',
                'alamat_rumah'         => $siswa->alamat_rumah ?? '-',
                'tinggal_bersama'      => $siswa->tinggal_bersama ?? '-',
                'anak_ke'              => $siswa->anak_ke,
                'data_saudara_tiri'    => [
                    'status' => $statusSaudaraTiri,
                ],
                'hobi'                 => [
                    'ringkasan'   => $hobiStr,
                    'daftar_hobi' => $daftarHobi,
                ],
                'cita_cita'            => [
                    'ringkasan'        => $citaCitaStr,
                    'daftar_cita_cita' => $daftarCita,
                ],
                'profil_murid'         => [
                    'warna_favorit'            => $siswa->warna_favorit ?? '-',
                    'makanan_favorit'          => $siswa->makanan_favorit ?? '-',
                    'bahasa_keseharian'        => $siswa->bahasa_keseharian ?? '-',
                    'permainan_online'         => $siswa->permainan_online,
                    'permainan_offline'        => $siswa->permainan_offline,
                    'permainan_favorit_badges' => $gameBadges,
                ],
                'media_sosial'         => [
                    'nomor_wa'            => $nomorWaSiswa,
                    'daftar_media_sosial' => $daftarMediaSosial,
                ],
                'prestasi_smp'         => $daftarPrestasiSmp,
            ],

            'latar_belakang_ekonomi' => [
                'kondisi_orang_tua_wali' => $kondisiKeluarga,
                'ayah' => $ayah ? [
                    'id'                    => $ayah->id,
                    'nama'                  => $ayah->nama,
                    'kondisi'               => $kondisiAyah,
                    'pekerjaan'             => $ayah->pekerjaan,
                    'label_nama_pekerjaan'  => $ayahDanPekerjaan,
                    'penghasilan'           => $gajiAyah,
                    'penghasilan_formatted' => $formatRupiah($gajiAyah),
                    'alamat'                => $ayah->alamat,
                    'kontak_wa'             => $ayah->kontak_wa,
                ] : null,
                'ibu' => $ibu ? [
                    'id'                    => $ibu->id,
                    'nama'                  => $ibu->nama,
                    'kondisi'               => $kondisiIbu,
                    'pekerjaan'             => $ibu->pekerjaan,
                    'label_nama_pekerjaan'  => $ibuDanPekerjaan,
                    'penghasilan'           => $gajiIbu,
                    'penghasilan_formatted' => $formatRupiah($gajiIbu),
                    'alamat'                => $ibu->alamat,
                    'kontak_wa'             => $ibu->kontak_wa,
                ] : null,
                'ringkasan_orang_tua' => [
                    'ayah_dan_pekerjaan'       => $ayahDanPekerjaan,
                    'ibu_dan_pekerjaan'        => $ibuDanPekerjaan,
                    'kondisi_ayah'             => $kondisiAyah ?? '-',
                    'kondisi_ibu'              => $kondisiIbu ?? '-',
                    'penghasilan_gabungan'     => $formatRupiah($totalPenghasilan),
                    'penghasilan_gabungan_raw' => $totalPenghasilan,
                    'alamat_orang_tua'         => $alamatOrtuUtama,
                    'kontak_wa_orang_tua'      => $kontakOrtuUtama,
                ],
                'penerima_bantuan' => [
                    'pip'                    => $punyaBantuan(['PIP', 'PROGRAM INDONESIA PINTAR']) ? 'Terdaftar' : 'Tidak Terdaftar',
                    'pkh'                    => $punyaBantuan(['PKH', 'PROGRAM KELUARGA HARAPAN']) ? 'Terdaftar' : 'Tidak Terdaftar',
                    'kis_bpjs_kesehatan'     => $punyaBantuan(['KIS', 'BPJS', 'KARTU INDONESIA SEHAT']) ? 'Terdaftar' : 'Tidak Terdaftar',
                    'kip'                    => $punyaBantuan(['KIP', 'KARTU INDONESIA PINTAR']) ? 'Memiliki' : 'Tidak Memiliki',
                    'daftar_bantuan_aktif'   => $namaBantuanAktif,
                    'detail_bantuan_lengkap' => $daftarBantuanLengkap,
                ],
                'data_wali' => $dataWali,
            ],
        ]);
    }

    public function pindahKelas(Request $request, Siswa $siswa): JsonResponse
    {
        $validated = $request->validate([
            'nama_kelas'   => 'required_without:kelas_id|nullable|string|max:50',
            'kelas_id'     => 'required_without:nama_kelas|nullable|integer|exists:kelas,id',
            'tingkat'      => 'nullable|in:X,XI,XII',
            'tahun_ajaran' => 'nullable|string|max:20',
            'keterangan'   => 'nullable|string|max:255',
        ]);

        $riwayatBaru = DB::transaction(function () use ($siswa, $validated) {
            if (!empty($validated['kelas_id'])) {
                $kelasTujuan = Kelas::findOrFail($validated['kelas_id']);
            } else {
                $namaKelas = strtoupper(str_replace(' ', '-', trim($validated['nama_kelas'])));
                $tingkat   = $validated['tingkat'] ?? (
                    str_starts_with($namaKelas, 'XII') ? 'XII' : (str_starts_with($namaKelas, 'XI') ? 'XI' : 'X')
                );
                $tahunAjaran = $validated['tahun_ajaran'] ?? (date('Y') . '/' . ((int) date('Y') + 1));

                $kelasTujuan = Kelas::firstOrCreate(
                    ['nama_kelas' => $namaKelas, 'tingkat' => $tingkat],
                    ['tahun_ajaran' => $tahunAjaran]
                );
            }

            $siswa->riwayatKelas()
                ->whereNull('tanggal_selesai')
                ->where('kelas_id', '!=', $kelasTujuan->id)
                ->update(['tanggal_selesai' => Carbon::today()->format('Y-m-d')]);

            return $siswa->riwayatKelas()->updateOrCreate(
                [
                    'kelas_id'        => $kelasTujuan->id,
                    'tanggal_selesai' => null,
                ],
                [
                    'tanggal_mulai' => Carbon::today()->format('Y-m-d'),
                    'keterangan'    => $validated['keterangan'] ?? 'Pemindahan kelas oleh Admin/BK',
                ]
            );
        });

        $riwayatBaru->load('kelas');

        return response()->json([
            'message'      => "Kelas siswa {$siswa->nama_lengkap} berhasil dipindahkan ke {$riwayatBaru->kelas?->nama_kelas}.",
            'kelas_aktif'  => $riwayatBaru->kelas?->nama_kelas,
            'riwayat_baru' => $riwayatBaru,
        ]);
    }
}
