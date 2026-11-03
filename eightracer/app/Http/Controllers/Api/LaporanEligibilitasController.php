<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HasilEligibilitas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LaporanEligibilitasController extends Controller
{
    public function exportExcel(Request $request): StreamedResponse
    {
        $query = HasilEligibilitas::with([
            'siswa.riwayatKelas.kelas',
            'periode',
            'detailSkorEligibilitas',
        ]);

        if ($request->filled('kelas')) {
            $kelas = $request->query('kelas');
            $query->whereHas('siswa.riwayatKelas.kelas', function ($q) use ($kelas) {
                $q->where('nama_kelas', 'like', '%' . $kelas . '%');
            });
        }

        if ($request->filled('jurusan_id')) {
            $jurusanId = $request->query('jurusan_id');
            $query->whereHas('siswa.riwayatKelas.kelas', function ($q) use ($jurusanId) {
                $q->where('id', $jurusanId);
            });
        }

        $data = $query->orderBy('peringkat', 'asc')
            ->orderByDesc('total_skor_akhir')
            ->get();

        $filename = 'laporan-eligibilitas-' . date('YmdHis') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($data) {
            $output = fopen('php://output', 'w');
            fputs($output, "\xEF\xBB\xBF");

            fputcsv($output, [
                'Peringkat',
                'NISN',
                'NIS',
                'Nama Lengkap',
                'Kelas',
                'Skor Rapor',
                'Skor TKA',
                'Skor Prestasi',
                'Skor Presensi',
                'Skor Karakter',
                'Skor Organisasi',
                'Total Skor Akhir',
                'Status Eligibilitas',
            ], ';');

            foreach ($data as $item) {
                $siswa = $item->siswa;
                $detail = $item->detailSkorEligibilitas;
                $kelas = null;

                if ($siswa && $siswa->riwayatKelas->isNotEmpty()) {
                    $riwayat = $siswa->riwayatKelas->first();
                    $kelas = $riwayat->kelas ? $riwayat->kelas->nama_kelas : null;
                }

                fputcsv($output, [
                    $item->peringkat,
                    $siswa ? $siswa->nisn : '',
                    $siswa ? $siswa->nis : '',
                    $siswa ? $siswa->nama_lengkap : '',
                    $kelas ?? '',
                    $detail ? $detail->skor_raw_rapor : '',
                    $detail ? $detail->skor_raw_tka : '',
                    $detail ? $detail->skor_raw_prestasi : '',
                    $detail ? $detail->skor_raw_presensi : '',
                    $detail ? $detail->skor_raw_karakter : '',
                    $detail ? $detail->skor_raw_organisasi : '',
                    $item->total_skor_akhir,
                    $item->status_eligible,
                ], ';');
            }

            fclose($output);
        }, 200, $headers);
    }

    public function exportPdf(Request $request): JsonResponse
    {
        $query = HasilEligibilitas::with([
            'siswa.riwayatKelas.kelas',
            'periode',
            'detailSkorEligibilitas',
        ]);

        if ($request->filled('kelas')) {
            $kelas = $request->query('kelas');
            $query->whereHas('siswa.riwayatKelas.kelas', function ($q) use ($kelas) {
                $q->where('nama_kelas', 'like', '%' . $kelas . '%');
            });
        }

        if ($request->filled('jurusan_id')) {
            $jurusanId = $request->query('jurusan_id');
            $query->whereHas('siswa.riwayatKelas.kelas', function ($q) use ($jurusanId) {
                $q->where('id', $jurusanId);
            });
        }

        $data = $query->orderBy('peringkat', 'asc')
            ->orderByDesc('total_skor_akhir')
            ->get();

        $formattedData = $data->map(function ($item) {
            $siswa = $item->siswa;
            $detail = $item->detailSkorEligibilitas;
            $kelas = null;

            if ($siswa && $siswa->riwayatKelas->isNotEmpty()) {
                $riwayat = $siswa->riwayatKelas->first();
                $kelas = $riwayat->kelas ? $riwayat->kelas->nama_kelas : null;
            }

            return [
                'peringkat' => $item->peringkat,
                'total_skor_akhir' => (float) $item->total_skor_akhir,
                'status_eligible' => $item->status_eligible,
                'siswa' => [
                    'id' => $siswa ? $siswa->id : null,
                    'nisn' => $siswa ? $siswa->nisn : null,
                    'nis' => $siswa ? $siswa->nis : null,
                    'nama_lengkap' => $siswa ? $siswa->nama_lengkap : null,
                    'kelas' => $kelas,
                ],
                'rincian_skor' => [
                    'rapor' => $detail ? (float) $detail->skor_raw_rapor : 0,
                    'tka' => $detail ? (float) $detail->skor_raw_tka : 0,
                    'prestasi' => $detail ? (float) $detail->skor_raw_prestasi : 0,
                    'presensi' => $detail ? (float) $detail->skor_raw_presensi : 0,
                    'karakter' => $detail ? (float) $detail->skor_raw_karakter : 0,
                    'organisasi' => $detail ? (float) $detail->skor_raw_organisasi : 0,
                ],
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Data laporan eligibilitas untuk dokumen PDF berhasil diambil.',
            'data' => [
                'metadata' => [
                    'judul' => 'Laporan Hasil Pemeringkatan Eligibilitas Siswa',
                    'tanggal_unduh' => date('Y-m-d H:i:s'),
                    'total_siswa' => $data->count(),
                ],
                'daftar_peringkat' => $formattedData,
            ],
        ], 200);
    }
}
