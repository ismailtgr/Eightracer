<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CatatanKarakter;
use App\Models\NilaiRapor;
use App\Models\PrestasiMurid;
use App\Models\RekapPresensi;
use App\Models\RiwayatOrganisasi;
use App\Models\Siswa;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AkademikController extends Controller
{
    public function getAkademik(Request $request): JsonResponse
    {
        $user = $request->user();

        // Data ownership: selalu dari akun yang login, abaikan siswa_id dari client.
        $siswa = Siswa::with([
            'nilaiRapor.mataPelajaran',
            'prestasiMurid',
            'rekapPresensi',
            'catatanKarakter',
            'riwayatOrganisasi',
        ])->where('user_id', $user->id)->first();

        if (! $siswa) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data siswa tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data akademik siswa berhasil diambil.',
            'data' => [
                'siswa' => [
                    'id' => $siswa->id,
                    'nama_lengkap' => $siswa->nama_lengkap,
                    'nisn' => $siswa->nisn,
                    'nilai_tka' => $siswa->nilai_tka,
                ],
                'nilai_rapor' => $siswa->nilaiRapor,
                'prestasi' => $siswa->prestasiMurid,
                'presensi' => $siswa->rekapPresensi,
                'catatan_karakter' => $siswa->catatanKarakter,
                'organisasi' => $siswa->riwayatOrganisasi,
            ],
        ], 200);
    }

    public function storeOrUpdateNilaiRapor(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nilai_tka' => ['nullable', 'numeric', 'between:0,100'],
            'nilai_rapor' => ['required', 'array'],
            'nilai_rapor.*.mata_pelajaran_id' => ['required', 'exists:mata_pelajaran,id'],
            'nilai_rapor.*.semester' => ['required', 'integer', 'between:1,6'],
            'nilai_rapor.*.nilai_akhir' => ['required', 'numeric', 'between:0,100'],
        ]);

        $user = $request->user();

        $siswa = Siswa::where('user_id', $user->id)->first();

        if (! $siswa) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data siswa tidak ditemukan.',
            ], 404);
        }

        DB::transaction(function () use ($siswa, $validated) {
            if (isset($validated['nilai_tka'])) {
                $siswa->update(['nilai_tka' => $validated['nilai_tka']]);
            }

            foreach ($validated['nilai_rapor'] as $item) {
                NilaiRapor::updateOrCreate(
                    [
                        'siswa_id' => $siswa->id,
                        'mata_pelajaran_id' => $item['mata_pelajaran_id'],
                        'semester' => $item['semester'],
                    ],
                    [
                        'nilai_akhir' => $item['nilai_akhir'],
                    ]
                );
            }
        });

        $siswa->load('nilaiRapor.mataPelajaran');

        return response()->json([
            'status' => 'success',
            'message' => 'Data nilai rapor berhasil disimpan.',
            'data' => [
                'siswa_id' => $siswa->id,
                'nilai_tka' => $siswa->nilai_tka,
                'nilai_rapor' => $siswa->nilaiRapor,
            ],
        ], 200);
    }

    public function storePrestasi(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'jenis_prestasi' => ['required', 'string', 'max:100'],
            'nama_kompetisi' => ['required', 'string', 'max:255'],
            'bidang' => ['nullable', 'string', 'max:100'],
            'tingkat' => ['required', 'string', 'max:100'],
            'peringkat' => ['required', 'string', 'max:100'],
            'bobot_persentase' => ['nullable', 'numeric', 'between:0,100'],
            'status_verifikasi' => ['nullable', Rule::in(['menunggu', 'diverifikasi', 'ditolak'])],
            'penyelenggara' => ['nullable', 'string', 'max:255'],
            'tahun' => ['required', 'integer'],
            'file_bukti_url' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();

        $siswa = Siswa::where('user_id', $user->id)->first();

        if (! $siswa) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data siswa tidak ditemukan.',
            ], 404);
        }

        $validated['siswa_id'] = $siswa->id;
        $validated['status_verifikasi'] = $validated['status_verifikasi'] ?? 'menunggu';

        $prestasi = PrestasiMurid::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Data prestasi siswa berhasil disimpan.',
            'data' => $prestasi,
        ], 201);
    }

    public function storePresensiDanKarakter(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'presensi' => ['nullable', 'array'],
            'presensi.*.semester' => ['required_with:presensi', 'integer', 'between:1,6'],
            'presensi.*.jumlah_hadir' => ['required_with:presensi', 'integer', 'min:0'],
            'presensi.*.jumlah_sakit' => ['nullable', 'integer', 'min:0'],
            'presensi.*.jumlah_izin' => ['nullable', 'integer', 'min:0'],
            'presensi.*.jumlah_alpa' => ['nullable', 'integer', 'min:0'],
            'presensi.*.persentase_kehadiran' => ['nullable', 'numeric', 'between:0,100'],
            'catatan_karakter' => ['nullable', 'array'],
            'catatan_karakter.*.semester' => ['required_with:catatan_karakter', 'integer', 'between:1,6'],
            'catatan_karakter.*.jenis_catatan' => ['nullable', 'string', 'max:100'],
            'catatan_karakter.*.poin_disiplin' => ['nullable', 'integer'],
            'catatan_karakter.*.deskripsi' => ['required_with:catatan_karakter', 'string'],
            'catatan_karakter.*.tanggal_pencatatan' => ['nullable', 'date'],
            'organisasi' => ['nullable', 'array'],
            'organisasi.*.jenis_organisasi' => ['nullable', 'string', 'max:100'],
            'organisasi.*.nama_organisasi' => ['required_with:organisasi', 'string', 'max:255'],
            'organisasi.*.jabatan' => ['required_with:organisasi', 'string', 'max:100'],
            'organisasi.*.bobot_jabatan' => ['nullable', 'numeric'],
            'organisasi.*.periode_jabatan' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $request->user();

        $siswa = Siswa::where('user_id', $user->id)->first();

        if (! $siswa) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data siswa tidak ditemukan.',
            ], 404);
        }

        DB::transaction(function () use ($siswa, $validated) {
            if (! empty($validated['presensi'])) {
                foreach ($validated['presensi'] as $item) {
                    RekapPresensi::updateOrCreate(
                        [
                            'siswa_id' => $siswa->id,
                            'semester' => $item['semester'],
                        ],
                        [
                            'jumlah_hadir' => $item['jumlah_hadir'],
                            'jumlah_sakit' => $item['jumlah_sakit'] ?? 0,
                            'jumlah_izin' => $item['jumlah_izin'] ?? 0,
                            'jumlah_alpa' => $item['jumlah_alpa'] ?? 0,
                            'persentase_kehadiran' => $item['persentase_kehadiran'] ?? null,
                        ]
                    );
                }
            }

            if (! empty($validated['catatan_karakter'])) {
                foreach ($validated['catatan_karakter'] as $item) {
                    CatatanKarakter::create([
                        'siswa_id' => $siswa->id,
                        'semester' => $item['semester'],
                        'jenis_catatan' => $item['jenis_catatan'] ?? null,
                        'poin_disiplin' => $item['poin_disiplin'] ?? null,
                        'deskripsi' => $item['deskripsi'],
                        'tanggal_pencatatan' => ! empty($item['tanggal_pencatatan'])
                            ? Carbon::parse($item['tanggal_pencatatan'])
                            : Carbon::now(),
                    ]);
                }
            }

            if (! empty($validated['organisasi'])) {
                foreach ($validated['organisasi'] as $item) {
                    RiwayatOrganisasi::create([
                        'siswa_id' => $siswa->id,
                        'jenis_organisasi' => $item['jenis_organisasi'] ?? null,
                        'nama_organisasi' => $item['nama_organisasi'],
                        'jabatan' => $item['jabatan'],
                        'bobot_jabatan' => $item['bobot_jabatan'] ?? null,
                        'periode_jabatan' => $item['periode_jabatan'] ?? null,
                    ]);
                }
            }
        });

        $siswa->load([
            'rekapPresensi',
            'catatanKarakter',
            'riwayatOrganisasi',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Data presensi, karakter, dan organisasi berhasil disimpan.',
            'data' => [
                'presensi' => $siswa->rekapPresensi,
                'catatan_karakter' => $siswa->catatanKarakter,
                'organisasi' => $siswa->riwayatOrganisasi,
            ],
        ], 200);
    }
}
