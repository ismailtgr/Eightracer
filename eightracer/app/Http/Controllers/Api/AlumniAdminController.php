<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AlumniProgress;
use App\Models\BukuAlumni;
use App\Models\PerguruanTinggi;
use App\Models\Siswa;
use App\Models\StudyTracer;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AlumniAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = StudyTracer::with(['siswa', 'perguruanTinggi', 'bukuAlumni']);

        if ($request->filled('nama_lengkap')) {
            $query->whereHas('siswa', function ($q) use ($request) {
                $q->where('nama_lengkap', 'like', '%' . $request->query('nama_lengkap') . '%');
            });
        }

        if ($request->filled('ptn_diterima')) {
            $query->whereHas('perguruanTinggi', function ($q) use ($request) {
                $q->where('nama_pt', 'like', '%' . $request->query('ptn_diterima') . '%');
            });
        }

        if ($request->filled('prodi_diterima')) {
            $query->where('program_studi', 'like', '%' . $request->query('prodi_diterima') . '%');
        }

        if ($request->filled('tahun_lulus')) {
            $query->whereHas('bukuAlumni', function ($q) use ($request) {
                $q->where('tahun_lulusan', $request->query('tahun_lulus'));
            });
        }

        $alumni = $query->paginate(10);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar data alumni berhasil diambil.',
            'data' => $alumni,
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'siswa_id' => ['required', 'exists:siswa,id'],
            'ptn_diterima' => ['required', 'string', 'max:255'],
            'prodi_diterima' => ['required', 'string', 'max:255'],
            'strata_pendidikan' => ['nullable', 'string', 'max:50'],
            'jalur_masuk' => ['required', 'string', 'max:100'],
            'sub_jalur_mandiri' => ['nullable', 'string', 'max:100'],
            'tahun_lulus' => ['nullable', 'string', 'max:10'],
            'pilihan_ke' => ['nullable', 'integer'],
            'status_penerimaan' => ['nullable', Rule::in(['menunggu', 'diterima', 'tidak_diterima'])],
            'nilai_rapor_pendaftaran' => ['nullable', 'numeric'],
            'skor_utbk' => ['nullable', 'numeric'],
            'kontak_wa_terbaru' => ['nullable', 'string', 'max:50'],
            'is_pelamar_kipk' => ['nullable', 'boolean'],
            'izinkan_tampil_di_buku' => ['nullable', 'boolean'],
        ]);

        $alumni = DB::transaction(function () use ($validated) {
            $pt = PerguruanTinggi::firstOrCreate(['nama_pt' => $validated['ptn_diterima']]);

            $bukuAlumniId = null;
            if (! empty($validated['tahun_lulus'])) {
                $buku = BukuAlumni::firstOrCreate(
                    ['tahun_lulusan' => $validated['tahun_lulus']],
                    [
                        'judul_buku' => 'Buku Alumni Lulusan ' . $validated['tahun_lulus'],
                        'is_published' => true,
                        'tanggal_rilis' => Carbon::now()->toDateString(),
                    ]
                );
                $bukuAlumniId = $buku->id;
            }

            $studyTracer = StudyTracer::create([
                'siswa_id' => $validated['siswa_id'],
                'perguruan_tinggi_id' => $pt->id,
                'buku_alumni_id' => $bukuAlumniId,
                'strata_pendidikan' => $validated['strata_pendidikan'] ?? 'S1',
                'program_studi' => $validated['prodi_diterima'],
                'jalur_masuk' => $validated['jalur_masuk'],
                'sub_jalur_mandiri' => $validated['sub_jalur_mandiri'] ?? null,
                'pilihan_ke' => $validated['pilihan_ke'] ?? 1,
                'status_penerimaan' => $validated['status_penerimaan'] ?? 'diterima',
                'nilai_rapor_pendaftaran' => $validated['nilai_rapor_pendaftaran'] ?? null,
                'skor_utbk' => $validated['skor_utbk'] ?? null,
                'kontak_wa_terbaru' => $validated['kontak_wa_terbaru'] ?? null,
                'is_pelamar_kipk' => $validated['is_pelamar_kipk'] ?? false,
                'izinkan_tampil_di_buku' => $validated['izinkan_tampil_di_buku'] ?? true,
                'tanggal_pengisian' => Carbon::now()->toDateString(),
            ]);

            if (! empty($validated['tahun_lulus'])) {
                AlumniProgress::updateOrCreate(
                    ['siswa_id' => $validated['siswa_id']],
                    [
                        'study_tracer_id' => $studyTracer->id,
                        'tahun_lulus' => $validated['tahun_lulus'],
                        'status_pendidikan' => 'kuliah',
                        'tanggal_update' => Carbon::now()->toDateString(),
                    ]
                );
            }

            return $studyTracer;
        });

        $alumni->load(['siswa', 'perguruanTinggi', 'bukuAlumni']);

        return response()->json([
            'status' => 'success',
            'message' => 'Data alumni berhasil ditambahkan.',
            'data' => $alumni,
        ], 201);
    }

    public function show(string|int $id): JsonResponse
    {
        $alumni = StudyTracer::with(['siswa', 'perguruanTinggi', 'bukuAlumni'])->find($id);

        if (! $alumni) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data alumni tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data detail alumni berhasil diambil.',
            'data' => $alumni,
        ], 200);
    }

    public function update(Request $request, string|int $id): JsonResponse
    {
        $alumni = StudyTracer::find($id);

        if (! $alumni) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data alumni tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'ptn_diterima' => ['sometimes', 'string', 'max:255'],
            'prodi_diterima' => ['sometimes', 'string', 'max:255'],
            'strata_pendidikan' => ['nullable', 'string', 'max:50'],
            'jalur_masuk' => ['sometimes', 'string', 'max:100'],
            'sub_jalur_mandiri' => ['nullable', 'string', 'max:100'],
            'tahun_lulus' => ['nullable', 'string', 'max:10'],
            'pilihan_ke' => ['nullable', 'integer'],
            'status_penerimaan' => ['nullable', Rule::in(['menunggu', 'diterima', 'tidak_diterima'])],
            'nilai_rapor_pendaftaran' => ['nullable', 'numeric'],
            'skor_utbk' => ['nullable', 'numeric'],
            'kontak_wa_terbaru' => ['nullable', 'string', 'max:50'],
            'is_pelamar_kipk' => ['nullable', 'boolean'],
            'izinkan_tampil_di_buku' => ['nullable', 'boolean'],
        ]);

        if (isset($validated['ptn_diterima'])) {
            $pt = PerguruanTinggi::firstOrCreate(['nama_pt' => $validated['ptn_diterima']]);
            $alumni->perguruan_tinggi_id = $pt->id;
        }

        if (isset($validated['prodi_diterima'])) {
            $alumni->program_studi = $validated['prodi_diterima'];
        }

        if (isset($validated['tahun_lulus'])) {
            $buku = BukuAlumni::firstOrCreate(
                ['tahun_lulusan' => $validated['tahun_lulus']],
                [
                    'judul_buku' => 'Buku Alumni Lulusan ' . $validated['tahun_lulus'],
                    'is_published' => true,
                    'tanggal_rilis' => Carbon::now()->toDateString(),
                ]
            );
            $alumni->buku_alumni_id = $buku->id;

            AlumniProgress::updateOrCreate(
                ['siswa_id' => $alumni->siswa_id],
                [
                    'study_tracer_id' => $alumni->id,
                    'tahun_lulus' => $validated['tahun_lulus'],
                    'status_pendidikan' => 'kuliah',
                    'tanggal_update' => Carbon::now()->toDateString(),
                ]
            );
        }

        $fillableDirect = [
            'strata_pendidikan',
            'jalur_masuk',
            'sub_jalur_mandiri',
            'pilihan_ke',
            'status_penerimaan',
            'nilai_rapor_pendaftaran',
            'skor_utbk',
            'kontak_wa_terbaru',
            'is_pelamar_kipk',
            'izinkan_tampil_di_buku',
        ];

        foreach ($fillableDirect as $field) {
            if (array_key_exists($field, $validated)) {
                $alumni->{$field} = $validated[$field];
            }
        }

        $alumni->save();
        $alumni->load(['siswa', 'perguruanTinggi', 'bukuAlumni']);

        return response()->json([
            'status' => 'success',
            'message' => 'Data alumni berhasil diperbarui.',
            'data' => $alumni,
        ], 200);
    }

    public function destroy(string|int $id): JsonResponse
    {
        $alumni = StudyTracer::find($id);

        if (! $alumni) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data alumni tidak ditemukan.',
            ], 404);
        }

        $alumni->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Data alumni berhasil dihapus.',
        ], 200);
    }
}
