<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AlumniProgress;
use App\Models\PerguruanTinggi;
use App\Models\PrestasiMurid;
use App\Models\Siswa;
use App\Models\StudyTracer;
use App\Models\StudyTracerPrestasi;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AlumniFormController extends Controller
{
    private function getSiswa()
    {
        $user = Auth::user();
        $siswa = Siswa::where('user_id', $user->id)->first();
        if (! $siswa) {
            throw ValidationException::withMessages(['user' => 'Akun ini bukan siswa.']);
        }
        return $siswa;
    }

    public function status(Request $request)
    {
        $siswa = $this->getSiswa();

        $progress = AlumniProgress::where('siswa_id', $siswa->id)->first();
        $studyTracer = StudyTracer::where('siswa_id', $siswa->id)->first();

        return response()->json([
            'siswa' => [
                'id' => $siswa->id,
                'nama_lengkap' => $siswa->nama_lengkap,
                'nisn' => $siswa->nisn,
            ],
            'sudah_mengisi' => $progress !== null,
            'status_pendidikan' => $progress->status_pendidikan ?? null,
            'study_tracer_id' => $studyTracer->id ?? null,
            'tanggal_update' => $progress->tanggal_update ?? null,
        ]);
    }

    public function step1(Request $request)
    {
        $siswa = $this->getSiswa();

        $validated = $request->validate([
            'status' => 'required|in:kuliah,tidak_kuliah',
            'keterangan_tidak_kuliah' => 'required_if:status,tidak_kuliah|in:bekerja,wirausaha,lainnya',
        ]);

        if ($validated['status'] === 'tidak_kuliah') {
            AlumniProgress::updateOrCreate(
                ['siswa_id' => $siswa->id],
                [
                    'study_tracer_id' => null,
                    'tahun_lulus' => date('Y'),
                    'status_pendidikan' => $validated['keterangan_tidak_kuliah'] ?? 'tidak_kuliah',
                    'ipk_terkini' => null,
                    'tanggal_update' => Carbon::now()->toDateString(),
                ]
            );

            return response()->json([
                'message' => 'Status berhasil disimpan. Terima kasih telah mengisi form.',
                'lanjut_ke_step2' => false,
            ]);
        }

        // Jika kuliah, simpan sementara di session/atau kembalikan flag
        return response()->json([
            'message' => 'Status kuliah dipilih. Lanjutkan ke langkah 2.',
            'lanjut_ke_step2' => true,
        ]);
    }

    public function step2(Request $request)
    {
        $siswa = $this->getSiswa();

        $validated = $request->validate([
            'perguruan_tinggi_id' => 'nullable|exists:perguruan_tinggi,id',
            'nama_pt_baru' => 'required_without:perguruan_tinggi_id|nullable|string|max:255',
            'program_studi' => 'required|string|max:255',
            'strata_pendidikan' => 'required|in:D3,D4,S1,S2,S3',
            'jalur_masuk' => 'required|string|max:100',
            'sub_jalur_mandiri' => 'nullable|string|max:100',
            'pilihan_ke' => 'nullable|integer|min:1|max:5',
            'status_penerimaan' => 'required|in:diterima,tidak_diterima,menunggu',
            'nilai_rapor_pendaftaran' => 'nullable|numeric|min:0|max:100',
            'skor_utbk' => 'nullable|numeric|min:0|max:1000',
            'is_pelamar_kipk' => 'boolean',
            'kontak_wa_terbaru' => 'nullable|string|min:9|max:20',
        ]);

        // Resolve PT
        $perguruanTinggi = null;
        if (! empty($validated['perguruan_tinggi_id'])) {
            $perguruanTinggi = PerguruanTinggi::find($validated['perguruan_tinggi_id']);
        } elseif (! empty($validated['nama_pt_baru'])) {
            $perguruanTinggi = PerguruanTinggi::firstOrCreate(
                ['nama_pt' => strtoupper(trim($validated['nama_pt_baru']))],
                ['jenis_pt' => null, 'kode_pt' => null, 'lokasi' => null]
            );
        }

        if (! $perguruanTinggi) {
            throw ValidationException::withMessages(['perguruan_tinggi_id' => 'Perguruan tinggi tidak valid.']);
        }

        DB::transaction(function () use ($siswa, $validated, $perguruanTinggi) {
            $studyTracer = StudyTracer::updateOrCreate(
                [
                    'siswa_id' => $siswa->id,
                    'perguruan_tinggi_id' => $perguruanTinggi->id,
                    'program_studi' => trim($validated['program_studi']),
                    'jalur_masuk' => $validated['jalur_masuk'],
                ],
                [
                    'strata_pendidikan' => $validated['strata_pendidikan'],
                    'sub_jalur_mandiri' => $validated['sub_jalur_mandiri'] ?? null,
                    'pilihan_ke' => $validated['pilihan_ke'] ?? null,
                    'status_penerimaan' => $validated['status_penerimaan'],
                    'nilai_rapor_pendaftaran' => $validated['nilai_rapor_pendaftaran'] ?? null,
                    'skor_utbk' => $validated['skor_utbk'] ?? null,
                    'is_pelamar_kipk' => $validated['is_pelamar_kipk'] ?? false,
                    'kontak_wa_terbaru' => $validated['kontak_wa_terbaru'] ?? null,
                    'izinkan_tampil_di_buku' => false,
                    'tanggal_pengisian' => Carbon::now()->toDateString(),
                ]
            );

            AlumniProgress::updateOrCreate(
                ['siswa_id' => $siswa->id],
                [
                    'study_tracer_id' => $studyTracer->id,
                    'tahun_lulus' => date('Y'),
                    'status_pendidikan' => 'kuliah',
                    'ipk_terkini' => null,
                    'tanggal_update' => Carbon::now()->toDateString(),
                ]
            );
        });

        return response()->json([
            'message' => 'Data kuliah berhasil disimpan.',
            'lanjut_ke_step3' => true,
        ]);
    }

    public function step3(Request $request)
    {
        $siswa = $this->getSiswa();

        $validated = $request->validate([
            'prestasi' => 'nullable|array',
            'prestasi.*.nama_kompetisi' => 'required_with:prestasi|string|max:255',
            'prestasi.*.tingkat' => 'required_with:prestasi|in:Sekolah,Kota,Provinsi,Nasional,Internasional',
            'prestasi.*.peringkat' => 'nullable|string|max:50',
            'prestasi.*.tahun' => 'nullable|integer|min:2000|max:' . date('Y'),
        ]);

        $studyTracer = StudyTracer::where('siswa_id', $siswa->id)->first();

        if (! $studyTracer) {
            throw ValidationException::withMessages(['study_tracer' => 'Siswa belum mengisi data kuliah (step 2).']);
        }

        if (! empty($validated['prestasi'])) {
            foreach ($validated['prestasi'] as $p) {
                $prestasi = PrestasiMurid::updateOrCreate(
                    [
                        'siswa_id' => $siswa->id,
                        'nama_kompetisi' => trim($p['nama_kompetisi']),
                        'tahun' => $p['tahun'] ?? null,
                    ],
                    [
                        'jenis_prestasi' => 'Lomba',
                        'bidang' => null,
                        'tingkat' => $p['tingkat'],
                        'peringkat' => $p['peringkat'] ?? null,
                        'bobot_persentase' => null,
                        'status_verifikasi' => 'pending',
                        'penyelenggara' => null,
                        'file_bukti_url' => null,
                    ]
                );

                StudyTracerPrestasi::updateOrCreate(
                    [
                        'study_tracer_id' => $studyTracer->id,
                        'prestasi_murid_id' => $prestasi->id,
                    ]
                );
            }
        }

        return response()->json([
            'message' => 'Data prestasi berhasil disimpan.',
            'lanjut_ke_step4' => true,
        ]);
    }

    public function step4(Request $request)
    {
        $siswa = $this->getSiswa();

        $validated = $request->validate([
            'kontak_wa_terbaru' => 'required|string|min:9|max:20',
            'izinkan_tampil_di_buku' => 'required|boolean',
        ]);

        $studyTracer = StudyTracer::where('siswa_id', $siswa->id)->first();

        if ($studyTracer) {
            $studyTracer->update([
                'kontak_wa_terbaru' => $validated['kontak_wa_terbaru'],
                'izinkan_tampil_di_buku' => $validated['izinkan_tampil_di_buku'],
            ]);
        }

        AlumniProgress::updateOrCreate(
            ['siswa_id' => $siswa->id],
            ['tanggal_update' => Carbon::now()->toDateString()]
        );

        return response()->json([
            'message' => 'Form berhasil diselesaikan.',
        ]);
    }

    public function summary(Request $request)
    {
        $siswa = $this->getSiswa();

        $progress = AlumniProgress::where('siswa_id', $siswa->id)->first();
        $studyTracer = StudyTracer::with('perguruanTinggi')->where('siswa_id', $siswa->id)->first();
        $prestasi = PrestasiMurid::where('siswa_id', $siswa->id)->get();

        return response()->json([
            'status' => $progress->status_pendidikan ?? null,
            'kuliah' => $studyTracer ? [
                'perguruan_tinggi' => $studyTracer->perguruanTinggi->nama_pt ?? null,
                'program_studi' => $studyTracer->program_studi,
                'strata_pendidikan' => $studyTracer->strata_pendidikan,
                'jalur_masuk' => $studyTracer->jalur_masuk,
                'sub_jalur_mandiri' => $studyTracer->sub_jalur_mandiri,
                'pilihan_ke' => $studyTracer->pilihan_ke,
                'status_penerimaan' => $studyTracer->status_penerimaan,
                'nilai_rapor_pendaftaran' => $studyTracer->nilai_rapor_pendaftaran,
                'skor_utbk' => $studyTracer->skor_utbk,
                'is_pelamar_kipk' => $studyTracer->is_pelamar_kipk,
                'kontak_wa_terbaru' => $studyTracer->kontak_wa_terbaru,
                'izinkan_tampil_di_buku' => $studyTracer->izinkan_tampil_di_buku,
            ] : null,
            'prestasi' => $prestasi->map(fn($p) => [
                'nama_kompetisi' => $p->nama_kompetisi,
                'tingkat' => $p->tingkat,
                'peringkat' => $p->peringkat,
                'tahun' => $p->tahun,
            ]),
        ]);
    }
}
