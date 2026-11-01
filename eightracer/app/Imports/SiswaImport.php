<?php

namespace App\Imports;

use App\Models\BantuanSiswa;
use App\Models\CitaCita;
use App\Models\CitaCitaSiswa;
use App\Models\Hobi;
use App\Models\HobiSiswa;
use App\Models\JenisBantuan;
use App\Models\Kelas;
use App\Models\MediaSosial;
use App\Models\OrangTua;
use App\Models\PrestasiMurid;
use App\Models\SaudaraTiri;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Wali;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;


class SiswaImport implements ToCollection, WithHeadingRow, WithMultipleSheets
{
    public array $errors        = [];
    public int   $successCount  = 0;
    public int   $updatedCount  = 0;
    public int   $skippedCount  = 0;
    public array $skippedDetail = [];
    public array $peringatan    = [];

    public function sheets(): array
    {
        return [0 => $this];
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $nisn = $this->ambil($row, ['nisn']);
            $nama = $this->ambil($row, ['nama_lengkap', 'nama']);

            if ($nisn === '' || $nama === '') {
                continue;
            }

            try {
                $status = DB::transaction(function () use ($row, $nisn, $nama, $index) {
                    $siswa = Siswa::where('nisn', $nisn)->first();
                    $isNew = !$siswa;

                    if ($isNew) {
                        $user = User::firstOrCreate(
                            ['username' => $nisn],
                            ['password' => $nisn, 'role' => 'siswa']
                        );
                    } else {
                        $user = $siswa->user;
                    }

                    $biodata = [
                        'user_id'           => $user->id,
                        'nisn'              => $nisn,
                        'nis'               => $this->str($this->ambil($row, ['nis'])),
                        'nama_lengkap'      => $nama,
                        'nama_panggilan'    => $this->str($this->ambil($row, ['nama_panggilan', 'panggilan'])),
                        'jenis_kelamin'     => $this->normalisasiJk($this->ambil($row, ['jenis_kelamin', 'jk', 'l_p', 'gender'])),
                        'tempat_lahir'      => $this->str($this->ambil($row, ['tempat_lahir'])),
                        'tanggal_lahir'     => $this->tanggal(
                            $this->ambilRaw($row, ['tanggal_lahir']),
                            ['baris' => $index + 2, 'nisn' => $nisn]
                        ),
                        'agama'             => $this->normalisasiAgama($this->ambil($row, ['agama', 'agm'])),
                        'golongan_darah'    => $this->str($this->ambil($row, ['golongan_darah'])),
                        'alamat_rumah'      => $this->str($this->ambil($row, ['alamat_tempat_tinggal', 'alamat_rumah', 'alamat'])),
                        'tinggal_bersama'   => $this->str($this->ambil($row, ['tinggal_bersama'])),
                        'anak_ke'           => $this->int($this->ambil($row, ['aku_anak_ke', 'anak_ke'])),
                        'warna_favorit'     => $this->str($this->ambil($row, [
                            'warna_favoritku_adalah',
                            'warna_favorit',
                        ])),
                        'makanan_favorit'   => $this->str($this->ambil($row, [
                            'makanan_favoritku_adalah',
                            'makanan_favorit',
                        ])),
                        'bahasa_keseharian' => $this->str($this->ambil($row, [
                            'aku_menggunakan_bahasa_keseharian',
                            'bahasa_keseharian',
                        ])),
                        'permainan_online'  => $this->str($this->ambil($row, [
                            'permainan_online_yang_aku_suka_adalah',
                            'permainan_online',
                        ])),
                        'permainan_offline' => $this->str($this->ambil($row, [
                            'permainan_offline_yang_aku_suka_adalah',
                            'permainan_offline',
                        ])),
                        'nilai_tka'         => $this->float($this->ambil($row, ['nilai_tka'])),
                        'catatan'           => $this->str($this->ambil($row, ['catatan'])),
                    ];

                    if ($isNew) {
                        $siswa = Siswa::create($biodata);
                    } else {

                        $updateData = array_filter($biodata, fn ($v) => $v !== null);
                        $siswa->update($updateData);
                    }

                    $this->simpanRiwayatKelas($siswa, $row);

                    $this->simpanHobi($siswa, $row);

                    $this->simpanCitaCita($siswa, $row);

                    $this->simpanMediaSosial($siswa, $row);

                    $this->simpanPrestasi($siswa, $row);

                    $this->simpanSaudaraTiri($siswa, $row);

                    $this->simpanBantuan($siswa, $row);

                    $this->simpanOrangTua($siswa, $row);

                    $this->simpanWali($siswa, $row);

                    return $isNew ? 'created' : 'updated';
                });

                if ($status === 'created') {
                    $this->successCount++;
                } else {
                    $this->updatedCount++;
                }
            } catch (\Throwable $e) {
                $this->errors[] = [
                    'baris' => $index + 2,
                    'nisn'  => $nisn,
                    'nama'  => $nama,
                    'pesan' => $e->getMessage(),
                ];
            }
        }
    }

    private function simpanRiwayatKelas(Siswa $siswa, $row): void
    {
        $kelasExcel = $this->ambil($row, ['kelas_x', 'kelas']);
        if ($kelasExcel === '') {
            return;
        }

        if (is_numeric($kelasExcel)) {
            $namaKelas = 'X-' . $kelasExcel;
        } else {
            $namaKelas = strtoupper(str_replace(' ', '-', $kelasExcel));
        }

        $kelas = Kelas::firstOrCreate(
            ['nama_kelas' => $namaKelas, 'tingkat' => 'X'],
            ['tahun_ajaran' => date('Y') . '/' . ((int) date('Y') + 1)]
        );

        $siswa->riwayatKelas()->updateOrCreate(
            ['kelas_id' => $kelas->id],
            [
                'tanggal_mulai'   => now()->format('Y-m-d'),
                'tanggal_selesai' => null,
                'keterangan'      => 'Import biodata siswa',
            ]
        );
    }

    private function simpanHobi(Siswa $siswa, $row): void
    {
        $teks = $this->ambil($row, ['hobi']);
        if ($this->kosongAtauTidakAda($teks)) {
            return;
        }

        foreach ($this->pecahDaftar($teks) as $item) {
            $hobi = Hobi::firstOrCreate(['nama_hobi' => $item]);
            HobiSiswa::firstOrCreate([
                'siswa_id' => $siswa->id,
                'hobi_id'  => $hobi->id,
            ]);
        }
    }

    private function simpanCitaCita(Siswa $siswa, $row): void
    {
        $teks = $this->ambil($row, ['cita_cita']);
        if ($this->kosongAtauTidakAda($teks)) {
            return;
        }

        foreach ($this->pecahDaftar($teks) as $idx => $item) {
            $cita = CitaCita::firstOrCreate(['nama_cita_cita' => $item]);
            CitaCitaSiswa::updateOrCreate(
                [
                    'siswa_id'     => $siswa->id,
                    'cita_cita_id' => $cita->id,
                ],
                ['prioritas' => $idx + 1]
            );
        }
    }

    private function simpanMediaSosial(Siswa $siswa, $row): void
    {
        $teks = $this->ambil($row, [
            'media_sosial_yang_aku_punya_adalah_ini_untuk_media_sosial',
            'media_sosial_yang_aku_punya_adalah',
            'media_sosial',
        ]);

        if ($this->kosongAtauTidakAda($teks)) {
            return;
        }

        foreach ($this->pecahDaftar($teks) as $item) {
            $jenis    = 'Lainnya';
            $username = $item;

            $platformList = [
                'Instagram' => ['instagram', 'ig'],
                'TikTok'    => ['tiktok', 'tt'],
                'WhatsApp'  => ['whatsapp', 'wa'],
                'YouTube'   => ['youtube', 'yt'],
                'X/Twitter' => ['twitter', 'x'],
                'Facebook'  => ['facebook', 'fb'],
            ];

            $lower = strtolower($item);
            foreach ($platformList as $namaPlatform => $keywords) {
                foreach ($keywords as $kw) {
                    if (preg_match('/\b' . preg_quote($kw, '/') . '\b/i', $lower)) {
                        $jenis = $namaPlatform;

                        if (preg_match('/[:\-]\s*(.+)$/', $item, $m)) {
                            $username = trim($m[1]);
                        } elseif (preg_match('/\(([^)]+)\)/', $item, $m)) {
                            $username = trim($m[1]);
                        }
                        break 2;
                    }
                }
            }

            MediaSosial::updateOrCreate(
                [
                    'siswa_id'    => $siswa->id,
                    'jenis_media' => $jenis,
                    'username'    => $username,
                ],
                ['nomor_wa' => $jenis === 'WhatsApp' ? $username : null]
            );
        }
    }

    private function simpanPrestasi(Siswa $siswa, $row): void
    {
        $teks = $this->ambil($row, [
            'prestasi_yang_pernah_aku_raih_saat_smp_adalah',
            'prestasi',
        ]);

        if ($this->kosongAtauTidakAda($teks)) {
            return;
        }

        foreach ($this->pecahDaftar($teks, false) as $item) {
            if ($this->kosongAtauTidakAda($item)) {
                continue;
            }

            $jenis = preg_match('/\b(olimpiade|osn|ksn|matematika|ipa|ips|cerdas cermat|akademik|karya ilmiah)\b/i', $item)
                ? 'Akademik'
                : 'Non-Akademik';

            $tingkat = 'SMP';
            if (preg_match('/\b(internasional|nasional|provinsi|kota|kabupaten|kecamatan|sekolah)\b/i', $item, $m)) {
                $tingkat = ucfirst(strtolower($m[1]));
            }

            PrestasiMurid::firstOrCreate(
                [
                    'siswa_id'       => $siswa->id,
                    'nama_kompetisi' => mb_substr($item, 0, 255),
                ],
                [
                    'jenis_prestasi' => $jenis,
                    'tingkat'        => $tingkat,
                ]
            );
        }
    }

    private function simpanSaudaraTiri(Siswa $siswa, $row): void
    {
        $teks = $this->ambil($row, ['punya_saudara_tiri', 'saudara_tiri']);
        if ($teks === '') {
            return;
        }

        $status = !$this->kosongAtauTidakAda($teks) ? 'Punya' : 'Tidak';

        SaudaraTiri::updateOrCreate(
            ['siswa_id' => $siswa->id],
            ['status' => $status]
        );
    }

    private function simpanBantuan(Siswa $siswa, $row): void
    {
        $teks = $this->ambil($row, [
            'saya_pernah_mendapatkan_bantuan_pemerintah_berupa',
            'bantuan_pemerintah',
            'jenis_bantuan',
        ]);

        if ($this->kosongAtauTidakAda($teks)) {
            return;
        }

        foreach ($this->pecahDaftar($teks) as $item) {
            if ($this->kosongAtauTidakAda($item)) {
                continue;
            }

            $jenis = JenisBantuan::firstOrCreate(
                ['nama_bantuan' => mb_substr($item, 0, 255)],
                ['keterangan' => 'Import data siswa']
            );

            BantuanSiswa::updateOrCreate(
                [
                    'siswa_id'         => $siswa->id,
                    'jenis_bantuan_id' => $jenis->id,
                ],
                ['aktif' => true]
            );
        }
    }

    private function simpanOrangTua(Siswa $siswa, $row): void
    {
        $namaAyah = $this->ambil($row, ['nama_ayah_kandung']);
        if (!$this->kosongAtauTidakAda($namaAyah)) {
            OrangTua::updateOrCreate(
                [
                    'siswa_id'        => $siswa->id,
                    'jenis_orang_tua' => 'ayah',
                ],
                [
                    'nama'        => $namaAyah,
                    'pekerjaan'   => $this->str($this->ambil($row, ['pekerjaan_ayah_kandung'])),
                    'penghasilan' => $this->nominalUang($this->ambil($row, [
                        'gaji_pendapatan_ayah_kandung_per_bulan',
                        'gaji_ayah_kandung',
                    ])),
                    'alamat'      => $this->str($this->ambil($row, ['alamat_ayah_kandung'])),
                    'kontak_wa'   => $this->str($this->ambil($row, ['no_hp_ayah_kandung'])),
                ]
            );
        }

        $namaIbu = $this->ambil($row, ['nama_ibu_kandung']);
        if (!$this->kosongAtauTidakAda($namaIbu)) {
            OrangTua::updateOrCreate(
                [
                    'siswa_id'        => $siswa->id,
                    'jenis_orang_tua' => 'ibu',
                ],
                [
                    'nama'        => $namaIbu,
                    'pekerjaan'   => $this->str($this->ambil($row, ['pekerjaan_ibu_kandung'])),
                    'penghasilan' => $this->nominalUang($this->ambil($row, [
                        'gaji_pendapatan_ibu_kandung_per_bulan',
                        'gaji_ibu_kandung',
                    ])),
                    'alamat'      => $this->str($this->ambil($row, ['alamat_ibu_kandung'])),
                    'kontak_wa'   => $this->str($this->ambil($row, ['no_hp_ibu_kandung'])),
                ]
            );
        }
    }

    private function simpanWali(Siswa $siswa, $row): void
    {
        $namaWali = $this->ambil($row, ['nama_wali']);
        if ($this->kosongAtauTidakAda($namaWali)) {
            return;
        }

        Wali::updateOrCreate(
            ['siswa_id' => $siswa->id],
            [
                'nama'      => $namaWali,
                'alamat'    => $this->str($this->ambil($row, ['alamat_wali'])),
                'kontak_wa' => $this->str($this->ambil($row, ['no_hp_wali'])),
            ]
        );
    }

    private function ambilRaw($row, array $kunciList)
    {
        $arr = $row instanceof Collection ? $row->all() : (array) $row;

        foreach ($kunciList as $kunci) {
            if (array_key_exists($kunci, $arr) && $arr[$kunci] !== null && trim((string) $arr[$kunci]) !== '') {
                return $arr[$kunci];
            }
        }

        foreach ($kunciList as $kunci) {
            foreach ($arr as $colKey => $val) {
                $normKey = trim(preg_replace('/_+/', '_', strtolower((string) $colKey)), '_');
                if ($normKey === $kunci || str_starts_with($normKey, $kunci . '_')) {
                    if ($val !== null && trim((string) $val) !== '') {
                        return $val;
                    }
                }
            }
        }

        return null;
    }

    private function ambil($row, array $kunciList): string
    {
        $v = $this->ambilRaw($row, $kunciList);
        if (is_float($v) && floor($v) == $v) {
            $v = (int) $v;
        }
        return trim((string) ($v ?? ''));
    }

    private function kosongAtauTidakAda(?string $teks): bool
    {
        if ($teks === null) {
            return true;
        }
        $clean = strtolower(trim(preg_replace('/[^a-z0-9\s]/i', '', $teks)));
        if ($clean === '' || $clean === '0') {
            return true;
        }

        $negatif = [
            'tidak', 'tidak ada', 'tidak punya', 'tidak pernah', 'belum', 'belum ada',
            'belum pernah', 'nihil', 'kosong', 'none', 'tdk', 'tdk ada', 'gak ada', 'enggak',
        ];

        return in_array($clean, $negatif, true);
    }

    private function pecahDaftar(string $teks, bool $pecahKoma = true): array
    {
        $pattern = $pecahKoma ? '/[,;\/\n]+|\s+dan\s+/i' : '/[;\n]+/';
        $items   = [];
        foreach (preg_split($pattern, $teks) as $bagian) {
            $b = trim($bagian, " \t\n\r\0\x0B.-");
            if ($b !== '' && !$this->kosongAtauTidakAda($b)) {
                $items[] = $b;
            }
        }
        return array_values(array_unique($items));
    }

    private function nominalUang(string $teks): ?float
    {
        if ($this->kosongAtauTidakAda($teks)) {
            return null;
        }

        if (preg_match('/(\d[\d\.]*)/', $teks, $m)) {
            $angka = str_replace('.', '', $m[1]);
            if (is_numeric($angka)) {
                $val = (float) $angka;
                if ($val < 1000 && str_contains(strtolower($teks), 'juta') || str_contains(strtolower($teks), 'jt')) {
                    $val *= 1000000;
                }
                return $val;
            }
        }

        return null;
    }

    private function tanggal($v, ?array $konteks = null): ?string
    {
        if ($v instanceof \DateTimeInterface) {
            return Carbon::instance($v)->format('Y-m-d');
        }

        $raw = trim((string) ($v ?? ''));
        if ($raw === '') {
            return null;
        }

        try {
            if (is_numeric($raw)) {
                $num = (float) $raw;
                if (preg_match('/^\d{8}$/', $raw)) {
                    return Carbon::createFromFormat('Ymd', $raw)->format('Y-m-d');
                }
                if ($num >= 1 && $num < 80000) {
                    return Carbon::instance(ExcelDate::excelToDateTimeObject($num))->format('Y-m-d');
                }
                throw new \InvalidArgumentException('angka tidak dikenali');
            }

            $bulan = [
                'januari' => 'january', 'februari' => 'february', 'maret' => 'march',
                'april' => 'april', 'mei' => 'may', 'juni' => 'june', 'juli' => 'july',
                'agustus' => 'august', 'september' => 'september', 'oktober' => 'october',
                'november' => 'november', 'desember' => 'december',
                'agu' => 'aug', 'okt' => 'oct', 'des' => 'dec', 'ags' => 'aug',
            ];
            $teks = strtolower($raw);
            $teks = preg_replace('/\s+/', ' ', strtr($teks, $bulan));

            if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $teks, $m)) {
                return $this->tanggalValid((int) $m[3], (int) $m[2], (int) $m[1]);
            }
            if (preg_match('/^(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})/', $teks, $m)) {
                return $this->tanggalValid((int) $m[1], (int) $m[2], (int) $m[3]);
            }

            return Carbon::parse($teks)->format('Y-m-d');
        } catch (\Throwable) {
            $this->peringatan[] = ($konteks ?? []) + ['pesan' => "Tanggal lahir '{$raw}' tidak terbaca, dikosongkan"];
            return null;
        }
    }

    private function tanggalValid(int $tahun, int $bulan, int $hari): string
    {
        if (!checkdate($bulan, $hari, $tahun) || $tahun < 1900) {
            throw new \InvalidArgumentException('tanggal tidak valid');
        }

        return sprintf('%04d-%02d-%02d', $tahun, $bulan, $hari);
    }

    private function str($v): ?string
    {
        $v = trim((string) ($v ?? ''));
        return $v === '' ? null : $v;
    }

    private function normalisasiJk(string $v): ?string
    {
        $jk = strtoupper(trim($v));
        if ($jk === '') {
            return null;
        }
        if (in_array($jk, ['L', 'LAKI-LAKI', 'LAKI LAKI', 'PRIA', 'LK'], true) || str_starts_with($jk, 'L')) {
            return 'L';
        }
        if (in_array($jk, ['P', 'PEREMPUAN', 'WANITA', 'PR'], true) || str_starts_with($jk, 'P')) {
            return 'P';
        }
        return null;
    }

    private function normalisasiAgama(string $v): ?string
    {
        $agm = trim($v);
        if ($agm === '') {
            return null;
        }
        $peta = [
            'ISL' => 'Islam', 'ISLAM' => 'Islam',
            'KRIS' => 'Kristen', 'KRISTEN' => 'Kristen', 'PROTESTAN' => 'Kristen', 'KP' => 'Kristen',
            'KAT' => 'Katolik', 'KATH' => 'Katolik', 'KATOLIK' => 'Katolik', 'KATHOLIK' => 'Katolik',
            'HIN' => 'Hindu', 'HINDU' => 'Hindu',
            'BUD' => 'Buddha', 'BUDHA' => 'Buddha', 'BUDDHA' => 'Buddha',
            'KONG' => 'Konghucu', 'KONGHUCU' => 'Konghucu',
        ];
        return $peta[strtoupper($agm)] ?? ucfirst(strtolower($agm));
    }

    private function int($v): ?int
    {
        $v = trim((string) ($v ?? ''));
        if (is_numeric($v)) {
            return (int) $v;
        }
        if (preg_match('/(\d+)/', $v, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    private function float($v): ?float
    {
        $v = trim((string) ($v ?? ''));
        return is_numeric($v) ? (float) $v : null;
    }
}
