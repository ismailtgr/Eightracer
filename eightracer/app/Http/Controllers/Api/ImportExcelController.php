<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\NilaiRapor;
use App\Models\RekapPresensi;
use App\Models\RiwayatKelas;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportExcelController extends Controller
{
    public function importExcel(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
        ]);

        $file = $request->file('file');
        $realPath = $file->getRealPath();

        try {
            $spreadsheet = IOFactory::load($realPath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray(null, true, true, true);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal membaca berkas: ' . $e->getMessage(),
            ], 422);
        }

        if (empty($rows) || count($rows) < 2) {
            return response()->json([
                'status' => 'error',
                'message' => 'Berkas Excel/CSV tidak memiliki baris data.',
            ], 422);
        }

        $headerRow = array_shift($rows);
        $headerMap = [];
        foreach ($headerRow as $col => $name) {
            if ($name !== null) {
                $cleanName = strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '_', (string) $name)));
                $headerMap[$cleanName] = $col;
            }
        }

        $successCount = 0;
        $failedRows = [];

        foreach ($rows as $index => $row) {
            $rowNum = $index;

            $nisn = trim((string) ($row[$headerMap['nisn'] ?? 'A'] ?? ''));
            $nama = trim((string) ($row[$headerMap['nama'] ?? ($headerMap['nama_lengkap'] ?? 'B')] ?? ''));

            if (empty($nisn) || empty($nama)) {
                $failedRows[] = [
                    'baris' => $rowNum,
                    'alasan' => 'Kolom NISN atau Nama Lengkap kosong.',
                ];
                continue;
            }

            try {
                DB::transaction(function () use ($row, $headerMap, $nisn, $nama) {
                    $nis = trim((string) ($row[$headerMap['nis'] ?? ''] ?? ''));
                    $jk = strtoupper(trim((string) ($row[$headerMap['jenis_kelamin'] ?? ($headerMap['jk'] ?? '')] ?? '')));
                    $jk = in_array($jk, ['L', 'P']) ? $jk : 'L';
                    $kelasNama = trim((string) ($row[$headerMap['kelas'] ?? ($headerMap['nama_kelas'] ?? '')] ?? ''));
                    $nilaiTka = isset($headerMap['nilai_tka']) && is_numeric($row[$headerMap['nilai_tka']]) ? (float) $row[$headerMap['nilai_tka']] : null;

                    $user = User::firstOrCreate(
                        ['username' => $nisn],
                        [
                            'password' => Hash::make('password123'),
                            'role' => 'siswa',
                        ]
                    );

                    $siswa = Siswa::updateOrCreate(
                        ['nisn' => $nisn],
                        [
                            'user_id' => $user->id,
                            'nis' => !empty($nis) ? $nis : null,
                            'nama_lengkap' => $nama,
                            'jenis_kelamin' => $jk,
                            'nilai_tka' => $nilaiTka,
                        ]
                    );

                    if (!empty($kelasNama)) {
                        $tingkat = 12;
                    if (preg_match('/(10|11|12|X|XI|XII)/i', $kelasNama, $m)) {
                        $u = strtoupper($m[1]);
                        $tingkat = ($u === '10' || $u === 'X') ? 10 : (($u === '11' || $u === 'XI') ? 11 : 12);
                    }
                    $kelas = Kelas::firstOrCreate(
                        ['nama_kelas' => $kelasNama],
                        ['tingkat' => $tingkat, 'tahun_ajaran' => date('Y') . '/' . (date('Y') + 1)]
                    );
                        RiwayatKelas::updateOrCreate(
                            ['siswa_id' => $siswa->id, 'kelas_id' => $kelas->id],
                            ['tanggal_mulai' => now()->toDateString(), 'keterangan' => 'Impor massal ' . date('Y')]
                        );
                    }

                    if (isset($headerMap['nilai_rapor']) && is_numeric($row[$headerMap['nilai_rapor']])) {
                        $mapelUmum = MataPelajaran::firstOrCreate(
                            ['nama_mapel' => 'Rata-rata Rapor'],
                            ['jenis_mapel' => 'U', 'kelompok' => 'Umum', 'tersedia_sebagai_pilihan' => false]
                        );
                        NilaiRapor::updateOrCreate(
                            [
                                'siswa_id' => $siswa->id,
                                'mata_pelajaran_id' => $mapelUmum->id,
                                'semester' => 5,
                            ],
                            ['nilai_akhir' => (float) $row[$headerMap['nilai_rapor']]]
                        );
                    }

                    if (isset($headerMap['kehadiran']) && is_numeric($row[$headerMap['kehadiran']])) {
                        $persen = (float) $row[$headerMap['kehadiran']];
                        $persen = $persen > 100 ? 100 : ($persen < 0 ? 0 : $persen);
                        RekapPresensi::updateOrCreate(
                            [
                                'siswa_id' => $siswa->id,
                                'semester' => 5,
                            ],
                            [
                                'persentase_kehadiran' => $persen,
                                'jumlah_hadir' => (int) round(($persen / 100) * 100),
                                'jumlah_sakit' => 0,
                                'jumlah_izin' => 0,
                                'jumlah_alpa' => 0,
                            ]
                        );
                    }
                });

                $successCount++;
            } catch (\Throwable $e) {
                $failedRows[] = [
                    'baris' => $rowNum,
                    'nisn' => $nisn,
                    'alasan' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Proses impor data siswa dan akademik selesai.',
            'data' => [
                'total_baris_diproses' => count($rows),
                'berhasil' => $successCount,
                'gagal' => count($failedRows),
                'rincian_gagal' => $failedRows,
            ],
        ], 200);
    }
}
