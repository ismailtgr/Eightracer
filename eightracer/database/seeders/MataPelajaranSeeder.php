<?php

namespace Database\Seeders;

use App\Models\MataPelajaran;
use Illuminate\Database\Seeder;

class MataPelajaranSeeder extends Seeder
{
    public function run(): void
    {
        $mapel = [
            ['Pendidikan Agama dan Budi Pekerti', 'W', 'Umum', false, null, null],
            ['Pendidikan Pancasila',              'W', 'Umum', false, null, null],
            ['Bahasa Indonesia',                  'W', 'Umum', false, null, null],
            ['Matematika',                        'W', 'Umum', false, null, null],
            ['Bahasa Inggris',                    'W', 'Umum', false, null, null],
            ['Pendidikan Jasmani',                'W', 'Umum', false, null, null],
            ['Seni Budaya',                       'W', 'Umum', false, null, null],
            ['Sejarah',                           'W', 'Umum', false, null, null],

            [
                'Matematika Tingkat Lanjut',
                'P',
                'MIPA',
                true,
                'Fokus pembelajaran mencakup penalaran kuantitatif tingkat lanjut, pemodelan matematika, dan pemecahan masalah analitis secara terstruktur. Didukung oleh fondasi aljabar dan logika deduktif Fase E.',
                ['Kalkulus & Limit', 'Aljabar Matriks', 'Geometri Analitik', 'Statistika Inferensial'],
            ],
            [
                'Fisika',
                'P',
                'MIPA',
                true,
                'Fokus pembelajaran mencakup analisis fenomena fisis, hukum gerak, energi, serta kelistrikan melalui pendekatan eksperimental dan matematis. Didukung oleh fondasi numerik dan observasi ilmiah.',
                ['Mekanika Klasik', 'Listrik & Magnet', 'Termodinamika', 'Fisika Modern'],
            ],
            [
                'Informatika',
                'P',
                'MIPA',
                true,
                'Fokus pembelajaran mencakup penguasaan komputasi tingkat lanjut dan pemecahan masalah secara terstruktur. Didukung oleh fondasi logika matematika dan literasi digital.',
                ['Algoritma Lanjut', 'RPL', 'Sistem Basis Data', 'Jaringan Komputer'],
            ],
            [
                'Bahasa Jepang',
                'P',
                'Bahasa',
                true,
                'Fokus pembelajaran mencakup penguasaan aksara Jepang, struktur tata bahasa dasar-menengah, serta komunikasi lintas budaya secara lisan dan tulisan. Didukung oleh daya ingat visual dan kecakapan linguistik.',
                ['Hiragana & Katakana', 'Kanji Dasar', 'Tata Bahasa (Bunpou)', 'Percakapan (Kaiwa)'],
            ],
            [
                'Biologi',
                'P',
                'MIPA',
                true,
                'Fokus pembelajaran mencakup kajian sistem kehidupan dari tingkat seluler, molekuler, genetika, hingga bioteknologi modern dan ekosistem. Didukung oleh ketelitian observasi laboratorium dan nalar ilmiah.',
                ['Biologi Sel & Molekuler', 'Genetika & Hereditas', 'Fisiologi Organ', 'Bioteknologi'],
            ],
            [
                'Kimia',
                'P',
                'MIPA',
                true,
                'Fokus pembelajaran mencakup analisis komposisi, struktur materi, energetika reaksi, serta kesetimbangan dan senyawa karbon melalui praktikum terukur. Didukung oleh perhitungan stoikiometri dan logika analitis.',
                ['Stoikiometri Reaksi', 'Termokimia', 'Kesetimbangan Kimia', 'Kimia Organik'],
            ],
            [
                'Bahasa Inggris Tingkat Lanjut',
                'P',
                'Bahasa',
                true,
                'Fokus pembelajaran mencakup penulisan akademik, telaah teks kritis, retorika debat, dan apresiasi sastra berbahasa Inggris tingkat lanjut. Didukung oleh penguasaan tata bahasa dan literasi global.',
                ['Academic Writing', 'Critical Reading', 'Public Speaking & Debate', 'Literature Analysis'],
            ],
            [
                'Ekonomi',
                'P',
                'IPS',
                true,
                'Fokus pembelajaran mencakup analisis perilaku pasar mikro-makro, siklus akuntansi perusahaan, kebijakan moneter-fiskal, dan manajemen keuangan. Didukung oleh nalar kuantitatif dan literasi finansial.',
                ['Ekonomi Mikro & Makro', 'Akuntansi Perusahaan', 'Manajemen Keuangan', 'Kebijakan Fiskal & Moneter'],
            ],
            [
                'Sosiologi',
                'P',
                'IPS',
                true,
                'Fokus pembelajaran mencakup kajian struktur sosial, dinamika kelompok, perubahan masyarakat, resolusi konflik, dan metodologi riset sosial. Didukung oleh kepekaan interpersonal dan berpikir kritis.',
                ['Struktur & Stratifikasi', 'Perubahan Sosial', 'Resolusi Konflik', 'Riset Sosial'],
            ],
            [
                'Geografi',
                'P',
                'IPS',
                true,
                'Fokus pembelajaran mencakup analisis keruangan geosfer, pemetaan berbasis Sistem Informasi Geografis (SIG), penginderaan jauh, dan perencanaan wilayah. Didukung oleh kecerdasan spasial dan observasi lingkungan.',
                ['Sistem Informasi Geografis', 'Penginderaan Jauh', 'Dinamika Geosfer', 'Tata Ruang Wilayah'],
            ],
            [
                'Bahasa Indonesia Tingkat Lanjut',
                'P',
                'Bahasa',
                true,
                'Fokus pembelajaran mencakup penyusunan karya tulis ilmiah, kajian sastra Nusantara, jurnalistik, serta retorika dan penyuntingan teks. Didukung oleh kemampuan ekspresi verbal dan analisis wacana.',
                ['Penulisan Karya Ilmiah', 'Kajian Sastra Nusantara', 'Jurnalistik & Esai', 'Retorika & Penyuntingan'],
            ],
        ];

        foreach ($mapel as [$nama, $jenis, $kelompok, $pilihan, $deskripsi, $topikUtama]) {
            MataPelajaran::updateOrCreate(
                ['nama_mapel' => $nama],
                [
                    'jenis_mapel'              => $jenis,
                    'kelompok'                 => $kelompok,
                    'tersedia_sebagai_pilihan' => $pilihan,
                    'deskripsi'                => $deskripsi,
                    'topik_utama'              => $topikUtama,
                ]
            );
        }

        MataPelajaran::whereNotIn('nama_mapel', [
            'Matematika Tingkat Lanjut',
            'Fisika',
            'Informatika',
            'Bahasa Jepang',
            'Biologi',
            'Kimia',
            'Bahasa Inggris Tingkat Lanjut',
            'Ekonomi',
            'Sosiologi',
            'Geografi',
            'Bahasa Indonesia Tingkat Lanjut',
        ])->update(['tersedia_sebagai_pilihan' => false]);
    }
}
