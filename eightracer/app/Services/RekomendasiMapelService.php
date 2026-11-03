<?php

namespace App\Services;

use App\Models\AsesmenMultipleIntelligence;
use App\Models\AsesmenRiasec;
use App\Models\MataPelajaran;
use App\Models\MinatProdiSiswa;
use App\Models\NilaiRapor;
use App\Models\RekomendasiMapel;
use App\Models\Siswa;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RekomendasiMapelService
{
    private const BOBOT_PRODI  = 0.35;
    private const BOBOT_NILAI  = 0.35;
    private const BOBOT_RIASEC = 0.15;
    private const BOBOT_MI     = 0.15;

    private const KURIKULUM_PRODI = [
        // === RUMPUN ILMU: HUMANIORA (1 - 5) ===
        [
            'no'                      => 1,
            'kelompok_prodi'          => 'Seni',
            'rumpun_ilmu'             => 'Humaniora',
            'keywords'                => ['seni', 'musik', 'tari', 'teater', 'karawitan', 'pedalangan', 'kriyah', 'seni rupa', 'film', 'televisi'],
            'mapel_kurikulum_resmi'   => 'Seni Budaya',
            'mapel_pilihan_utama'     => ['Bahasa Indonesia Tingkat Lanjut', 'Bahasa Inggris Tingkat Lanjut'],
            'mapel_pilihan_pendukung' => ['Sosiologi', 'Bahasa Jepang'],
        ],
        [
            'no'                      => 2,
            'kelompok_prodi'          => 'Sejarah',
            'rumpun_ilmu'             => 'Humaniora',
            'keywords'                => ['sejarah', 'arkeologi', 'museologi'],
            'mapel_kurikulum_resmi'   => 'Sejarah',
            'mapel_pilihan_utama'     => ['Sosiologi', 'Bahasa Indonesia Tingkat Lanjut'],
            'mapel_pilihan_pendukung' => ['Geografi', 'Bahasa Inggris Tingkat Lanjut'],
        ],
        [
            'no'                      => 3,
            'kelompok_prodi'          => 'Linguistik',
            'rumpun_ilmu'             => 'Humaniora',
            'keywords'                => ['linguistik', 'ilmu bahasa', 'penerjemahan'],
            'mapel_kurikulum_resmi'   => 'Bahasa Indonesia dan/atau Bahasa Inggris',
            'mapel_pilihan_utama'     => ['Bahasa Indonesia Tingkat Lanjut', 'Bahasa Inggris Tingkat Lanjut'],
            'mapel_pilihan_pendukung' => ['Bahasa Jepang', 'Sosiologi'],
        ],
        [
            'no'                      => 4,
            'kelompok_prodi'          => 'Susastra atau Sastra',
            'rumpun_ilmu'             => 'Humaniora',
            'keywords'                => ['sastra', 'susastra', 'sastra indonesia', 'sastra inggris', 'sastra jepang', 'bahasa dan kebudayaan', 'bahasa jepang', 'bahasa asing'],
            'mapel_kurikulum_resmi'   => 'Bahasa Indonesia dan/atau Bahasa Asing yang Relevan',
            'mapel_pilihan_utama'     => ['Bahasa Indonesia Tingkat Lanjut', 'Bahasa Inggris Tingkat Lanjut', 'Bahasa Jepang'],
            'mapel_pilihan_pendukung' => ['Sosiologi'],
        ],
        [
            'no'                      => 5,
            'kelompok_prodi'          => 'Filsafat',
            'rumpun_ilmu'             => 'Humaniora',
            'keywords'                => ['filsafat'],
            'mapel_kurikulum_resmi'   => 'Sosiologi',
            'mapel_pilihan_utama'     => ['Sosiologi'],
            'mapel_pilihan_pendukung' => ['Bahasa Indonesia Tingkat Lanjut', 'Bahasa Inggris Tingkat Lanjut', 'Ekonomi'],
        ],

        // === RUMPUN ILMU: ILMU SOSIAL (6 - 9) ===
        [
            'no'                      => 6,
            'kelompok_prodi'          => 'Sosial',
            'rumpun_ilmu'             => 'Ilmu Sosial',
            'keywords'                => ['sosiologi', 'antropologi', 'kesejahteraan sosial', 'hubungan internasional', 'ilmu politik', 'politik', 'pembangunan sosial', 'kriminologi', 'sosial'],
            'mapel_kurikulum_resmi'   => 'Sosiologi',
            'mapel_pilihan_utama'     => ['Sosiologi'],
            'mapel_pilihan_pendukung' => ['Bahasa Inggris Tingkat Lanjut', 'Ekonomi', 'Bahasa Indonesia Tingkat Lanjut', 'Geografi'],
        ],
        [
            'no'                      => 7,
            'kelompok_prodi'          => 'Ekonomi',
            'rumpun_ilmu'             => 'Ilmu Sosial',
            'keywords'                => ['ekonomi', 'ekonomi pembangunan', 'ekonomi islam', 'ekonomi syariah', 'ilmu ekonomi'],
            'mapel_kurikulum_resmi'   => 'Ekonomi dan/atau Matematika',
            'mapel_pilihan_utama'     => ['Ekonomi', 'Matematika Tingkat Lanjut'],
            'mapel_pilihan_pendukung' => ['Sosiologi', 'Bahasa Inggris Tingkat Lanjut'],
        ],
        [
            'no'                      => 8,
            'kelompok_prodi'          => 'Pertahanan',
            'rumpun_ilmu'             => 'Ilmu Sosial',
            'keywords'                => ['pertahanan', 'studi pertahanan', 'keamanan nasional'],
            'mapel_kurikulum_resmi'   => 'Pendidikan Pancasila',
            'mapel_pilihan_utama'     => ['Sosiologi', 'Geografi'],
            'mapel_pilihan_pendukung' => ['Bahasa Inggris Tingkat Lanjut', 'Ekonomi'],
        ],
        [
            'no'                      => 9,
            'kelompok_prodi'          => 'Psikologi',
            'rumpun_ilmu'             => 'Ilmu Sosial',
            'keywords'                => ['psikologi'],
            'mapel_kurikulum_resmi'   => 'Sosiologi dan/atau Matematika',
            'mapel_pilihan_utama'     => ['Sosiologi', 'Matematika Tingkat Lanjut'],
            'mapel_pilihan_pendukung' => ['Biologi', 'Bahasa Inggris Tingkat Lanjut'],
        ],

        // === RUMPUN ILMU: ILMU ALAM (10 - 16) ===
        [
            'no'                      => 10,
            'kelompok_prodi'          => 'Kimia',
            'rumpun_ilmu'             => 'Ilmu Alam',
            'keywords'                => ['kimia', 'sains kimia'],
            'mapel_kurikulum_resmi'   => 'Kimia',
            'mapel_pilihan_utama'     => ['Kimia'],
            'mapel_pilihan_pendukung' => ['Biologi', 'Fisika', 'Matematika Tingkat Lanjut'],
        ],
        [
            'no'                      => 11,
            'kelompok_prodi'          => 'Ilmu atau Sains Kebumian',
            'rumpun_ilmu'             => 'Ilmu Alam',
            'keywords'                => ['kebumian', 'geofisika', 'geologi', 'meteorologi', 'oseanografi', 'klimatologi'],
            'mapel_kurikulum_resmi'   => 'Fisika dan/atau Matematika Tingkat Lanjut',
            'mapel_pilihan_utama'     => ['Fisika', 'Matematika Tingkat Lanjut'],
            'mapel_pilihan_pendukung' => ['Geografi', 'Kimia'],
        ],
        [
            'no'                      => 12,
            'kelompok_prodi'          => 'Ilmu atau Sains Kelautan',
            'rumpun_ilmu'             => 'Ilmu Alam',
            'keywords'                => ['kelautan', 'ilmu kelautan', 'sains kelautan'],
            'mapel_kurikulum_resmi'   => 'Biologi',
            'mapel_pilihan_utama'     => ['Biologi'],
            'mapel_pilihan_pendukung' => ['Kimia', 'Geografi', 'Fisika'],
        ],
        [
            'no'                      => 13,
            'kelompok_prodi'          => 'Biologi',
            'rumpun_ilmu'             => 'Ilmu Alam',
            'keywords'                => ['biologi', 'mikrobiologi'],
            'mapel_kurikulum_resmi'   => 'Biologi',
            'mapel_pilihan_utama'     => ['Biologi'],
            'mapel_pilihan_pendukung' => ['Kimia', 'Matematika Tingkat Lanjut', 'Fisika'],
        ],
        [
            'no'                      => 14,
            'kelompok_prodi'          => 'Biofisika',
            'rumpun_ilmu'             => 'Ilmu Alam',
            'keywords'                => ['biofisika'],
            'mapel_kurikulum_resmi'   => 'Fisika',
            'mapel_pilihan_utama'     => ['Fisika'],
            'mapel_pilihan_pendukung' => ['Biologi', 'Matematika Tingkat Lanjut', 'Kimia'],
        ],
        [
            'no'                      => 15,
            'kelompok_prodi'          => 'Fisika',
            'rumpun_ilmu'             => 'Ilmu Alam',
            'keywords'                => ['fisika', 'instrumentasi', 'nuklir'],
            'mapel_kurikulum_resmi'   => 'Fisika',
            'mapel_pilihan_utama'     => ['Fisika'],
            'mapel_pilihan_pendukung' => ['Matematika Tingkat Lanjut', 'Kimia', 'Informatika'],
        ],
        [
            'no'                      => 16,
            'kelompok_prodi'          => 'Astronomi',
            'rumpun_ilmu'             => 'Ilmu Alam',
            'keywords'                => ['astronomi', 'astrofisika'],
            'mapel_kurikulum_resmi'   => 'Fisika dan/atau Matematika Tingkat Lanjut',
            'mapel_pilihan_utama'     => ['Fisika', 'Matematika Tingkat Lanjut'],
            'mapel_pilihan_pendukung' => ['Informatika', 'Kimia'],
        ],

        // === RUMPUN ILMU: ILMU FORMAL (17 - 19) ===
        [
            'no'                      => 17,
            'kelompok_prodi'          => 'Komputer',
            'rumpun_ilmu'             => 'Ilmu Formal',
            'keywords'                => ['komputer', 'informatika', 'teknik informatika', 'ilmu komputer', 'sistem informasi', 'teknologi informasi', 'rekayasa perangkat lunak', 'rpl', 'keamanan siber', 'cyber', 'kecerdasan buatan', 'ai', 'multimedia'],
            'mapel_kurikulum_resmi'   => 'Matematika Tingkat Lanjut',
            'mapel_pilihan_utama'     => ['Matematika Tingkat Lanjut', 'Informatika'],
            'mapel_pilihan_pendukung' => ['Fisika', 'Bahasa Inggris Tingkat Lanjut'],
        ],
        [
            'no'                      => 18,
            'kelompok_prodi'          => 'Logika',
            'rumpun_ilmu'             => 'Ilmu Formal',
            'keywords'                => ['logika'],
            'mapel_kurikulum_resmi'   => 'Matematika Tingkat Lanjut',
            'mapel_pilihan_utama'     => ['Matematika Tingkat Lanjut'],
            'mapel_pilihan_pendukung' => ['Informatika', 'Sosiologi', 'Fisika'],
        ],
        [
            'no'                      => 19,
            'kelompok_prodi'          => 'Matematika',
            'rumpun_ilmu'             => 'Ilmu Formal',
            'keywords'                => ['matematika', 'statistika', 'aktuaria'],
            'mapel_kurikulum_resmi'   => 'Matematika Tingkat Lanjut',
            'mapel_pilihan_utama'     => ['Matematika Tingkat Lanjut'],
            'mapel_pilihan_pendukung' => ['Informatika', 'Fisika', 'Ekonomi'],
        ],

        // === RUMPUN ILMU: ILMU TERAPAN (20 - 59) ===
        [
            'no'                      => 20,
            'kelompok_prodi'          => 'Ilmu dan Sains Pertanian',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'keywords'                => ['pertanian', 'agroteknologi', 'agribisnis', 'agronomi', 'ilmu tanah', 'proteksi tanaman', 'hortikultura', 'penyuluhan pertanian'],
            'mapel_kurikulum_resmi'   => 'Biologi',
            'mapel_pilihan_utama'     => ['Biologi'],
            'mapel_pilihan_pendukung' => ['Kimia', 'Ekonomi', 'Geografi'],
        ],
        [
            'no'                      => 21,
            'kelompok_prodi'          => 'Peternakan',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'keywords'                => ['peternakan', 'nutrisi ternak'],
            'mapel_kurikulum_resmi'   => 'Biologi',
            'mapel_pilihan_utama'     => ['Biologi'],
            'mapel_pilihan_pendukung' => ['Kimia', 'Ekonomi', 'Matematika Tingkat Lanjut'],
        ],
        [
            'no'                      => 22,
            'kelompok_prodi'          => 'Ilmu atau Sains Perikanan',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'keywords'                => ['perikanan', 'akuakultur', 'budidaya perairan', 'manajemen sumberdaya perairan'],
            'mapel_kurikulum_resmi'   => 'Biologi',
            'mapel_pilihan_utama'     => ['Biologi'],
            'mapel_pilihan_pendukung' => ['Kimia', 'Geografi', 'Ekonomi'],
        ],
        [
            'no'                      => 23,
            'kelompok_prodi'          => 'Arsitektur',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'keywords'                => ['arsitektur', 'arsitektur lanskap'],
            'mapel_kurikulum_resmi'   => 'Matematika dan/atau Fisika',
            'mapel_pilihan_utama'     => ['Fisika', 'Matematika Tingkat Lanjut'],
            'mapel_pilihan_pendukung' => ['Informatika', 'Geografi'],
        ],
        [
            'no'                      => 24,
            'kelompok_prodi'          => 'Perencanaan Wilayah',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'keywords'                => ['perencanaan wilayah', 'pwk', 'planologi', 'perencanaan wilayah dan kota'],
            'mapel_kurikulum_resmi'   => 'Ekonomi dan/atau Matematika',
            'mapel_pilihan_utama'     => ['Ekonomi', 'Matematika Tingkat Lanjut', 'Geografi'],
            'mapel_pilihan_pendukung' => ['Sosiologi'],
        ],
        [
            'no'                      => 25,
            'kelompok_prodi'          => 'Desain',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'keywords'                => ['desain', 'dkv', 'desain komunikasi visual', 'desain interior', 'desain produk', 'animasi'],
            'mapel_kurikulum_resmi'   => 'Seni Budaya dan/atau Matematika',
            'mapel_pilihan_utama'     => ['Matematika Tingkat Lanjut', 'Informatika'],
            'mapel_pilihan_pendukung' => ['Bahasa Inggris Tingkat Lanjut', 'Ekonomi'],
        ],
        [
            'no'                      => 26,
            'kelompok_prodi'          => 'Ilmu atau Sains Akuntansi',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'keywords'                => ['akuntansi', 'perpajakan', 'audit'],
            'mapel_kurikulum_resmi'   => 'Ekonomi',
            'mapel_pilihan_utama'     => ['Ekonomi'],
            'mapel_pilihan_pendukung' => ['Matematika Tingkat Lanjut', 'Bahasa Inggris Tingkat Lanjut', 'Sosiologi'],
        ],
        [
            'no'                      => 27,
            'kelompok_prodi'          => 'Ilmu atau Sains Manajemen',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'keywords'                => ['manajemen', 'manajemen keuangan', 'manajemen pemasaran', 'perbankan', 'keuangan'],
            'mapel_kurikulum_resmi'   => 'Ekonomi',
            'mapel_pilihan_utama'     => ['Ekonomi'],
            'mapel_pilihan_pendukung' => ['Matematika Tingkat Lanjut', 'Bahasa Inggris Tingkat Lanjut', 'Sosiologi'],
        ],
        [
            'no'                      => 28,
            'kelompok_prodi'          => 'Logistik',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'keywords'                => ['logistik', 'manajemen logistik', 'rantai pasok', 'supply chain'],
            'mapel_kurikulum_resmi'   => 'Ekonomi',
            'mapel_pilihan_utama'     => ['Ekonomi'],
            'mapel_pilihan_pendukung' => ['Matematika Tingkat Lanjut', 'Geografi', 'Informatika'],
        ],
        [
            'no'                      => 29,
            'kelompok_prodi'          => 'Administrasi Bisnis',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'keywords'                => ['administrasi bisnis', 'administrasi niaga', 'administrasi perkantoran', 'kesekretariatan'],
            'mapel_kurikulum_resmi'   => 'Ekonomi',
            'mapel_pilihan_utama'     => ['Ekonomi'],
            'mapel_pilihan_pendukung' => ['Sosiologi', 'Bahasa Inggris Tingkat Lanjut', 'Matematika Tingkat Lanjut'],
        ],
        [
            'no'                      => 30,
            'kelompok_prodi'          => 'Bisnis',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'keywords'                => ['bisnis', 'bisnis digital', 'kewirausahaan', 'bisnis internasional', 'perdagangan internasional'],
            'mapel_kurikulum_resmi'   => 'Ekonomi',
            'mapel_pilihan_utama'     => ['Ekonomi'],
            'mapel_pilihan_pendukung' => ['Bahasa Inggris Tingkat Lanjut', 'Informatika', 'Matematika Tingkat Lanjut'],
        ],
        [
            'no'                      => 31,
            'kelompok_prodi'          => 'Ilmu atau Sains Komunikasi',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'keywords'                => ['komunikasi', 'ilmu komunikasi', 'jurnalistik', 'hubungan masyarakat', 'humas', 'public relations', 'penyiaran', 'broadcasting', 'media'],
            'mapel_kurikulum_resmi'   => 'Sosiologi',
            'mapel_pilihan_utama'     => ['Sosiologi'],
            'mapel_pilihan_pendukung' => ['Bahasa Indonesia Tingkat Lanjut', 'Bahasa Inggris Tingkat Lanjut', 'Ekonomi'],
        ],
        [
            'no'                      => 32,
            'kelompok_prodi'          => 'Pendidikan',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'keywords'                => ['pendidikan', 'pgsd', 'pgpaud', 'bimbingan konseling', 'keguruan', 'teknologi pendidikan'],
            'mapel_kurikulum_resmi'   => 'Paling banyak 1 mata pelajaran pendukung yang relevan dengan program studi kependidikannya',
            'mapel_pilihan_utama'     => ['Sosiologi', 'Bahasa Indonesia Tingkat Lanjut'],
            'mapel_pilihan_pendukung' => ['Bahasa Inggris Tingkat Lanjut', 'Biologi', 'Matematika Tingkat Lanjut'],
        ],
        [
            'no'                      => 33,
            'kelompok_prodi'          => 'Teknik atau Rekayasa',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'keywords'                => ['teknik', 'rekayasa', 'teknik mesin', 'teknik sipil', 'teknik elektro', 'teknik industri', 'teknik kimia', 'teknik lingkungan', 'teknik perkapalan', 'teknik dirgantara', 'teknik otomotif', 'teknik geodesi', 'teknik pertambangan', 'teknik perminyakan', 'teknik material', 'metalurgi', 'teknik biomedis', 'mekatronika', 'robotika'],
            'mapel_kurikulum_resmi'   => 'Fisika dan Matematika Tingkat Lanjut; atau Kimia dan Matematika Tingkat Lanjut',
            'mapel_pilihan_utama'     => ['Fisika', 'Matematika Tingkat Lanjut', 'Kimia'],
            'mapel_pilihan_pendukung' => ['Informatika'],
        ],
        [
            'no'                      => 34,
            'kelompok_prodi'          => 'Ilmu atau Sains Lingkungan',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'keywords'                => ['ilmu lingkungan', 'sains lingkungan', 'manajemen lingkungan'],
            'mapel_kurikulum_resmi'   => 'Biologi',
            'mapel_pilihan_utama'     => ['Biologi'],
            'mapel_pilihan_pendukung' => ['Kimia', 'Geografi', 'Matematika Tingkat Lanjut'],
        ],
        [
            'no'                      => 35,
            'kelompok_prodi'          => 'Kehutanan',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'keywords'                => ['kehutanan', 'manajemen hutan', 'silvikultur', 'teknologi hasil hutan'],
            'mapel_kurikulum_resmi'   => 'Biologi',
            'mapel_pilihan_utama'     => ['Biologi'],
            'mapel_pilihan_pendukung' => ['Kimia', 'Geografi', 'Ekonomi'],
        ],
        [
            'no'                      => 36,
            'kelompok_prodi'          => 'Ilmu atau Sains Kedokteran',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'keywords'                => ['kedokteran', 'pendidikan dokter', 'dokter', 'sains kedokteran', 'ilmu kedokteran'],
            'mapel_kurikulum_resmi'   => 'Biologi dan Kimia',
            'mapel_pilihan_utama'     => ['Biologi', 'Kimia'],
            'mapel_pilihan_pendukung' => ['Matematika Tingkat Lanjut', 'Fisika', 'Bahasa Inggris Tingkat Lanjut'],
        ],
        [
            'no'                      => 37,
            'kelompok_prodi'          => 'Ilmu atau Sains Kedokteran Gigi',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'keywords'                => ['kedokteran gigi', 'pendidikan dokter gigi', 'dokter gigi'],
            'mapel_kurikulum_resmi'   => 'Biologi dan Kimia',
            'mapel_pilihan_utama'     => ['Biologi', 'Kimia'],
            'mapel_pilihan_pendukung' => ['Matematika Tingkat Lanjut', 'Fisika', 'Bahasa Inggris Tingkat Lanjut'],
        ],
        [
            'no'                      => 38,
            'kelompok_prodi'          => 'Ilmu atau Sains Veteriner',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'keywords'                => ['veteriner', 'kedokteran hewan', 'dokter hewan'],
            'mapel_kurikulum_resmi'   => 'Biologi dan Kimia',
            'mapel_pilihan_utama'     => ['Biologi', 'Kimia'],
            'mapel_pilihan_pendukung' => ['Matematika Tingkat Lanjut', 'Fisika', 'Bahasa Inggris Tingkat Lanjut'],
        ],
        [
            'no'                      => 39,
            'kelompok_prodi'          => 'Ilmu Farmasi',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'keywords'                => ['farmasi', 'apoteker', 'sains farmasi', 'farmasi klinik'],
            'mapel_kurikulum_resmi'   => 'Biologi dan Kimia',
            'mapel_pilihan_utama'     => ['Biologi', 'Kimia'],
            'mapel_pilihan_pendukung' => ['Matematika Tingkat Lanjut', 'Fisika', 'Bahasa Inggris Tingkat Lanjut'],
        ],
        [
            'no'                      => 40,
            'kelompok_prodi'          => 'Ilmu atau Sains Gizi',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'keywords'                => ['gizi', 'ilmu gizi', 'nutrisi', 'dietetika'],
            'mapel_kurikulum_resmi'   => 'Biologi dan Kimia',
            'mapel_pilihan_utama'     => ['Biologi', 'Kimia'],
            'mapel_pilihan_pendukung' => ['Matematika Tingkat Lanjut', 'Bahasa Inggris Tingkat Lanjut'],
        ],
        [
            'no'                      => 41,
            'kelompok_prodi'          => 'Kesehatan Masyarakat',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'keywords'                => ['kesehatan masyarakat', 'kesmas', 'epidemiologi', 'keselamatan dan kesehatan kerja', 'k3', 'kesehatan lingkungan'],
            'mapel_kurikulum_resmi'   => 'Biologi',
            'mapel_pilihan_utama'     => ['Biologi'],
            'mapel_pilihan_pendukung' => ['Kimia', 'Sosiologi', 'Matematika Tingkat Lanjut'],
        ],
        [
            'no'                      => 42,
            'kelompok_prodi'          => 'Kebidanan',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'keywords'                => ['kebidanan', 'bidan'],
            'mapel_kurikulum_resmi'   => 'Biologi',
            'mapel_pilihan_utama'     => ['Biologi'],
            'mapel_pilihan_pendukung' => ['Kimia', 'Sosiologi', 'Bahasa Inggris Tingkat Lanjut'],
        ],
        [
            'no'                      => 43,
            'kelompok_prodi'          => 'Keperawatan',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'keywords'                => ['keperawatan', 'perawat', 'ners'],
            'mapel_kurikulum_resmi'   => 'Biologi',
            'mapel_pilihan_utama'     => ['Biologi'],
            'mapel_pilihan_pendukung' => ['Kimia', 'Sosiologi', 'Bahasa Inggris Tingkat Lanjut'],
        ],
        [
            'no'                      => 44,
            'kelompok_prodi'          => 'Kesehatan',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'keywords'                => ['kesehatan', 'fisioterapi', 'analis kesehatan', 'teknologi laboratorium medis', 'radiologi', 'rekam medis', 'okupasi terapi', 'akupunktur'],
            'mapel_kurikulum_resmi'   => 'Biologi',
            'mapel_pilihan_utama'     => ['Biologi'],
            'mapel_pilihan_pendukung' => ['Kimia', 'Fisika', 'Matematika Tingkat Lanjut'],
        ],
        [
            'no'                      => 45,
            'kelompok_prodi'          => 'Ilmu atau Sains Informasi',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'keywords'                => ['sains informasi', 'ilmu informasi', 'perpustakaan', 'kearsipan'],
            'mapel_kurikulum_resmi'   => 'Matematika Tingkat Lanjut',
            'mapel_pilihan_utama'     => ['Matematika Tingkat Lanjut', 'Informatika'],
            'mapel_pilihan_pendukung' => ['Bahasa Inggris Tingkat Lanjut', 'Sosiologi'],
        ],
        [
            'no'                      => 46,
            'kelompok_prodi'          => 'Hukum',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'keywords'                => ['hukum', 'ilmu hukum', 'hukum tata negara', 'hukum bisnis', 'syariah'],
            'mapel_kurikulum_resmi'   => 'Sosiologi dan/atau Pendidikan Pancasila',
            'mapel_pilihan_utama'     => ['Sosiologi'],
            'mapel_pilihan_pendukung' => ['Ekonomi', 'Bahasa Indonesia Tingkat Lanjut', 'Bahasa Inggris Tingkat Lanjut'],
        ],
        [
            'no'                      => 47,
            'kelompok_prodi'          => 'Ilmu atau Sains Militer',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'keywords'                => ['militer', 'sains militer', 'akademi militer', 'akmil', 'akpol', 'kepolisian'],
            'mapel_kurikulum_resmi'   => 'Sosiologi',
            'mapel_pilihan_utama'     => ['Sosiologi'],
            'mapel_pilihan_pendukung' => ['Fisika', 'Matematika Tingkat Lanjut', 'Geografi'],
        ],
        [
            'no'                      => 48,
            'kelompok_prodi'          => 'Urusan Publik',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'keywords'                => ['urusan publik', 'administrasi publik', 'administrasi negara', 'kebijakan publik', 'ilmu pemerintahan', 'pemerintahan', 'manajemen publik'],
            'mapel_kurikulum_resmi'   => 'Sosiologi',
            'mapel_pilihan_utama'     => ['Sosiologi'],
            'mapel_pilihan_pendukung' => ['Ekonomi', 'Bahasa Indonesia Tingkat Lanjut', 'Geografi'],
        ],
        [
            'no'                      => 49,
            'kelompok_prodi'          => 'Ilmu atau Sains Keolahragaan',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'keywords'                => ['keolahragaan', 'ilmu keolahragaan', 'olahraga', 'kepelatihan olahraga', 'pendidikan jasmani'],
            'mapel_kurikulum_resmi'   => 'PJOK dan/atau Biologi',
            'mapel_pilihan_utama'     => ['Biologi'],
            'mapel_pilihan_pendukung' => ['Kimia', 'Sosiologi', 'Fisika'],
        ],
        [
            'no'                      => 50,
            'kelompok_prodi'          => 'Pariwisata',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'keywords'                => ['pariwisata', 'perhotelan', 'destinasi pariwisata', 'perjalanan wisata', 'hospitality'],
            'mapel_kurikulum_resmi'   => 'Ekonomi',
            'mapel_pilihan_utama'     => ['Ekonomi'],
            'mapel_pilihan_pendukung' => ['Bahasa Inggris Tingkat Lanjut', 'Bahasa Jepang', 'Geografi', 'Sosiologi'],
        ],
        [
            'no'                      => 51,
            'kelompok_prodi'          => 'Transportasi',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'keywords'                => ['transportasi', 'manajemen transportasi', 'perkeretaapian', 'pelayaran', 'penerbangan'],
            'mapel_kurikulum_resmi'   => 'Matematika Tingkat Lanjut',
            'mapel_pilihan_utama'     => ['Matematika Tingkat Lanjut'],
            'mapel_pilihan_pendukung' => ['Fisika', 'Geografi', 'Ekonomi'],
        ],
        [
            'no'                      => 52,
            'kelompok_prodi'          => 'Bioteknologi, Biokewirausahaan, Bioinformatika',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'keywords'                => ['bioteknologi', 'biokewirausahaan', 'bioinformatika', 'rekayasa hayati'],
            'mapel_kurikulum_resmi'   => 'Biologi dan/atau Matematika',
            'mapel_pilihan_utama'     => ['Biologi', 'Matematika Tingkat Lanjut'],
            'mapel_pilihan_pendukung' => ['Kimia', 'Informatika'],
        ],
        [
            'no'                      => 53,
            'kelompok_prodi'          => 'Geografi, Geografi Lingkungan, Sains Informasi Geografi',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'keywords'                => ['geografi', 'geografi lingkungan', 'sains informasi geografi', 'sig', 'kartografi', 'penginderaan jauh'],
            'mapel_kurikulum_resmi'   => 'Geografi dan/atau Matematika',
            'mapel_pilihan_utama'     => ['Geografi', 'Matematika Tingkat Lanjut'],
            'mapel_pilihan_pendukung' => ['Fisika', 'Informatika', 'Sosiologi'],
        ],
        [
            'no'                      => 54,
            'kelompok_prodi'          => 'Informatika Medis atau Informatika Kesehatan',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'keywords'                => ['informatika medis', 'informatika kesehatan', 'rekam medis dan informasi kesehatan'],
            'mapel_kurikulum_resmi'   => 'Biologi dan/atau Matematika Tingkat Lanjut',
            'mapel_pilihan_utama'     => ['Biologi', 'Matematika Tingkat Lanjut'],
            'mapel_pilihan_pendukung' => ['Informatika', 'Kimia'],
        ],
        [
            'no'                      => 55,
            'kelompok_prodi'          => 'Konservasi Biologi, Konservasi Hewan Liar, Konservasi Hutan, Konservasi Sumber Daya Alam',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'keywords'                => ['konservasi', 'konservasi biologi', 'konservasi hutan', 'konservasi sumber daya alam'],
            'mapel_kurikulum_resmi'   => 'Biologi',
            'mapel_pilihan_utama'     => ['Biologi'],
            'mapel_pilihan_pendukung' => ['Kimia', 'Geografi'],
        ],
        [
            'no'                      => 56,
            'kelompok_prodi'          => 'Teknologi Pangan, Teknologi Hasil Pertanian/Peternakan/Perikanan',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'keywords'                => ['teknologi pangan', 'teknologi hasil pertanian', 'teknologi hasil perikanan', 'teknologi hasil peternakan', 'teknologi industri pertanian', 'pangan'],
            'mapel_kurikulum_resmi'   => 'Kimia dan Biologi',
            'mapel_pilihan_utama'     => ['Kimia', 'Biologi'],
            'mapel_pilihan_pendukung' => ['Matematika Tingkat Lanjut', 'Fisika'],
        ],
        [
            'no'                      => 57,
            'kelompok_prodi'          => 'Sains Data',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'keywords'                => ['sains data', 'data science'],
            'mapel_kurikulum_resmi'   => 'Matematika Tingkat Lanjut',
            'mapel_pilihan_utama'     => ['Matematika Tingkat Lanjut', 'Informatika'],
            'mapel_pilihan_pendukung' => ['Fisika', 'Ekonomi'],
        ],
        [
            'no'                      => 58,
            'kelompok_prodi'          => 'Sains Perkopian',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'keywords'                => ['perkopian', 'sains perkopian', 'kopi'],
            'mapel_kurikulum_resmi'   => 'Biologi',
            'mapel_pilihan_utama'     => ['Biologi'],
            'mapel_pilihan_pendukung' => ['Kimia', 'Ekonomi'],
        ],
        [
            'no'                      => 59,
            'kelompok_prodi'          => 'Studi Humanitas',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'keywords'                => ['humanitas', 'studi humanitas', 'humaniora'],
            'mapel_kurikulum_resmi'   => 'Sosiologi',
            'mapel_pilihan_utama'     => ['Sosiologi'],
            'mapel_pilihan_pendukung' => ['Bahasa Indonesia Tingkat Lanjut', 'Bahasa Inggris Tingkat Lanjut'],
        ],
    ];

    private const RIASEC_MAPEL = [
        'Matematika Tingkat Lanjut'       => ['investigative', 'conventional'],
        'Matematika'                      => ['investigative', 'realistic'],
        'Fisika'                          => ['investigative', 'realistic'],
        'Kimia'                           => ['investigative', 'realistic'],
        'Biologi'                         => ['investigative', 'social'],
        'Informatika'                     => ['investigative', 'realistic'],
        'Bahasa Jepang'                   => ['artistic', 'social'],
        'Bahasa Inggris Tingkat Lanjut'   => ['artistic', 'social', 'enterprising'],
        'Bahasa Indonesia Tingkat Lanjut' => ['artistic', 'social'],
        'Ekonomi'                         => ['enterprising', 'conventional'],
        'Sosiologi'                       => ['social', 'enterprising'],
        'Geografi'                        => ['investigative', 'realistic'],
    ];

    private const MI_MAPEL = [
        'Matematika Tingkat Lanjut'       => ['logis_matematis'],
        'Matematika'                      => ['logis_matematis'],
        'Fisika'                          => ['logis_matematis', 'spasial'],
        'Kimia'                           => ['logis_matematis', 'naturalis'],
        'Biologi'                         => ['naturalis', 'logis_matematis'],
        'Informatika'                     => ['logis_matematis', 'spasial'],
        'Bahasa Jepang'                   => ['linguistik', 'interpersonal'],
        'Bahasa Inggris Tingkat Lanjut'   => ['linguistik', 'interpersonal'],
        'Bahasa Indonesia Tingkat Lanjut' => ['linguistik', 'intrapersonal'],
        'Ekonomi'                         => ['logis_matematis', 'interpersonal'],
        'Sosiologi'                       => ['interpersonal', 'linguistik'],
        'Geografi'                        => ['spasial', 'naturalis'],
    ];

    private const AKTIVITAS_MAPEL = [
        'Matematika Tingkat Lanjut'       => 'latihan penyelesaian soal pembuktian kalkulus, eksplorasi pemodelan aljabar matriks, dan pendalaman soal analitis terstruktur',
        'Fisika'                          => 'latihan analisis soal mekanika-kelistrikan, simulasi eksperimen virtual, dan pendalaman penurunan rumus fisis',
        'Informatika'                     => 'latihan implementasi kode pemrograman mandiri dan eksplorasi modul praktikum di lab komputer',
        'Bahasa Jepang'                   => 'latihan penulisan aksara Hiragana-Katakana-Kanji, hafalan kosakata harian, dan praktik percakapan (Kaiwa)',
        'Biologi'                         => 'review peta konsep sistem organ dan genetika, telaah jurnal bioteknologi, serta pembuatan ringkasan visual',
        'Kimia'                           => 'latihan perhitungan stoikiometri dan termokimia, analisis persamaan reaksi, serta persiapan modul praktikum laboratorium',
        'Bahasa Inggris Tingkat Lanjut'   => 'latihan academic writing, critical reading artikel ilmiah internasional, dan praktik public speaking/debat',
        'Ekonomi'                         => 'latihan studi kasus ekonomi makro-mikro, penyusunan siklus laporan akuntansi, dan analisis berita finansial',
        'Sosiologi'                       => 'telaah studi kasus fenomena sosial, penyusunan instrumen riset sosiologi, dan latihan penulisan esai kritis',
        'Geografi'                        => 'latihan pengolahan peta pada perangkat Sistem Informasi Geografis (SIG), analisis citra penginderaan jauh, dan studi kewilayahan',
        'Bahasa Indonesia Tingkat Lanjut' => 'latihan penulisan karya tulis ilmiah, penyuntingan naskah esai, dan telaah apresiasi sastra Nusantara',
    ];

    /**
     * Memetakan daftar minat prodi siswa ke aturan Kurikulum Merdeka (59 Kelompok Prodi).
     */
    public function petakanMinatProdiKeKurikulum(Collection $minatProdi): array
    {
        $hasil = [];

        foreach ($minatProdi as $mp) {
            $namaProdiLower = strtolower(trim((string) $mp->nama_prodi));
            $cocok = null;

            // Cek khusus Pendidikan (No. 32) dengan mata pelajaran spesifik
            if (str_contains($namaProdiLower, 'pendidikan') && !str_contains($namaProdiLower, 'pendidikan dokter')) {
                $cocok = $this->petakanProdiPendidikan($namaProdiLower);
            }

            if (!$cocok) {
                // Urutkan keyword terpanjang lebih dulu agar "kedokteran gigi" cocok sebelum "kedokteran"
                $bestScore = 0;
                foreach (self::KURIKULUM_PRODI as $aturan) {
                    foreach ($aturan['keywords'] as $kw) {
                        if (str_contains($namaProdiLower, $kw)) {
                            $len = strlen($kw);
                            if ($len > $bestScore) {
                                $bestScore = $len;
                                $cocok     = $aturan;
                            }
                        }
                    }
                }
            }

            if ($cocok) {
                $hasil[] = [
                    'prioritas'               => (int) $mp->prioritas,
                    'nama_prodi_siswa'        => $mp->nama_prodi,
                    'perguruan_tinggi'        => $mp->perguruan_tinggi,
                    'no_kelompok_kurikulum'   => $cocok['no'],
                    'kelompok_prodi'          => $cocok['kelompok_prodi'],
                    'rumpun_ilmu'             => $cocok['rumpun_ilmu'],
                    'mapel_kurikulum_resmi'   => $cocok['mapel_kurikulum_resmi'],
                    'mapel_pilihan_utama'     => $cocok['mapel_pilihan_utama'],
                    'mapel_pilihan_pendukung' => $cocok['mapel_pilihan_pendukung'],
                ];
            } else {
                $hasil[] = [
                    'prioritas'               => (int) $mp->prioritas,
                    'nama_prodi_siswa'        => $mp->nama_prodi,
                    'perguruan_tinggi'        => $mp->perguruan_tinggi,
                    'no_kelompok_kurikulum'   => null,
                    'kelompok_prodi'          => 'Umum / Lintas Disiplin',
                    'rumpun_ilmu'             => 'Umum',
                    'mapel_kurikulum_resmi'   => 'Menyesuaikan profil akademik siswa',
                    'mapel_pilihan_utama'     => [],
                    'mapel_pilihan_pendukung' => [],
                ];
            }
        }

        return $hasil;
    }

    private function petakanProdiPendidikan(string $namaProdiLower): array
    {
        $utama     = ['Sosiologi', 'Bahasa Indonesia Tingkat Lanjut'];
        $pendukung = ['Bahasa Inggris Tingkat Lanjut'];

        if (str_contains($namaProdiLower, 'matematika')) {
            $utama = ['Matematika Tingkat Lanjut'];
            $pendukung = ['Fisika', 'Informatika'];
        } elseif (str_contains($namaProdiLower, 'fisika')) {
            $utama = ['Fisika'];
            $pendukung = ['Matematika Tingkat Lanjut', 'Kimia'];
        } elseif (str_contains($namaProdiLower, 'kimia')) {
            $utama = ['Kimia'];
            $pendukung = ['Biologi', 'Matematika Tingkat Lanjut'];
        } elseif (str_contains($namaProdiLower, 'biologi') || str_contains($namaProdiLower, 'ipa')) {
            $utama = ['Biologi'];
            $pendukung = ['Kimia', 'Fisika'];
        } elseif (str_contains($namaProdiLower, 'informatika') || str_contains($namaProdiLower, 'komputer') || str_contains($namaProdiLower, 'tik')) {
            $utama = ['Informatika', 'Matematika Tingkat Lanjut'];
            $pendukung = ['Fisika'];
        } elseif (str_contains($namaProdiLower, 'inggris')) {
            $utama = ['Bahasa Inggris Tingkat Lanjut'];
            $pendukung = ['Bahasa Indonesia Tingkat Lanjut', 'Bahasa Jepang'];
        } elseif (str_contains($namaProdiLower, 'jepang')) {
            $utama = ['Bahasa Jepang'];
            $pendukung = ['Bahasa Inggris Tingkat Lanjut', 'Bahasa Indonesia Tingkat Lanjut'];
        } elseif (str_contains($namaProdiLower, 'indonesia') || str_contains($namaProdiLower, 'sastra')) {
            $utama = ['Bahasa Indonesia Tingkat Lanjut'];
            $pendukung = ['Bahasa Inggris Tingkat Lanjut', 'Sosiologi'];
        } elseif (str_contains($namaProdiLower, 'ekonomi') || str_contains($namaProdiLower, 'akuntansi') || str_contains($namaProdiLower, 'bisnis')) {
            $utama = ['Ekonomi'];
            $pendukung = ['Matematika Tingkat Lanjut', 'Sosiologi'];
        } elseif (str_contains($namaProdiLower, 'geografi')) {
            $utama = ['Geografi'];
            $pendukung = ['Sosiologi', 'Ekonomi'];
        }

        return [
            'no'                      => 32,
            'kelompok_prodi'          => 'Pendidikan',
            'rumpun_ilmu'             => 'Ilmu Terapan',
            'mapel_kurikulum_resmi'   => 'Paling banyak 1 mata pelajaran pendukung yang relevan dengan program studi kependidikannya',
            'mapel_pilihan_utama'     => $utama,
            'mapel_pilihan_pendukung' => $pendukung,
        ];
    }

    /**
     * Generate atau update draf rekomendasi mapil untuk satu siswa.
     *
     * @return Collection<int,RekomendasiMapel>
     */
    public function generate(Siswa $siswa, ?string $tahunAjaran = null, bool $force = false): Collection
    {
        $tahunAjaran = $tahunAjaran ?: (date('Y') . '/' . ((int) date('Y') + 1));

        $sudahDikunci = RekomendasiMapel::where('siswa_id', $siswa->id)
            ->where('tahun_ajaran', $tahunAjaran)
            ->whereIn('status', ['dikunci', 'diterbitkan'])
            ->exists();

        if ($sudahDikunci && !$force) {
            return RekomendasiMapel::with('mataPelajaran')
                ->where('siswa_id', $siswa->id)
                ->where('tahun_ajaran', $tahunAjaran)
                ->orderByDesc('skor_rekomendasi')
                ->get();
        }

        $mapelPilihan   = MataPelajaran::where('tersedia_sebagai_pilihan', true)->get();
        $nilaiMap       = $this->buildNilaiMap($siswa->id);
        $riasec         = AsesmenRiasec::where('siswa_id', $siswa->id)->latest('id')->first();
        $miRecords      = AsesmenMultipleIntelligence::where('siswa_id', $siswa->id)->get()->keyBy('jenis_kecerdasan');
        $minatProdi     = MinatProdiSiswa::where('siswa_id', $siswa->id)->orderBy('prioritas')->get();
        $petaKurikulum  = $this->petakanMinatProdiKeKurikulum($minatProdi);

        $rekomendasi = collect();

        foreach ($mapelPilihan as $mapel) {
            $skorNilai  = $this->skorDariNilai($mapel, $nilaiMap);
            $skorRiasec = $this->skorDariRiasec($mapel, $riasec);
            $skorMi     = $this->skorDariMi($mapel, $miRecords);
            $infoProdi  = $this->evaluasiKurikulumProdi($mapel->nama_mapel, $petaKurikulum);
            $skorProdi  = $infoProdi['skor'];

            $skorDasar =
                ($skorProdi  * self::BOBOT_PRODI) +
                ($skorNilai  * self::BOBOT_NILAI) +
                ($skorRiasec * self::BOBOT_RIASEC) +
                ($skorMi     * self::BOBOT_MI);

            // Afirmasi Kurikulum Merdeka: jika mapel merupakan Mapel Pendukung Utama
            // untuk Prodi Pilihan 1 (misal Biologi & Kimia pada Kedokteran), berikan bonus
            // afirmasi agar mapel wajib kurikulum selalu berada di Paket 4 Teratas.
            $bonusKurikulum = match ($infoProdi['peran']) {
                'utama_pilihan_1_dan_2' => 6.0,
                'utama_pilihan_1'       => 5.0,
                'utama_pilihan_2'       => 3.0,
                'pendukung_rumpun'      => 1.5,
                default                 => 0.0,
            };

            $skorTotal = round(min(100.0, $skorDasar + $bonusKurikulum), 2);

            $tingkat        = $this->tentukanTingkat($skorTotal);
            $alasan         = $this->buatAlasan($mapel->nama_mapel, $skorNilai, $skorRiasec, $skorMi, $skorProdi, $tingkat, $infoProdi['keterangan']);
            $probabilitas   = $skorTotal;
            $alokasiJam     = $this->buatRekomendasiAlokasiJam($mapel->nama_mapel, $skorTotal);
            $fokusPenguatan = $this->buatFokusPenguatanMateri($mapel->nama_mapel, $skorTotal);
            $evaluasi       = $this->buatCatatanEvaluasiAkademik($mapel->nama_mapel, $skorTotal, $skorNilai, $skorProdi, $infoProdi['keterangan']);

            $rec = RekomendasiMapel::updateOrCreate(
                [
                    'siswa_id'          => $siswa->id,
                    'mata_pelajaran_id' => $mapel->id,
                    'tahun_ajaran'      => $tahunAjaran,
                ],
                [
                    'skor_rekomendasi'          => $skorTotal,
                    'tingkat_rekomendasi'       => $tingkat,
                    'alasan_rekomendasi'        => $alasan,
                    'tanggal_rekomendasi'       => Carbon::today()->format('Y-m-d'),
                    'status'                    => 'draft',
                    'probabilitas_penguasaan'   => $probabilitas,
                    'strategi_belajar'          => $alokasiJam,
                    'fokus_penguatan_materi'    => $fokusPenguatan,
                    'catatan_evaluasi_akademik' => $evaluasi,
                ]
            );

            $rekomendasi->push($rec);
        }

        return $rekomendasi->sortByDesc('skor_rekomendasi')->values();
    }

    public function evaluasiKurikulumProdi(string $namaMapel, array $petaKurikulum): array
    {
        if (empty($petaKurikulum)) {
            return [
                'skor'       => 65.0,
                'peran'      => 'umum',
                'keterangan' => null,
            ];
        }

        $utamaPil1     = false;
        $utamaPil2     = false;
        $pendukungPil1 = false;
        $pendukungPil2 = false;
        $prodiCocok    = [];

        foreach ($petaKurikulum as $item) {
            $isPil1 = ((int) $item['prioritas'] === 1);
            if (in_array($namaMapel, $item['mapel_pilihan_utama'], true)) {
                if ($isPil1) {
                    $utamaPil1 = true;
                } else {
                    $utamaPil2 = true;
                }
                $prodiCocok[] = "{$item['nama_prodi_siswa']} (Kelompok {$item['kelompok_prodi']})";
            } elseif (in_array($namaMapel, $item['mapel_pilihan_pendukung'], true)) {
                if ($isPil1) {
                    $pendukungPil1 = true;
                } else {
                    $pendukungPil2 = true;
                }
            }
        }

        $prodiStr = implode(' & ', array_unique($prodiCocok));

        if ($utamaPil1 && $utamaPil2) {
            return [
                'skor'       => 100.0,
                'peran'      => 'utama_pilihan_1_dan_2',
                'keterangan' => "Merupakan Mata Pelajaran Pendukung Utama Kurikulum Merdeka untuk {$prodiStr} (Pilihan 1 & 2)",
            ];
        }

        if ($utamaPil1) {
            return [
                'skor'       => 98.0,
                'peran'      => 'utama_pilihan_1',
                'keterangan' => "Merupakan Mata Pelajaran Pendukung Utama Kurikulum Merdeka untuk {$prodiStr} (Pilihan 1)",
            ];
        }

        if ($utamaPil2) {
            return [
                'skor'       => 90.0,
                'peran'      => 'utama_pilihan_2',
                'keterangan' => "Merupakan Mata Pelajaran Pendukung Utama Kurikulum Merdeka untuk {$prodiStr} (Pilihan 2)",
            ];
        }

        if ($pendukungPil1 || $pendukungPil2) {
            return [
                'skor'       => 80.0,
                'peran'      => 'pendukung_rumpun',
                'keterangan' => 'Merupakan mata pelajaran pilihan pelengkap yang selaras dengan rumpun keilmuan program studi tujuan siswa',
            ];
        }

        return [
            'skor'       => 45.0,
            'peran'      => 'tidak_terkait',
            'keterangan' => 'Bukan mata pelajaran pendukung langsung pada kelompok program studi pilihan siswa',
        ];
    }

    public function kategoriPenguasaan(float $persentase): string
    {
        return match (true) {
            $persentase >= 80 => 'Tinggi',
            $persentase >= 65 => 'Sedang',
            default           => 'Rendah',
        };
    }

    private function buildNilaiMap(int $siswaId): array
    {
        return NilaiRapor::where('siswa_id', $siswaId)
            ->where('semester', 1)
            ->with('mataPelajaran')
            ->get()
            ->mapWithKeys(fn ($n) => [strtolower($n->mataPelajaran->nama_mapel) => (float) $n->nilai_akhir])
            ->toArray();
    }

    private function skorDariNilai(MataPelajaran $mapel, array $nilaiMap): float
    {
        $key   = strtolower($mapel->nama_mapel);
        $nilai = $nilaiMap[$key]
            ?? $nilaiMap[trim(str_replace('tingkat lanjut', '', $key))]
            ?? ($key === 'bahasa jepang'
                ? ($nilaiMap['bahasa inggris'] ?? $nilaiMap['bahasa indonesia'] ?? null)
                : null);

        if ($nilai === null) {
            return 65.0;
        }

        return min(100, max(0, $nilai));
    }

    private function skorDariRiasec(MataPelajaran $mapel, ?AsesmenRiasec $riasec): float
    {
        if (!$riasec) {
            return 60.0;
        }

        $kodeMapel = $this->matchMapelKey($mapel->nama_mapel, self::RIASEC_MAPEL);
        if (!$kodeMapel) {
            return 60.0;
        }

        $dimensiMap = [
            'realistic'     => (float) $riasec->realistic_score,
            'investigative' => (float) $riasec->investigative_score,
            'artistic'      => (float) $riasec->artistic_score,
            'social'        => (float) $riasec->social_score,
            'enterprising'  => (float) $riasec->enterprising_score,
            'conventional'  => (float) $riasec->conventional_score,
        ];

        $dimensiRelevan = self::RIASEC_MAPEL[$kodeMapel];
        $total = 0;
        $count = 0;

        foreach ($dimensiRelevan as $dim) {
            $total += $dimensiMap[$dim] ?? 0;
            $count++;
        }

        $avg = $count > 0 ? $total / $count : 60.0;
        return min(100, max(0, $avg));
    }

    private function skorDariMi(MataPelajaran $mapel, Collection $miRecords): float
    {
        $kodeMapel = $this->matchMapelKey($mapel->nama_mapel, self::MI_MAPEL);
        if (!$kodeMapel || $miRecords->isEmpty()) {
            return 60.0;
        }

        $jenisRelevan = self::MI_MAPEL[$kodeMapel];
        $total = 0;
        $count = 0;

        foreach ($jenisRelevan as $jenis) {
            if ($miRecords->has($jenis)) {
                $total += (float) $miRecords->get($jenis)->skor;
                $count++;
            }
        }

        $avg = $count > 0 ? $total / $count : 60.0;
        return min(100, max(0, $avg));
    }

    private function matchMapelKey(string $namaMapel, array $peta): ?string
    {
        if (isset($peta[$namaMapel])) {
            return $namaMapel;
        }

        $lower = strtolower($namaMapel);
        foreach (array_keys($peta) as $key) {
            if (str_contains(strtolower($key), $lower) || str_contains($lower, strtolower($key))) {
                return $key;
            }
        }

        return null;
    }

    private function tentukanTingkat(float $skor): string
    {
        return match (true) {
            $skor >= 80 => 'Sangat Direkomendasikan',
            $skor >= 68 => 'Direkomendasikan',
            $skor >= 55 => 'Cukup',
            default     => 'Kurang Direkomendasikan',
        };
    }

    private function buatAlasan(
        string $namaMapel,
        float $skorNilai,
        float $skorRiasec,
        float $skorMi,
        float $skorProdi,
        string $tingkat,
        ?string $ketKurikulum = null
    ): string {
        $infoKurikulum = $ketKurikulum ? "{$ketKurikulum}. " : '';

        $narasi = match ($tingkat) {
            'Sangat Direkomendasikan' => $infoKurikulum . "Estimasi penguasaan materi sangat kuat karena didukung oleh capaian nilai rapor Semester 1 (%.1f), keselarasan prodi pada Kurikulum Merdeka (%.1f), profil kecerdasan majemuk (%.1f), dan hasil asesmen RIASEC (%.1f).",
            'Direkomendasikan'        => $infoKurikulum . "Estimasi penguasaan materi berada pada kategori baik berdasarkan capaian nilai rapor Semester 1 (%.1f), relevansi terhadap minat program studi (%.1f), profil kecerdasan majemuk (%.1f), dan hasil asesmen RIASEC (%.1f).",
            'Cukup'                   => $infoKurikulum . "Siswa memiliki potensi dasar pada {$namaMapel} berdasarkan nilai rapor Semester 1 (%.1f), minat program studi (%.1f), kecerdasan majemuk (%.1f), dan hasil asesmen RIASEC (%.1f), namun memerlukan penguatan belajar mandiri.",
            default                   => $infoKurikulum . "Estimasi keselarasan terhadap {$namaMapel} relatif rendah berdasarkan nilai rapor Semester 1 (%.1f), prospek prodi (%.1f), kecerdasan majemuk (%.1f), dan hasil asesmen RIASEC (%.1f).",
        };

        return sprintf($narasi, $skorNilai, $skorProdi, $skorMi, $skorRiasec);
    }

    public function buatRekomendasiAlokasiJam(string $namaMapel, float $skor): string
    {
        $jam = match (true) {
            $skor >= 80 => 4,
            $skor >= 68 => 5,
            $skor >= 55 => 6,
            default     => 8,
        };

        $aktivitas = self::AKTIVITAS_MAPEL[$namaMapel]
            ?? "latihan pemahaman konsep dan penyelesaian soal terstruktur pada {$namaMapel}";

        return "Minimal {$jam} jam per minggu untuk {$aktivitas}.";
    }

    public function buatFokusPenguatanMateri(string $namaMapel, float $skor): string
    {
        $jam = match (true) {
            $skor >= 80 => 4,
            $skor >= 68 => 5,
            $skor >= 55 => 6,
            default     => 8,
        };

        if ($skor >= 80) {
            return "Perkuat pemahaman konsep dasar dan perbanyak latihan soal terarah minimal {$jam} jam per minggu.";
        }

        if ($skor >= 68) {
            return "Fokus pada penguatan konsep prasyarat Fase E dan latihan soal analitis bertahap minimal {$jam} jam per minggu.";
        }

        return "Prioritaskan remedial konsep dasar Fase E, diskusi terarah bersama guru pengampu, dan latihan soal bertahap minimal {$jam} jam per minggu.";
    }

    public function buatCatatanEvaluasiAkademik(
        string $namaMapel,
        float $skor,
        float $skorNilai,
        float $skorProdi,
        ?string $ketKurikulum = null
    ): string {
        if ($skor >= 80 && $skorProdi >= 90) {
            return ($ketKurikulum ? "{$ketKurikulum}. " : '')
                . "Sangat direkomendasikan sebagai mata pelajaran pilihan utama Fase F karena menjadi syarat pendukung langsung seleksi masuk perguruan tinggi (SNBP) dan ditopang fondasi nilai rapor Semester 1 yang kuat.";
        }

        if ($skor >= 68) {
            return ($ketKurikulum ? "{$ketKurikulum}. " : '')
                . "Direkomendasikan sebagai bagian dari paket mata pelajaran pilihan Fase F. Siswa memiliki kesiapan akademik yang memadai dan dapat mempertahankan performa melalui belajar mandiri rutin.";
        }

        return "Perlu pertimbangan konseling lebih lanjut bersama Guru BK apabila siswa memilih {$namaMapel}, mengingat mata pelajaran ini bukan pendukung utama prodi tujuan atau membutuhkan jam belajar tambahan untuk mengejar prasyarat Fase F.";
    }
}
