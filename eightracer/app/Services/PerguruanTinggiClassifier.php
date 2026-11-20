<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class PerguruanTinggiClassifier
{
    /**
     * Klasifikasi jenis PT: Negeri, Swasta, Kedinasan, Luar Negeri.
     * Prioritas: dataset CSV → heuristik keyword → default Swasta.
     */
    public static function classify(string $namaPt): string
    {
        $namaPt = trim($namaPt);
        if ($namaPt === '') {
            return 'Swasta';
        }

        // 1. Coba dari dataset
        $fromDataset = self::fromDataset($namaPt);
        if ($fromDataset !== null) {
            // Dataset hanya punya negeri/swasta.
            // Tapi kalau keyword kedinasan/luar negeri cocok, tetap override.
            if (self::isKedinasan($namaPt)) {
                return 'Kedinasan';
            }
            if (self::isLuarNegeri($namaPt)) {
                return 'Luar Negeri';
            }
            return $fromDataset;
        }

        // 2. Fallback heuristik (PT tidak ada di dataset)
        if (self::isKedinasan($namaPt)) {
            return 'Kedinasan';
        }
        if (self::isLuarNegeri($namaPt)) {
            return 'Luar Negeri';
        }
        if (self::isNegeri($namaPt)) {
            return 'Negeri';
        }

        return 'Swasta';
    }

    /**
     * Cari di dataset LIST UNIVERSITAS.csv (di-cache agar tidak baca file berulang).
     * Return 'Negeri' / 'Swasta' / null.
     */
    private static function fromDataset(string $namaPt): ?string
    {
        $map = Cache::rememberForever('pt_status_map', function () {
            $path = storage_path('app/datasets/LIST UNIVERSITAS.csv');
            if (! file_exists($path)) {
                return [];
            }

            $map = [];
            $handle = fopen($path, 'r');
            $header = fgetcsv($handle);
            if (! $header) {
                fclose($handle);
                return [];
            }

            $headerLower = array_map(fn($h) => strtolower(trim((string) $h)), $header);
            $namaIdx   = array_search('nama', $headerLower, true);
            $statusIdx = array_search('status', $headerLower, true);

            if ($namaIdx === false || $statusIdx === false) {
                fclose($handle);
                return [];
            }

            while (($row = fgetcsv($handle)) !== false) {
                $nama   = strtoupper(trim((string) ($row[$namaIdx] ?? '')));
                $status = strtolower(trim((string) ($row[$statusIdx] ?? '')));
                if ($nama === '' || $status === '') {
                    continue;
                }
                $map[$nama] = $status === 'negeri' ? 'Negeri' : 'Swasta';
            }
            fclose($handle);
            return $map;
        });

        $key = strtoupper(trim($namaPt));
        if (isset($map[$key])) {
            return $map[$key];
        }

        // Pencocokan longgar: buang kata "UNIVERSITAS", "INSTITUT", dll.
        $normalized = self::normalizeForLookup($key);
        foreach ($map as $datasetName => $jenis) {
            if (self::normalizeForLookup($datasetName) === $normalized) {
                return $jenis;
            }
        }

        return null;
    }

    private static function normalizeForLookup(string $name): string
    {
        $name = strtoupper(trim($name));
        $name = preg_replace('/^(UNIVERSITAS|UNIV|INSTITUT|POLITEKNIK|SEKOLAH TINGGI|AKADEMI)\s+/', '', $name);
        $name = preg_replace('/[^A-Z0-9]+/', ' ', $name);
        return trim(preg_replace('/\s+/', ' ', $name));
    }

    /**
     * Keyword Kedinasan — TIDAK ada di dataset, jadi harus hardcode.
     */
    private static function isKedinasan(string $namaPt): bool
    {
        $u = strtoupper(trim($namaPt));
        $keywords = [
            'AKAMIGAS', 'PEM AKAMIGAS', 'POLITEKNIK ENERGI DAN MINERAL',
            'STPDN', 'IPDN', 'INSTITUT PEMERINTAHAN DALAM NEGERI',
            'AKADEMI KEPOLISIAN', 'AKPOL',
            'AKADEMI TNI', 'AKMIL', 'AKADEMI MILITER',
            'AKADEMI ANGKATAN LAUT', 'AAL',
            'AKADEMI ANGKATAN UDARA', 'AAU',
            'SEKOLAH TINGGI PENERBANGAN', 'STPI', 'STP CURUG',
            'POLITEKNIK PENERBANGAN',
            'POLITEKNIK KESEHATAN KEMENKES', 'POLTEKKES KEMENKES',
            'POLITEKNIK STATISTIK', 'STIS', 'SEKOLAH TINGGI ILMU STATISTIK',
            'POLITEKNIK IMIGRASI', 'POLITEKNIK KEIMIGRASIAN',
            'POLITEKNIK ILMU PELAYARAN', 'PIP SEMARANG', 'PIP MAKASSAR',
            'POLITEKNIK MARITIM NEGERI',
            'POLITEKNIK PERKERETAAPIAN', 'POLITEKNIK KESELAMATAN TRANSPORTASI',
            'POLITEKNIK PEMASYARAKATAN',
            'POLITEKNIK BEA DAN CUKAI', 'PKN STAN',
            'STAN', 'SEKOLAH TINGGI AKUNTANSI NEGARA',
            'POLITEKNIK KEUANGAN NEGARA',
            'UNIVERSITAS PERTAHANAN', 'UNHAN',
            'POLTEKPAR', 'POLITEKNIK PARIWISATA',
            'SEKOLAH TINGGI METEOROLOGI', 'STMKG', 'AKADEMI METEOROLOGI',
            'POLITEKNIK NEGERI', 'POLITEKNIK MANUFAKTUR',
            'POLITEKNIK ELEKTRONIKA NEGERI', 'POLITEKNIK KIMIA',
            'POLITEKNIK PERTANIAN NEGERI', 'POLITEKNIK PEMBANGUNAN',
            'POLITEKNIK WILAYAH', 'POLITEKNIK KELAUTAN',
        ];
        foreach ($keywords as $kw) {
            if (str_contains($u, $kw)) return true;
        }
        return false;
    }

    /**
     * Keyword Luar Negeri — TIDAK ada di dataset, jadi harus hardcode.
     */
    private static function isLuarNegeri(string $namaPt): bool
    {
        $u = strtoupper(trim($namaPt));
        $keywords = [
            'ASIA PACIFIC UNIVERSITY', 'RIKKYO', 'WASEDA', 'KEIO', 'TODAI', 'KYOTO',
            'NATIONAL UNIVERSITY OF', 'MELBOURNE', 'SYDNEY',
            'OXFORD', 'CAMBRIDGE', 'HARVARD', 'MASSACHUSETTS', 'STANFORD',
            'UNIVERSITI ', 'CHULALONGKORN', 'NANYANG',
        ];
        foreach ($keywords as $kw) {
            if (str_contains($u, $kw)) return true;
        }
        return false;
    }

    /**
     * Heuristik negeri — hanya dipakai jika PT tidak ada di dataset.
     */
    private static function isNegeri(string $namaPt): bool
    {
        $u = strtoupper(trim($namaPt));
        $keywords = [
            'UNIVERSITAS NEGERI', 'UNIVERSITAS BRAWIJAYA', 'UNIVERSITAS AIRLANGGA',
            'UNIVERSITAS HASANUDDIN', 'UNIVERSITAS INDONESIA',
            'INSTITUT TEKNOLOGI BANDUNG', 'INSTITUT TEKNOLOGI SEPULUH NOPEMBER',
            'UNIVERSITAS GADJAH MADA', 'UNIVERSITAS PENDIDIKAN INDONESIA',
            'UNIVERSITAS SEBELAS MARET', 'UNIVERSITAS PEMBANGUNAN NASIONAL',
            'UPN VETERAN', 'UNIVERSITAS ISLAM NEGERI', 'UIN ', 'IAIN ', 'STAIN ',
            'INSTITUT PERTANIAN BOGOR', 'IPB UNIVERSITY',
            'UNIVERSITAS UDAYANA', 'UNIVERSITAS MATARAM',
            'UNIVERSITAS LAMBUNG MANGKURAT', 'UNIVERSITAS JENDERAL SOEDIRMAN',
            'UNIVERSITAS DIPONEGORO', 'UNIVERSITAS SRIWIJAYA',
            'UNIVERSITAS ANDALAS', 'UNIVERSITAS RIAU', 'UNIVERSITAS SYIAH KUALA',
            'UNIVERSITAS SUMATERA UTARA', 'UNIVERSITAS NEGERI MEDAN',
            'UNIVERSITAS NEGERI PADANG', 'UNIVERSITAS PENDIDIKAN GANESHA',
            'UNIVERSITAS NUSA CENDANA', 'UNIVERSITAS HALU OLEO',
            'UNIVERSITAS TADULAKO', 'UNIVERSITAS BENGKULU',
            'UNIVERSITAS JAMBI', 'UNIVERSITAS BANGKA BELITUNG',
            'UNIVERSITAS PALANGKARAYA', 'UNIVERSITAS TANJUNGPURA',
            'UNIVERSITAS PATTIMURA', 'UNIVERSITAS CENDERAWASIH',
            'UNIVERSITAS PAPUA', 'UNIVERSITAS SAM RATULANGI',
            'UNIVERSITAS NEGERI GORONTALO', 'UNIVERSITAS NEGERI MANADO',
            'UNIVERSITAS MARITIM RAJA ALI HAJI', 'UNIVERSITAS SINGAPERBANGSA',
            'INSTITUT TEKNOLOGI SUMATERA',
        ];
        foreach ($keywords as $kw) {
            if (str_contains($u, $kw)) return true;
        }
        return false;
    }
}