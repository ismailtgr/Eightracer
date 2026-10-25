<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mapping kolom per sheet Excel
    |--------------------------------------------------------------------------
    | Kunci = nama sheet (case-insensitive, akan dinormalisasi)
    | Nilai = array mapping:
    |   - nama       : kolom nama siswa (wajib)
    |   - pt         : kolom nama perguruan tinggi (wajib)
    |   - prodi      : kolom nama program studi (wajib)
    |   - strata     : kolom jenjang (opsional; null jika tidak ada)
    |   - strata_from: kolom sumber jenjang jika bergabung (opsional)
    |   - pilihan_ke : kolom pilihan ke-
    |   - kontak_wa  : kolom nomor WA
    |   - kipk       : kolom status KIP-K
    |   - skor_utbk  : kolom skor/rata-rata UTBK
    |   - jalur_masuk: nama jalur yang disimpan (override nama sheet)
    */

    'sheets' => [
        'SNBP' => [
            'nama'        => 'NAMA',
            'pt'          => 'PERGURUAN TINGGI',
            'prodi'       => '4',              // kolom tanpa header
            'strata'      => 'PROGRAM STUDI',  // berisi S1/D4/D3
            'strata_from' => null,
            'pilihan_ke'  => 'PIL',
            'kontak_wa'   => 'NOMOR WA',
            'kipk'        => 'KIPK',
            'skor_utbk'   => null,
            'jalur_masuk' => 'SNBP',
        ],

        'SNBT' => [
            'nama'        => 'Nama',
            'pt'          => 'Jurusan/Program Studi/Departemen',  // berisi universitas
            'prodi'       => '4',                                  // kolom tanpa header
            'strata'      => 'Nama Perguruan Tinggi',              // berisi S1/D4/D3
            'strata_from' => null,
            'pilihan_ke'  => 'Pil',
            'kontak_wa'   => 'Nomor WA',
            'kipk'        => 'KIPK',
            'skor_utbk'   => null,
            'jalur_masuk' => 'SNBT',
        ],

        'SIMAMA' => [
            'nama'        => 'NAMA',
            'pt'          => 'PERGURUAN TINGGI',
            'prodi'       => 'JURUSAN/PROGAM STUDI',
            'strata'      => null,
            'strata_from' => 'JURUSAN/PROGAM STUDI',
            'pilihan_ke'  => 'PIL',
            'kontak_wa'   => 'NO. HP',
            'kipk'        => null,
            'skor_utbk'   => null,
            'jalur_masuk' => 'SIMAMA',
        ],

        'SIMAMI' => [
            'nama'        => 'NAMA',
            'pt'          => 'PERGURUAN TINGGI',
            'prodi'       => 'JURUSAN/PROGAM STUDI',
            'strata'      => null,
            'strata_from' => 'JURUSAN/PROGAM STUDI',
            'pilihan_ke'  => 'PIL',
            'kontak_wa'   => 'NO. HP',
            'kipk'        => null,
            'skor_utbk'   => null,
            'jalur_masuk' => 'SIMAMI',
        ],

        'SERTIFIKAT SNBP' => [
            'nama'        => 'NAMA LENGKAP',
            'pt'          => 'PTN',
            'prodi'       => 'PROGRAM STUDI',
            'strata'      => 'JENJANG',
            'strata_from' => null,
            'pilihan_ke'  => null,
            'kontak_wa'   => null,
            'kipk'        => null,
            'skor_utbk'   => null,
            'jalur_masuk' => 'SNBP',
        ],

        'SKOR UTBK (MANDIRI)' => [
            'nama'        => 'NAMA LENGKAP',
            'pt'          => 'PTN',
            'prodi'       => 'PROGRAM STUDI',
            'strata'      => 'JENJANG',
            'strata_from' => null,
            'pilihan_ke'  => null,
            'kontak_wa'   => null,
            'kipk'        => null,
            'skor_utbk'   => 'RATA2',
            'jalur_masuk' => 'MANDIRI',
        ],
    ],
];