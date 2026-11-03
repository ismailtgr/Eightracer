<?php

require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function simpanSpreadsheet(Spreadsheet $spreadsheet, string $namaFile): void
{
    $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'eightracer_templates';
    @mkdir($tmpDir, 0775, true);
    $tmp = $tmpDir . DIRECTORY_SEPARATOR . $namaFile;
    (new Xlsx($spreadsheet))->save($tmp);

    $dest = __DIR__ . DIRECTORY_SEPARATOR . 'templates';
    if (is_dir($dest) || @mkdir($dest, 0775, true)) {
        if (@copy($tmp, $dest . DIRECTORY_SEPARATOR . $namaFile)) {
            echo "OK  {$dest}\\{$namaFile}\n";
            return;
        }
    }
    echo "OK  {$tmp}  (folder project tidak bisa ditulis, salin manual)\n";
}

function buatTemplate(string $namaFile, string $judulSheet, array $columns, array $groups, array $dummyRows = []): void
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle($judulSheet);

    $petunjuk = $spreadsheet->createSheet();
    $petunjuk->setTitle('Petunjuk');
    $petunjuk->fromArray(['Kolom', 'Keterangan', 'Contoh'], null, 'A1');
    $petunjuk->getStyle('A1:C1')->getFont()->setBold(true);

    $i = 1;
    foreach ($columns as $heading => [$ket, $contoh]) {
        $c = Coordinate::stringFromColumnIndex($i);
        $sheet->setCellValueExplicit($c . '1', $heading, DataType::TYPE_STRING);
        $sheet->getColumnDimension($c)->setAutoSize(true);
        $sheet->getStyle("{$c}2:{$c}1000")->getNumberFormat()->setFormatCode('@');

        $r = $i + 1;
        $petunjuk->setCellValueExplicit("A{$r}", $heading, DataType::TYPE_STRING);
        $petunjuk->setCellValueExplicit("B{$r}", $ket, DataType::TYPE_STRING);
        $petunjuk->setCellValueExplicit("C{$r}", (string) $contoh, DataType::TYPE_STRING);
        $i++;
    }
    foreach (['A', 'B', 'C'] as $pc) {
        $petunjuk->getColumnDimension($pc)->setAutoSize(true);
    }

    foreach ($groups as [$s, $e, $hdr, $lbl]) {
        $a = Coordinate::stringFromColumnIndex($s);
        $b = Coordinate::stringFromColumnIndex($e);
        $sheet->getStyle("{$a}1:{$b}1")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $hdr]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'AAAAAA']]],
        ]);
    }

    foreach ($dummyRows as $rowIdx => $rowValues) {
        $excelRow = $rowIdx + 2;
        foreach ($rowValues as $colIdx => $val) {
            $c = Coordinate::stringFromColumnIndex($colIdx + 1);
            $sheet->setCellValueExplicit($c . $excelRow, (string) $val, DataType::TYPE_STRING);
        }
    }

    $sheet->getRowDimension(1)->setRowHeight(32);
    $sheet->freezePane('A2');
    $spreadsheet->setActiveSheetIndex(0);

    simpanSpreadsheet($spreadsheet, $namaFile);
}

function ambilSiswaUntukDummy(): array
{
    try {
        $app = require __DIR__ . '/bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $rows = \App\Models\Siswa::select('nisn', 'nis', 'nama_lengkap', 'nama_panggilan', 'jenis_kelamin', 'agama', 'tempat_lahir', 'tanggal_lahir', 'alamat_rumah', 'tinggal_bersama')
            ->orderBy('id')
            ->limit(15)
            ->get()
            ->toArray();
        if (!empty($rows)) {
            return $rows;
        }
    } catch (\Throwable) {
        // fallback ke data dummy statis
    }

    return [
        ['nisn' => '0081234501', 'nis' => '2425001', 'nama_lengkap' => 'Ahmad Fauzi Pratama',   'nama_panggilan' => 'Fauzi',  'jenis_kelamin' => 'L', 'agama' => 'Islam',   'tempat_lahir' => 'Malang',   'tanggal_lahir' => '2009-05-14', 'alamat_rumah' => 'Jl. Veteran No. 12, Malang',    'tinggal_bersama' => 'Orang Tua'],
        ['nisn' => '0081234502', 'nis' => '2425002', 'nama_lengkap' => 'Aisyah Putri Maharani', 'nama_panggilan' => 'Aisyah', 'jenis_kelamin' => 'P', 'agama' => 'Islam',   'tempat_lahir' => 'Surabaya', 'tanggal_lahir' => '2009-08-21', 'alamat_rumah' => 'Jl. Ijen No. 45, Malang',       'tinggal_bersama' => 'Orang Tua'],
        ['nisn' => '0081234503', 'nis' => '2425003', 'nama_lengkap' => 'Bagas Adi Nugroho',     'nama_panggilan' => 'Bagas',  'jenis_kelamin' => 'L', 'agama' => 'Islam',   'tempat_lahir' => 'Malang',   'tanggal_lahir' => '2009-02-10', 'alamat_rumah' => 'Jl. Soekarno Hatta No. 8',      'tinggal_bersama' => 'Orang Tua'],
        ['nisn' => '0081234504', 'nis' => '2425004', 'nama_lengkap' => 'Citra Dewi Lestari',    'nama_panggilan' => 'Citra',  'jenis_kelamin' => 'P', 'agama' => 'Kristen', 'tempat_lahir' => 'Batu',     'tanggal_lahir' => '2009-11-03', 'alamat_rumah' => 'Jl. Kawi No. 19, Malang',       'tinggal_bersama' => 'Wali'],
        ['nisn' => '0081234505', 'nis' => '2425005', 'nama_lengkap' => 'Dimas Arya Saputra',    'nama_panggilan' => 'Dimas',  'jenis_kelamin' => 'L', 'agama' => 'Islam',   'tempat_lahir' => 'Blitar',   'tanggal_lahir' => '2009-07-29', 'alamat_rumah' => 'Jl. Dieng No. 31, Malang',      'tinggal_bersama' => 'Orang Tua'],
    ];
}

$siswaDummy = ambilSiswaUntukDummy();

// ═════════════════════════════════════════════════════════════════════════════
// 1. TEMPLATE IMPORT SISWA (Format Kuesioner SMAN 8 Kota Malang)
// ═════════════════════════════════════════════════════════════════════════════
function buatTemplateSiswaKuesioner(array $siswaList): void
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Biodata Siswa');

    // Kolom 1..51 (Biodata & Keluarga) + Pertanyaan 1..100
    $headersUtama = [
        'NAMA LENGKAP',
        'NAMA PANGGILAN',
        'KELAS X',
        'NO. PRESENSI',
        'NISN',
        'Tempat Lahir',
        'Tanggal Lahir',
        'Alamat Tempat Tinggal',
        'Tinggal Bersama',
        'Hobi',
        'Cita Cita',
        'Ekstrakurikuler yang akan diikuti di SMAN 8 Kota Malang',
        'Aku berasal dari Suku ... .',
        'Aku menggunakan bahasa keseharian ... .',
        'Makanan Favoritku adalah ... .',
        'Warna Favoritku adalah ... .',
        'Permainan Online yang aku suka adalah ... .',
        'Permainan Offline yang aku suka adalah ... .',
        'Hal yang membuat aku bahagia adalah ... .',
        'Hal yang membuat aku sedih adalah ... .',
        'Aku ingin orangtuaku tahu bahwa ... .',
        'Aku ingin guruku tahu bahwa ... .',
        'Aku harap temanku tahu bahwa ... .',
        'Media Sosial yang aku punya adalah (ini untuk media sosial)... .',
        'Prestasi yang pernah aku raih saat SMP adalah ... .',
        'Aku Anak ke-',
        'Punya Saudara Tiri',
        'Nama Ayah Kandung',
        'Saya pernah mendapatkan bantuan pemerintah berupa',
        'Pekerjaan Ayah Kandung',
        'Gaji / Pendapatan Ayah Kandung (per bulan)',
        'Alamat Ayah Kandung',
        'No HP Ayah Kandung',
        'Nama Ibu Kandung',
        'Pekerjaan Ibu Kandung',
        'Gaji / Pendapatan Ibu Kandung (per bulan)',
        'Alamat Ibu Kandung',
        'No HP Ibu Kandung',
        'Nama Wali',
        'Alamat Wali',
        'No HP Wali',
        'Punya Ayah Tiri',
        'Nama Ayah Tiri',
        'Alamat Ayah Tiri',
        'Pekerjaan Ayah Tiri',
        'Gaji / Pendapatan Ayah Tiri',
        'Punya Ibu Tiri',
        'Nama Ibu Tiri',
        'Alamat Ibu Tiri',
        'Pekerjaan Ibu Tiri',
        'Gaji / Pendapatan Ibu Tiri',
    ];

    $pertanyaanAngket = [
        '1.  Saya merasa kurang percaya diri dalam bergaul.',
        '2.  Saya sering merasa cemas tanpa alasan yang jelas.',
        '3.  Saya mudah merasa sedih atau murung.',
        '4.  Saya sering merasa kesepian meskipun banyak teman.',
        '5.  Saya tidak memiliki motivasi untuk berprestasi.',
        '6.  Saya merasa bingung dengan perubahan fisik saya.',
        '7.  Saya sulit mengambil keputusan sendiri.',
        '8.  Saya terlalu sering menggunakan media sosial.',
        '9. Saya jarang punya waktu untuk merenung atau beribadah.',
        '10.  Saya merasa orang tua tidak memahami saya.',
        '11. Saya merasa tidak bahagia dengan diri saya sendiri.',
        '12. Saya sering merasa takut menghadapi masa depan.',
        '13.  Saya sulit mengungkapkan perasaan saya kepada orang lain.',
        '14.  Saya merasa hidup saya tidak berarti.',
        '15.  Saya kurang bisa menerima kekurangan diri saya.',
        '16.  Saya mudah marah pada hal-hal kecil.',
        '17. Saya takut untuk mencoba hal-hal baru.',
        '18.  Saya merasa tidak punya kelebihan apa pun.',
        '19.  Saya merasa tertekan oleh harapan orang tua.',
        '20. Saya sering merasa iri dengan teman saya.',
        '21.  Saya merasa tidak punya kendali atas hidup saya.',
        '22.  Saya sulit mengatur emosi ketika menghadapi masalah.',
        '23.  Saya merasa canggung berada di tempat umum.',
        '24. Saya tidak bisa menerima kritik dari orang lain.',
        '25.  Saya merasa lelah secara mental meski tidak melakukan apa-apa.',
        '26.  Saya sulit beradaptasi dengan lingkungan sekolah baru.',
        '27.  Saya tidak percaya diri saat berbicara dengan teman.',
        '28.  Saya pernah bertengkar dengan teman sekelas.',
        '29.     Saya merasa dikucilkan dari kelompok pertemanan.',
        '30.  Saya pernah menjadi korban perundungan (bullying).',
        '31.  Saya merasa tidak punya teman dekat.',
        '32.  Saya lebih suka menyendiri daripada bersosialisasi.',
        '33.  Saya merasa tidak bisa berkomunikasi dengan guru dengan baik.',
        '34.  Saya tidak nyaman bekerja dalam kelompok.',
        '35.  Saya sering merasa diabaikan dalam pergaulan.',
        '36.  Saya tidak terbiasa menyapa orang lain lebih dulu.',
        '37.  Saya sering menilai orang dari penampilan luar.',
        '38.  Saya merasa kesulitan memahami perasaan orang lain.',
        '39.  Saya kurang mampu menghargai pendapat teman.',
        '40.  Saya merasa minder jika berada di antara banyak orang.',
        '41.  Saya merasa teman saya tidak menghargai saya.',
        '42.  Saya merasa sulit membangun hubungan yang akrab.',
        '43.  Saya tidak tahu bagaimana memulai percakapan dengan orang baru.',
        '44.  Saya merasa takut ditolak oleh kelompok tertentu.',
        '45.  Saya lebih percaya pada teman di media sosial daripada teman nyata.',
        '46.  Saya sering merasa tidak dianggap dalam kelas.',
        '47.  Saya enggan mengikuti kegiatan organisasi atau ekstrakurikuler.',
        '48.  Saya takut ditertawakan saat tampil di depan umum.',
        '49.  Saya tidak punya panutan dalam pergaulan.',
        '50.  Saya sering bertindak menurut tekanan teman.',
        '51.  Saya kesulitan memahami pelajaran Matematika.',
        '52.  Saya tidak terbiasa belajar secara mandiri.',
        '53.  Saya tidak memiliki jadwal belajar yang teratur.',
        '54.  Saya sering terganggu oleh gadget saat belajar.',
        '55.  Saya mudah bosan ketika belajar.',
        '56.  Saya tidak tahu bagaimana cara belajar yang efektif.',
        '57.  Saya sulit berkonsentrasi saat di kelas.',
        '58.  Saya sering menunda mengerjakan tugas sekolah.',
        '59.  Saya merasa tugas sekolah terlalu banyak.',
        '60.  Saya merasa nilai saya tidak sesuai dengan usaha saya.',
        '61.  Saya tidak suka membaca buku pelajaran.',
        '62.  Saya merasa tidak bisa bersaing secara akademik.',
        '63.  Saya sering tidak siap saat ujian tiba.',
        '64.  Saya kurang percaya diri saat menjawab pertanyaan guru.',
        '65.  Saya merasa tertinggal dibanding teman-teman saya.',
        '66.  Saya tidak mengerti tujuan belajar di sekolah.',
        '67.  Saya jarang berdiskusi dengan teman tentang pelajaran.',
        '68.  Saya malas mengerjakan PR jika tidak diawasi.',
        '69.  Saya tidak memiliki tempat belajar yang nyaman di rumah.',
        '70.  Saya tidak mampu mengelola waktu belajar dengan baik.',
        '71.  Saya mudah mengantuk saat belajar.',
        '72.  Saya lebih memilih bermain daripada belajar.',
        '73.  Saya tidak pernah mencatat pelajaran di kelas.',
        '74.  Saya takut mendapat nilai jelek meskipun sudah belajar.',
        '75.  Saya merasa belajar adalah beban, bukan kebutuhan.',
        '76.  Saya belum tahu cita-cita saya di masa depan.',
        '77.  Saya bingung memilih mata pelajaran pilihan di kelas XI.',
        '78. Saya belum mengetahui potensi atau bakat saya.',
        '79.  Saya tidak tahu langkah-langkah untuk mencapai cita-cita.',
        '80.  Saya kurang informasi tentang dunia kerja.',
        '81.  Saya belum pernah mengikuti tes minat dan bakat.',
        '82.  Saya merasa pilihan karir saya ditentukan oleh orang tua.',
        '83.  Saya belum tahu perbedaan antara jurusan IPA dan IPS atau belum tahu perbedaan antara rumpun ilmu kesehatan, teknik, sosial dan ekonomi',
        '84.  Saya tidak tahu cara memilih perguruan tinggi yang tepat.',
        '85.  Saya tidak percaya diri dengan masa depan saya.',
        '86.  Saya belum tahu pekerjaan yang cocok dengan minat saya.',
        '87.  Saya belum pernah diskusi dengan guru BK tentang karier.',
        '88.  Saya bingung memilih antara kuliah atau langsung kerja.',
        '89.  Saya belum punya rencana jangka panjang setelah lulus SMA.',
        '90.  Saya merasa tidak memiliki kemampuan khusus untuk karier.',
        '91.  Saya takut gagal dalam mencapai cita-cita.',
        '92.  Saya tidak tahu pentingnya soft skills dalam dunia kerja.',
        '93.  Saya merasa pilihan karier saya berubah-ubah.',
        '94.  Saya belum memahami dunia industri dan profesi.',
        '95.  Saya tidak tahu peran teknologi dalam pilihan karier.',
        '96.  Saya jarang membaca informasi tentang profesi.',
        '97.  Saya merasa tidak ada pekerjaan yang sesuai dengan minat saya.',
        '98.  Saya belum memahami jurusan kuliah dan prospeknya.',
        '99.  Saya tidak tahu perbedaan antara karier dan pekerjaan.',
        '100. Saya belum memiliki motivasi untuk merencanakan masa depan',
    ];

    $allHeaders = array_merge($headersUtama, $pertanyaanAngket);

    // Indeks 1-based kolom yang DIAMBIL oleh SiswaImport (diberi warna biru tua, sisanya abu-abu)
    $kolomDiambil = [
        1, 2, 3, 5, 6, 7, 8, 9, 10, 11,
        14, 15, 16, 17, 18,
        24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 37, 38, 39, 40, 41,
    ];

    foreach ($allHeaders as $idx => $h) {
        $col = $idx + 1;
        $c   = Coordinate::stringFromColumnIndex($col);
        $sheet->setCellValueExplicit($c . '1', $h, DataType::TYPE_STRING);
        $sheet->getStyle("{$c}2:{$c}500")->getNumberFormat()->setFormatCode('@');

        $rgb = in_array($col, $kolomDiambil, true) ? '1E3A5F' : '7F8C8D';
        $sheet->getStyle($c . '1')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rgb]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
    }

    // Variasi data dummy untuk mengisi relasi
    $variasi = [
        [
            'hobi' => 'Membaca, Futsal', 'cita' => 'Software Engineer, Dosen', 'ekskul' => 'Robotik',
            'bahasa' => 'Indonesia, Jawa', 'makanan' => 'Nasi Goreng', 'warna' => 'Biru',
            'online' => 'Mobile Legends', 'offline' => 'Futsal',
            'medsos' => 'Instagram: @siswa_smandela, WhatsApp: 081234567801',
            'prestasi' => 'Juara 1 Olimpiade Matematika Tingkat Kota',
            'anak_ke' => '1', 'saudara_tiri' => 'Tidak',
            'ayah' => 'Budi Hartono', 'bantuan' => 'PIP', 'pek_ayah' => 'Wiraswasta', 'gaji_ayah' => '4.500.000',
            'ibu' => 'Siti Aminah', 'pek_ibu' => 'Guru', 'gaji_ibu' => '3.800.000',
            'wali' => '-', 'alamat_wali' => '-', 'hp_wali' => '-',
        ],
        [
            'hobi' => 'Melukis, Musik', 'cita' => 'Dokter, Peneliti', 'ekskul' => 'PMR',
            'bahasa' => 'Indonesia', 'makanan' => 'Bakso Malang', 'warna' => 'Hijau',
            'online' => 'Roblox', 'offline' => 'Bulu Tangkis',
            'medsos' => 'Instagram: @aisyah_pm, TikTok: @aisyah.art',
            'prestasi' => 'Juara 2 Lomba Karya Ilmiah Remaja SMP',
            'anak_ke' => '2', 'saudara_tiri' => 'Tidak',
            'ayah' => 'Hendra Wijaya', 'bantuan' => 'Tidak Pernah', 'pek_ayah' => 'PNS', 'gaji_ayah' => '5.500.000',
            'ibu' => 'Dewi Sartika', 'pek_ibu' => 'Ibu Rumah Tangga', 'gaji_ibu' => '0',
            'wali' => '-', 'alamat_wali' => '-', 'hp_wali' => '-',
        ],
        [
            'hobi' => 'Basket, Fotografi', 'cita' => 'Arsitek, Pengusaha', 'ekskul' => 'Basket',
            'bahasa' => 'Indonesia, Jawa', 'makanan' => 'Ayam Geprek', 'warna' => 'Hitam',
            'online' => 'Valorant', 'offline' => 'Basket',
            'medsos' => 'Instagram: @bagas_photo',
            'prestasi' => 'Juara 1 Turnamen Basket Antar SMP Se-Malang Raya',
            'anak_ke' => '1', 'saudara_tiri' => 'Punya',
            'ayah' => 'Agus Nugroho', 'bantuan' => 'KIP', 'pek_ayah' => 'Karyawan Swasta', 'gaji_ayah' => '3.200.000',
            'ibu' => 'Rina Kusuma', 'pek_ibu' => 'Pedagang', 'gaji_ibu' => '2.500.000',
            'wali' => 'Pakde Suryo', 'alamat_wali' => 'Jl. Kawi No. 19, Malang', 'hp_wali' => '081333444555',
        ],
    ];

    foreach ($siswaList as $i => $s) {
        $row = $i + 2;
        $v   = $variasi[$i % count($variasi)];
        $tgl = !empty($s['tanggal_lahir']) ? substr((string) $s['tanggal_lahir'], 0, 10) : '2009-05-14';
        $almt = $s['alamat_rumah'] ?? 'Jl. Veteran No. 12, Malang';

        $rowData = [
            $s['nama_lengkap'],                                  // 1. NAMA LENGKAP
            $s['nama_panggilan'] ?? explode(' ', $s['nama_lengkap'])[0], // 2. NAMA PANGGILAN
            'X-' . (($i % 10) + 1),                              // 3. KELAS X
            (string) ($i + 1),                                   // 4. NO. PRESENSI
            $s['nisn'],                                          // 5. NISN
            $s['tempat_lahir'] ?? 'Malang',                      // 6. Tempat Lahir
            $tgl,                                                // 7. Tanggal Lahir
            $almt,                                               // 8. Alamat Tempat Tinggal
            $s['tinggal_bersama'] ?? 'Orang Tua',                // 9. Tinggal Bersama
            $v['hobi'],                                          // 10. Hobi
            $v['cita'],                                          // 11. Cita Cita
            $v['ekskul'],                                        // 12. Ekstrakurikuler
            'Jawa',                                              // 13. Suku
            $v['bahasa'],                                        // 14. Bahasa keseharian
            $v['makanan'],                                       // 15. Makanan Favorit
            $v['warna'],                                         // 16. Warna Favorit
            $v['online'],                                        // 17. Permainan Online
            $v['offline'],                                       // 18. Permainan Offline
            'Berkumpul bersama keluarga',                        // 19. Hal bahagia
            'Melihat orang tua kecewa',                          // 20. Hal sedih
            'Saya selalu berusaha memberikan yang terbaik',      // 21. Ingin ortu tahu
            'Saya senang belajar dengan diskusi',                // 22. Ingin guru tahu
            'Saya senang berteman dengan siapa saja',            // 23. Harap teman tahu
            $v['medsos'],                                        // 24. Media Sosial
            $v['prestasi'],                                      // 25. Prestasi SMP
            $v['anak_ke'],                                       // 26. Aku Anak ke-
            $v['saudara_tiri'],                                  // 27. Punya Saudara Tiri
            $v['ayah'],                                          // 28. Nama Ayah Kandung
            $v['bantuan'],                                       // 29. Bantuan pemerintah
            $v['pek_ayah'],                                      // 30. Pekerjaan Ayah
            $v['gaji_ayah'],                                     // 31. Gaji Ayah
            $almt,                                               // 32. Alamat Ayah
            '0812345600' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT), // 33. No HP Ayah
            $v['ibu'],                                           // 34. Nama Ibu Kandung
            $v['pek_ibu'],                                       // 35. Pekerjaan Ibu
            $v['gaji_ibu'],                                      // 36. Gaji Ibu
            $almt,                                               // 37. Alamat Ibu
            '0812987600' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT), // 38. No HP Ibu
            $v['wali'],                                          // 39. Nama Wali
            $v['alamat_wali'],                                   // 40. Alamat Wali
            $v['hp_wali'],                                       // 41. No HP Wali
            'Tidak', '-', '-', '-', '0',                         // 42..46 Ayah Tiri
            'Tidak', '-', '-', '-', '0',                         // 47..51 Ibu Tiri
        ];

        // Tambahkan jawaban dummy "Tidak" untuk pertanyaan angket 1..100
        for ($q = 1; $q <= 100; $q++) {
            $rowData[] = 'Tidak';
        }

        foreach ($rowData as $colIdx => $val) {
            $c = Coordinate::stringFromColumnIndex($colIdx + 1);
            $sheet->setCellValueExplicit($c . $row, (string) $val, DataType::TYPE_STRING);
        }
    }

    // Set lebar kolom untuk 51 kolom pertama agar rapi
    for ($col = 1; $col <= 51; $col++) {
        $c = Coordinate::stringFromColumnIndex($col);
        $sheet->getColumnDimension($c)->setWidth(22);
    }

    // Sheet 2: Petunjuk Pemetaan
    $petunjuk = $spreadsheet->createSheet();
    $petunjuk->setTitle('Petunjuk');
    $petunjuk->fromArray([
        ['Kolom Excel', 'Status', 'Disimpan ke Tabel Database'],
        ['NAMA LENGKAP, NAMA PANGGILAN, NISN, Tempat/Tanggal Lahir, Alamat, Tinggal Bersama, Aku Anak ke-', 'DIAMBIL', 'users & siswa'],
        ['KELAS X', 'DIAMBIL', 'kelas & riwayat_kelas'],
        ['Aku menggunakan bahasa keseharian ... .', 'DIAMBIL', 'siswa.bahasa_keseharian'],
        ['Makanan Favoritku adalah ... .', 'DIAMBIL', 'siswa.makanan_favorit'],
        ['Warna Favoritku adalah ... .', 'DIAMBIL', 'siswa.warna_favorit'],
        ['Permainan Online / Offline yang aku suka adalah ... .', 'DIAMBIL', 'siswa.permainan_online & permainan_offline'],
        ['Hobi', 'DIAMBIL', 'hobi & hobi_siswa'],
        ['Cita Cita', 'DIAMBIL', 'cita_cita & cita_cita_siswa'],
        ['Media Sosial yang aku punya adalah ... .', 'DIAMBIL', 'media_sosial'],
        ['Prestasi yang pernah aku raih saat SMP adalah ... .', 'DIAMBIL', 'prestasi_murid'],
        ['Punya Saudara Tiri', 'DIAMBIL', 'saudara_tiri'],
        ['Saya pernah mendapatkan bantuan pemerintah berupa', 'DIAMBIL', 'jenis_bantuan & bantuan_siswa'],
        ['Nama / Pekerjaan / Gaji / Alamat / No HP Ayah & Ibu Kandung', 'DIAMBIL', 'orang_tua (ayah & ibu)'],
        ['Nama / Alamat / No HP Wali', 'DIAMBIL', 'wali'],
        ['Pertanyaan 1 s.d. 100 & kolom abu-abu lainnya', 'DIABAIKAN', 'Tidak disimpan ke DB'],
    ], null, 'A1');
    $petunjuk->getStyle('A1:C1')->getFont()->setBold(true);
    foreach (['A', 'B', 'C'] as $pc) {
        $petunjuk->getColumnDimension($pc)->setAutoSize(true);
    }

    $sheet->getRowDimension(1)->setRowHeight(36);
    $sheet->freezePane('A2');
    $spreadsheet->setActiveSheetIndex(0);

    simpanSpreadsheet($spreadsheet, 'template_import_siswa.xlsx');
}

buatTemplateSiswaKuesioner($siswaDummy);

// ═════════════════════════════════════════════════════════════════════════════
// 2. TEMPLATE IMPORT NILAI RAPOR
// ═════════════════════════════════════════════════════════════════════════════
$dummyNilai = [];
foreach ($siswaDummy as $idx => $s) {
    $base = 78 + (($idx * 3) % 15);
    $dummyNilai[] = [
        $s['nisn'], '1',
        $base + 4, $base + 2, $base + 1, $base + 5, $base + 3,
        $base + 2, $base,     $base - 1, $base + 1, $base - 2,
        $base + 6, $base,     $base + 3, $base + 2, $base + 4,
    ];
}

buatTemplate('template_import_nilai_rapor.xlsx', 'Nilai Rapor', [
    'nisn'       => ['* NISN (sudah terdaftar)', '0081234501'],
    'semester'   => ['Semester (1, 2, ...)', '1'],
    'nilai_mat'  => ['Matematika', '88'],
    'nilai_ind'  => ['Bhs. Indonesia', '82'],
    'nilai_ing'  => ['Bhs. Inggris', '78'],
    'nilai_fis'  => ['Fisika', '90'],
    'nilai_kim'  => ['Kimia', '85'],
    'nilai_bio'  => ['Biologi', '80'],
    'nilai_eko'  => ['Ekonomi', '75'],
    'nilai_geo'  => ['Geografi', '70'],
    'nilai_sos'  => ['Sosiologi', '72'],
    'nilai_sej'  => ['Sejarah', '68'],
    'nilai_inf'  => ['Informatika', '91'],
    'nilai_sbd'  => ['Seni Budaya', '77'],
    'nilai_pjok' => ['PJOK', '80'],
    'nilai_pkn'  => ['Pend. Pancasila', '83'],
    'nilai_pai'  => ['PAI', '79'],
], [[1, 17, '145A32', 'D5F5E3']], $dummyNilai);

// ═════════════════════════════════════════════════════════════════════════════
// 3. TEMPLATE IMPORT ASESMEN (Format Kolom Sekolah)
// ═════════════════════════════════════════════════════════════════════════════
function buatTemplateAsesmenSekolah(array $siswaList): void
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Asesmen Siswa');

    $headers = [
        1  => 'NO',
        2  => 'NIS',
        3  => 'NAMA LENGKAP',
        4  => '',
        5  => 'AGM',
        6  => 'PANGGILAN',
        7  => 'KEPRIBADIAN',
        8  => 'Bljr',
        9  => 'Mapel Fav',
        10 => 'Multiple Intelegence',
        11 => 'RIASEC',
        12 => '',
        13 => '',
        14 => 'Konsul',
        15 => 'Mapel Sementara',
        16 => '',
        17 => '',
        18 => 'MENU KELAS PEMINATAN',
        19 => 'PROSPEK (PROFIL DIRI)',
    ];

    foreach ($headers as $col => $text) {
        $c = Coordinate::stringFromColumnIndex($col);
        $sheet->setCellValueExplicit($c . '1', $text, DataType::TYPE_STRING);
        $sheet->getStyle("{$c}2:{$c}1000")->getNumberFormat()->setFormatCode('@');
    }

    $sheet->mergeCells('K1:M1');
    $sheet->mergeCells('O1:Q1');

    $sheet->getStyle('A1:S1')->applyFromArray([
        'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '566573']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'AAAAAA']]],
    ]);

    foreach (['B1:C1' => '1E3A5F', 'J1:M1' => '6C3483', 'S1:S1' => '1F618D'] as $range => $rgb) {
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($rgb);
    }

    $paketDummy = [
        ['mi' => 'Logis-Matematis, Spasial',   'r' => ['I', 'R', 'C'], 'prospek' => 'Teknik Informatika (UB), Sistem Informasi (ITS)', 'kep' => 'Koleris',    'bljr' => 'Visual',     'fav' => 'Matematika', 'mapil' => ['Fisika', 'Informatika', 'Matematika TL'], 'menu' => 'MIPA - Teknik'],
        ['mi' => 'Naturalis, Logis-Matematis', 'r' => ['I', 'S', 'R'], 'prospek' => 'Kedokteran (UB), Farmasi (UGM)',                  'kep' => 'Melankolis', 'bljr' => 'Kinestetik', 'fav' => 'Biologi',    'mapil' => ['Biologi', 'Kimia', 'Fisika'],             'menu' => 'MIPA - Kesehatan'],
        ['mi' => 'Interpersonal, Linguistik',  'r' => ['E', 'S', 'C'], 'prospek' => 'Manajemen (UB), Akuntansi (UNAIR)',               'kep' => 'Sanguinis',  'bljr' => 'Auditori',   'fav' => 'Ekonomi',    'mapil' => ['Ekonomi', 'Sosiologi', 'Bhs Inggris TL'], 'menu' => 'IPS - Bisnis'],
        ['mi' => 'Linguistik, Intrapersonal',  'r' => ['S', 'A', 'E'], 'prospek' => 'Ilmu Hukum (UB), Hubungan Internasional (UGM)',   'kep' => 'Koleris',    'bljr' => 'Auditori',   'fav' => 'Sosiologi',  'mapil' => ['Sosiologi', 'Sejarah', 'Ekonomi'],        'menu' => 'IPS - Hukum & Sosial'],
        ['mi' => 'Spasial, Musikal',           'r' => ['A', 'I', 'R'], 'prospek' => 'Arsitektur (UB), Desain Komunikasi Visual (UM)',  'kep' => 'Plegmatis',  'bljr' => 'Visual',     'fav' => 'Seni Budaya','mapil' => ['Fisika', 'Informatika', 'Seni Budaya'],   'menu' => 'Saintek - Desain'],
    ];

    foreach ($siswaList as $i => $s) {
        $row = $i + 2;
        $d   = $paketDummy[$i % count($paketDummy)];
        $nis = !empty($s['nis']) ? $s['nis'] : $s['nisn'];

        $values = [
            $i + 1,
            $nis,
            $s['nama_lengkap'],
            $s['jenis_kelamin'] ?? 'L',
            $s['agama'] ?? 'Islam',
            $s['nama_panggilan'] ?? explode(' ', $s['nama_lengkap'])[0],
            $d['kep'],
            $d['bljr'],
            $d['fav'],
            $d['mi'],
            $d['r'][0],
            $d['r'][1],
            $d['r'][2],
            'Sudah',
            $d['mapil'][0],
            $d['mapil'][1],
            $d['mapil'][2],
            $d['menu'],
            $d['prospek'],
        ];

        foreach ($values as $colIdx => $val) {
            $c = Coordinate::stringFromColumnIndex($colIdx + 1);
            $sheet->setCellValueExplicit($c . $row, (string) $val, DataType::TYPE_STRING);
        }
    }

    $lastRow = count($siswaList) + 1;
    $sheet->getStyle("A1:S{$lastRow}")->applyFromArray([
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
    ]);

    for ($col = 1; $col <= 19; $col++) {
        $c = Coordinate::stringFromColumnIndex($col);
        $sheet->getColumnDimension($c)->setAutoSize(true);
    }

    $petunjuk = $spreadsheet->createSheet();
    $petunjuk->setTitle('Petunjuk');
    $petunjuk->fromArray([
        ['Kolom', 'Status di Sistem', 'Keterangan / Contoh'],
        ['NIS / NAMA LENGKAP', 'DIAMBIL (Pencocokan)', 'Mencocokkan ke NIS, NISN, atau Nama Lengkap siswa yang sudah diimport'],
        ['Multiple Intelegence', 'DIAMBIL', 'Nama kecerdasan dominan, mis: Logis-Matematis, Spasial, Linguistik, Naturalis, Interpersonal'],
        ['RIASEC (3 kolom)', 'DIAMBIL', '3 huruf kode Holland (R/I/A/S/E/C) di 3 kolom berturut-turut, mis: I | R | C'],
        ['PROSPEK (PROFIL DIRI)', 'DIAMBIL (Minat Prodi)', 'Minat prodi pilihan 1 & 2 dipisahkan koma atau garis miring, mis: Teknik Informatika (UB), Sistem Informasi (ITS)'],
        ['Kolom lainnya', 'DIABAIKAN', 'KEPRIBADIAN, Bljr, Mapel Fav, Konsul, Mapel Sementara, MENU KELAS PEMINATAN tidak disimpan ke DB'],
    ], null, 'A1');
    $petunjuk->getStyle('A1:C1')->getFont()->setBold(true);
    foreach (['A', 'B', 'C'] as $pc) {
        $petunjuk->getColumnDimension($pc)->setAutoSize(true);
    }

    $sheet->getRowDimension(1)->setRowHeight(32);
    $sheet->freezePane('A2');
    $spreadsheet->setActiveSheetIndex(0);

    simpanSpreadsheet($spreadsheet, 'template_import_asesmen.xlsx');
}

buatTemplateAsesmenSekolah($siswaDummy);
