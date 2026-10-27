<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AlumniProgress;
use App\Models\AlumniProgressBeasiswa;
use App\Models\Siswa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlumniProgressController extends Controller
{
    /**
     * Menampilkan daftar progres alumni (dengan pencarian dan filter).
     */
    public function index(Request $request): JsonResponse
    {
        $query = AlumniProgress::with(['siswa', 'studyTracer.perguruanTinggi', 'alumniProgressBeasiswa.beasiswa']);

        if ($request->filled('tahun_lulus')) {
            $query->where('tahun_lulus', $request->input('tahun_lulus'));
        }

        if ($request->filled('status_pendidikan')) {
            $query->where('status_pendidikan', $request->input('status_pendidikan'));
        }

        $progress = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar progress alumni berhasil diambil',
            'data'    => $progress,
        ]);
    }

    /**
     * Menampilkan riwayat progres kuliah & beasiswa seorang alumni.
     */
    public function showBySiswa(int $siswaId): JsonResponse
    {
        $siswa = Siswa::findOrFail($siswaId);

        $progress = AlumniProgress::with(['studyTracer.perguruanTinggi', 'alumniProgressBeasiswa.beasiswa'])
            ->where('siswa_id', $siswa->id)
            ->orderBy('tanggal_update', 'desc')
            ->get();

        return response()->json([
            'status'  => 'success',
            'message' => 'Riwayat progres alumni berhasil diambil',
            'siswa'   => [
                'id'           => $siswa->id,
                'nama_lengkap' => $siswa->nama_lengkap,
                'nisn'         => $siswa->nisn,
            ],
            'data'    => $progress,
        ]);
    }

    /**
     * Mencatat progres semester alumni (Status, IPK, UKT, dan Beasiswa).
     */
    public function store(Request $request, int $siswaId): JsonResponse
    {
        $siswa = Siswa::findOrFail($siswaId);

        $validated = $request->validate([
            'study_tracer_id'    => 'nullable|exists:study_tracer,id',
            'tahun_lulus'        => 'required|string|max:10',
            'status_pendidikan'  => 'nullable|string|max:50',
            'ipk_terkini'        => 'nullable|numeric|between:0,4.00',
            'tanggal_update'     => 'required|date',
            // Informasi UKT & Beasiswa (Domain 5 Fitur Unggulan B4)
            'beasiswa_id'        => 'nullable|exists:basis_data_beasiswa,id',
            'status_beasiswa'    => 'nullable|in:proses,diterima,ditolak',
            'nominal_beasiswa'   => 'nullable|numeric',
            'nominal_ukt'        => 'nullable|numeric',
            'periode'            => 'nullable|string|max:50',
        ]);

        $progress = AlumniProgress::create([
            'siswa_id'          => $siswa->id,
            'study_tracer_id'   => $validated['study_tracer_id'] ?? null,
            'tahun_lulus'       => $validated['tahun_lulus'],
            'status_pendidikan' => $validated['status_pendidikan'] ?? null,
            'ipk_terkini'       => $validated['ipk_terkini'] ?? null,
            'tanggal_update'    => $validated['tanggal_update'],
        ]);

        if (!empty($validated['beasiswa_id']) || !empty($validated['nominal_ukt'])) {
            AlumniProgressBeasiswa::create([
                'alumni_progress_id' => $progress->id,
                'beasiswa_id'        => $validated['beasiswa_id'] ?? 1,
                'status_beasiswa'    => $validated['status_beasiswa'] ?? 'proses',
                'nominal_beasiswa'   => $validated['nominal_beasiswa'] ?? null,
                'nominal_ukt'        => $validated['nominal_ukt'] ?? null,
                'periode'            => $validated['periode'] ?? null,
            ]);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Progres alumni dan UKT/beasiswa berhasil dicatat',
            'data'    => $progress->load('alumniProgressBeasiswa.beasiswa'),
        ], 201);
    }

    /**
     * Menampilkan linimasa (timeline) perjalanan studi dan beasiswa individu alumni.
     * (Sesuai Use Case UC-13 / UC-32: Lihat Timeline Individu Alumni)
     */
    public function timeline(int $siswaId): JsonResponse
    {
        $siswa = Siswa::with([
            'studyTracer.perguruanTinggi',
            'alumniProgress.alumniProgressBeasiswa.beasiswa',
            'prestasiMurid',
        ])->findOrFail($siswaId);

        $timeline = [];

        // 1. Data Masuk PTN / Study Tracer
        foreach ($siswa->studyTracer as $tracer) {
            $timeline[] = [
                'type'        => 'study_tracer',
                'title'       => 'Diterima di Perguruan Tinggi',
                'description' => ($tracer->perguruanTinggi->nama ?? 'PTN') . ' - Jalur ' . ($tracer->jalur_masuk ?? '-'),
                'date'        => $tracer->tahun_diterima ?? null,
            ];
        }

        // 2. Data Progres Semester (IPK, UKT, Beasiswa)
        foreach ($siswa->alumniProgress as $prog) {
            $beasiswaInfo = $prog->alumniProgressBeasiswa->map(function ($b) {
                return 'Beasiswa: ' . ($b->beasiswa->nama_beasiswa ?? '-') . ' (' . $b->status_beasiswa . ') - UKT: Rp ' . number_format($b->nominal_ukt ?? 0, 0, ',', '.');
            })->implode(', ');

            $timeline[] = [
                'type'        => 'progress_semester',
                'title'       => 'Pembaruan Progres Kuliah: ' . ($prog->status_pendidikan ?? 'Aktif'),
                'description' => 'IPK: ' . ($prog->ipk_terkini ?? '-') . ($beasiswaInfo ? ' | ' . $beasiswaInfo : ''),
                'date'        => $prog->tanggal_update,
            ];
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Linimasa alumni berhasil diambil',
            'siswa'   => [
                'id'           => $siswa->id,
                'nama_lengkap' => $siswa->nama_lengkap,
                'nisn'         => $siswa->nisn,
            ],
            'timeline' => $timeline,
        ]);
    }
}
