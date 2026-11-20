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
use Illuminate\Validation\Rule;

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
            ],
            'sudah_mengisi' => $progress !== null,
            'step_1_selesai' => $studyTracer !== null,
            'data_kuliah' => $studyTracer ? [
                'perguruan_tinggi' => $studyTracer->perguruanTinggi->nama_pt ?? null,
                'program_studi' => $studyTracer->program_studi,
                'jalur_masuk' => $studyTracer->jalur_masuk,
            ] : null,
        ]);
    }

    // ==========================================
    // BAGIAN 1: DATA PENERIMAAN (KAMPUS)
    // ==========================================
    public function submitStep1(Request $request)
    {
        $siswa = $this->getSiswa();

        $validated = $request->validate([
            'strata_pendidikan' => 'required|in:D3,D4,S1',
            'program_studi' => 'required|string|max:255',
            'nama_pt' => 'required|string|max:255',
            'jenis_pt' => 'required|in:Negeri,Swasta,Ikatan Dinas,Luar Negeri',
            'jalur_masuk' => 'required|string|max:100',
            'pilihan_ke' => 'required|string', // 1,2,3,4, atau Tanpa Pilihan
            'kontak_wa_terbaru' => 'required|string|min:9|max:20',
            'is_pelamar_kipk' => 'required|boolean',
            'beasiswa' => 'nullable|string|max:255',
            'lembaga_penyalur' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($siswa, $validated) {
            // 1. Simpan Perguruan Tinggi
            $perguruanTinggi = PerguruanTinggi::firstOrCreate(
                ['nama_pt' => strtoupper(trim($validated['nama_pt']))],
                [
                    'jenis_pt' => $validated['jenis_pt'],
                    'kode_pt' => null,
                    'lokasi' => $validated['lembaga_penyalur'] ? json_encode(['lembaga_penyalur' => $validated['lembaga_penyalur']]) : null
                ]
            );

            // 2. Simpan Study Tracer (Skor UTBK diisi nanti di Step 2)
            $pilihan = is_numeric($validated['pilihan_ke']) ? (int)$validated['pilihan_ke'] : null;

            $studyTracer = StudyTracer::updateOrCreate(
                [
                    'siswa_id' => $siswa->id,
                    'perguruan_tinggi_id' => $perguruanTinggi->id,
                    'program_studi' => trim($validated['program_studi']),
                    'jalur_masuk' => $validated['jalur_masuk'],
                ],
                [
                    'strata_pendidikan' => $validated['strata_pendidikan'],
                    'pilihan_ke' => $pilihan,
                    'status_penerimaan' => 'diterima',
                    'is_pelamar_kipk' => $validated['is_pelamar_kipk'],
                    'kontak_wa_terbaru' => $validated['kontak_wa_terbaru'],
                    'izinkan_tampil_di_buku' => true,
                    'tanggal_pengisian' => Carbon::now()->toDateString(),
                ]
            );

            // 3. Simpan Alumni Progress
            AlumniProgress::updateOrCreate(
                ['siswa_id' => $siswa->id],
                [
                    'study_tracer_id' => $studyTracer->id,
                    'tahun_lulus' => date('Y'),
                    'status_pendidikan' => 'kuliah',
                    'tanggal_update' => Carbon::now()->toDateString(),
                ]
            );

            // 4. Simpan Beasiswa ke tabel prestasi_murid
            if (!empty($validated['beasiswa']) && strtoupper($validated['beasiswa']) !== 'TIDAK') {
                $prestasiBeasiswa = PrestasiMurid::updateOrCreate(
                    ['siswa_id' => $siswa->id, 'nama_kompetisi' => 'BEASISWA: ' . strtoupper($validated['beasiswa'])],
                    ['jenis_prestasi' => 'Beasiswa', 'tingkat' => 'Perguruan Tinggi', 'status_verifikasi' => 'pending']
                );
                StudyTracerPrestasi::firstOrCreate(['study_tracer_id' => $studyTracer->id, 'prestasi_murid_id' => $prestasiBeasiswa->id]);
            }
        });

        return response()->json([
            'message' => 'Data penerimaan berhasil disimpan. Silakan lanjut ke pendataan skor UTBK.',
            'lanjut_ke_step2' => true,
        ]);
    }

    public function daftarPtn()
    {
        return response()->json([
            'data' => array_merge(
                config('alumni.daftar_ptn_snbp', []),
                ['LAINNYA']
            ),
        ]);
    }


    public function submitSnbp(Request $request)
    {
        $siswa = $this->getSiswa();

        $daftarPtn = config('alumni.daftar_ptn_snbp', []);

        $validated = $request->validate([
            'strata_pendidikan' => 'required|in:S1,D4,D3',

            'program_studi' => 'required|string|max:255',

            'ptn' => [
                'required',
                'string',
                Rule::in(array_merge($daftarPtn, ['LAINNYA'])),
            ],

            'nama_pt_lainnya' => [
                'nullable',
                'required_if:ptn,LAINNYA',
                'string',
                'max:255',
            ],

            'pilihan_ke' => 'required|integer|in:1,2',

            'kontak_wa_terbaru' => 'required|string|min:9|max:20',

            'is_pelamar_kipk' => 'required|boolean',

            'peringkat_eligible_pdss' => 'required|integer|min:1',

            'is_memiliki_sertifikat_snbp' => 'required|boolean',

            'deskripsi_sertifikat_snbp' => 'nullable|string|max:5000',
        ]);

        $memilikiSertifikat = (bool) $validated['is_memiliki_sertifikat_snbp'];

        $deskripsiSertifikat = trim(
            $validated['deskripsi_sertifikat_snbp'] ?? ''
        );

        if ($memilikiSertifikat && $deskripsiSertifikat === '') {
            throw ValidationException::withMessages([
                'deskripsi_sertifikat_snbp' =>
                'Deskripsi sertifikat wajib diisi jika memiliki sertifikat.',
            ]);
        }

        if (! $memilikiSertifikat) {
            $deskripsiSertifikat = '-';
        }

        $namaPt = $validated['ptn'] === 'LAINNYA'
            ? $validated['nama_pt_lainnya']
            : $validated['ptn'];

        $namaPt = mb_strtoupper(trim($namaPt));
        $programStudi = mb_strtoupper(trim($validated['program_studi']));

        $studyTracer = DB::transaction(function () use (
            $siswa,
            $validated,
            $namaPt,
            $programStudi,
            $memilikiSertifikat,
            $deskripsiSertifikat
        ) {
            $perguruanTinggi = PerguruanTinggi::firstOrCreate(
                ['nama_pt' => $namaPt],
                [
                    'jenis_pt' => 'Negeri',
                    'kode_pt' => null,
                    'lokasi' => null,
                ]
            );

            $existingRecords = StudyTracer::where('siswa_id', $siswa->id)
                ->where('jalur_masuk', 'SNBP')
                ->lockForUpdate()
                ->get();

            if ($existingRecords->count() > 1) {
                throw ValidationException::withMessages([
                    'snbp' => 'Ditemukan lebih dari satu data SNBP. Silakan hubungi administrator.',
                ]);
            }

            $studyTracer = $existingRecords->first() ?? new StudyTracer();

            $studyTracer->fill([
                'siswa_id' => $siswa->id,
                'perguruan_tinggi_id' => $perguruanTinggi->id,
                'strata_pendidikan' => $validated['strata_pendidikan'],
                'program_studi' => $programStudi,
                'jalur_masuk' => 'SNBP',
                'pilihan_ke' => $validated['pilihan_ke'],
                'status_penerimaan' => 'diterima',
                'kontak_wa_terbaru' => $validated['kontak_wa_terbaru'],
                'is_pelamar_kipk' => $validated['is_pelamar_kipk'],
                'izinkan_tampil_di_buku' => true,
                'tanggal_pengisian' => Carbon::now()->toDateString(),
                'peringkat_eligible_pdss' => $validated['peringkat_eligible_pdss'],
                'is_memiliki_sertifikat_snbp' => $memilikiSertifikat,
                'deskripsi_sertifikat_snbp' => $memilikiSertifikat
                    ? $deskripsiSertifikat
                    : '-',
            ]);

            $studyTracer->save();

            return $studyTracer;
        });

        return response()->json([
            'message' => 'Data penerimaan SNBP berhasil disimpan.',
            'jalur_masuk' => 'SNBP',
            'data' => [
                'strata_pendidikan' => $studyTracer->strata_pendidikan,
                'program_studi' => $studyTracer->program_studi,
                'nama_pt' => $namaPt,
                'pilihan_ke' => $studyTracer->pilihan_ke,
                'peringkat_eligible_pdss' => $studyTracer->peringkat_eligible_pdss,
                'is_memiliki_sertifikat_snbp' => $studyTracer->is_memiliki_sertifikat_snbp,
                'deskripsi_sertifikat_snbp' => $studyTracer->deskripsi_sertifikat_snbp,
            ],
        ], 200);
    }


    // ==========================================
    // BAGIAN 2: PENDATAAN SKOR UTBK & PRESTASI
    // ==========================================
    public function submitStep2(Request $request)
    {
        $siswa = $this->getSiswa();
        $studyTracer = StudyTracer::where('siswa_id', $siswa->id)->first();

        if (! $studyTracer) {
            throw ValidationException::withMessages(['error' => 'Selesaikan Bagian 1 terlebih dahulu.']);
        }

        $validated = $request->validate([
            'kpu' => 'nullable|numeric',
            'kk' => 'nullable|numeric',
            'ppu' => 'nullable|numeric',
            'kbm' => 'nullable|numeric',
            'lbin_saintek' => 'nullable|numeric',
            'lbin_soshum' => 'nullable|numeric',
            'lbig' => 'nullable|numeric',
            'pm' => 'nullable|numeric',
            'lampiran_prestasi' => 'nullable|string',
        ]);

        DB::transaction(function () use ($siswa, $studyTracer, $validated) {
            // 1. Hitung Rata-rata UTBK (jika diisi)
            $vals = array_filter([
                $validated['kpu'] ?? 0,
                $validated['kk'] ?? 0,
                $validated['ppu'] ?? 0,
                $validated['kbm'] ?? 0,
                $validated['lbin_saintek'] ?? 0,
                $validated['lbin_soshum'] ?? 0,
                $validated['lbig'] ?? 0,
                $validated['pm'] ?? 0
            ], fn($v) => $v > 0);

            $skorUtbk = null;
            if (count($vals) > 0) {
                $skorUtbk = round(array_sum($vals) / count($vals), 5);
                $studyTracer->update(['skor_utbk' => $skorUtbk]);
            }

            // 2. Simpan Rincian UTBK ke prestasi_murid
            if ($skorUtbk !== null) {
                $rincian = json_encode([
                    'KPU' => $validated['kpu'] ?? 0,
                    'KK' => $validated['kk'] ?? 0,
                    'PPU' => $validated['ppu'] ?? 0,
                    'KBM' => $validated['kbm'] ?? 0,
                    'LBIN SAINTEK' => $validated['lbin_saintek'] ?? 0,
                    'LBIN SOSHUM' => $validated['lbin_soshum'] ?? 0,
                    'LBIG' => $validated['lbig'] ?? 0,
                    'PM' => $validated['pm'] ?? 0
                ]);
                $prestasiUtbk = PrestasiMurid::updateOrCreate(
                    ['siswa_id' => $siswa->id, 'nama_kompetisi' => 'RINCIAN SKOR UTBK'],
                    ['jenis_prestasi' => 'Skor UTBK', 'bidang' => $rincian, 'status_verifikasi' => 'verified']
                );
                StudyTracerPrestasi::firstOrCreate(['study_tracer_id' => $studyTracer->id, 'prestasi_murid_id' => $prestasiUtbk->id]);
            }

            // 3. Simpan Lampiran Prestasi (teks)
            if (!empty($validated['lampiran_prestasi'])) {
                $prestasiTeks = PrestasiMurid::updateOrCreate(
                    ['siswa_id' => $siswa->id, 'nama_kompetisi' => 'PRESTASI LAINNYA'],
                    ['jenis_prestasi' => 'Lainnya', 'bidang' => $validated['lampiran_prestasi'], 'status_verifikasi' => 'pending']
                );
                StudyTracerPrestasi::firstOrCreate(['study_tracer_id' => $studyTracer->id, 'prestasi_murid_id' => $prestasiTeks->id]);
            }
        });

        return response()->json([
            'message' => 'Pendataan skor UTBK dan prestasi berhasil diselesaikan.',
        ]);
    }
}
