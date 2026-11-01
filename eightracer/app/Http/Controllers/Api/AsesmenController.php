<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AsesmenKarakteristik;
use App\Models\AsesmenMultipleIntelligence;
use App\Models\AsesmenRiasec;
use App\Models\Siswa;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AsesmenController extends Controller
{
    public function getAsesmen(Request $request): JsonResponse
    {
        $user = $request->user();

        $siswaId = $request->query('siswa_id');

        if ($siswaId) {
            $siswa = Siswa::with([
                'asesmenRiasec',
                'asesmenMultipleIntelligence',
                'asesmenKarakteristik',
            ])->find($siswaId);
        } else {
            $siswa = Siswa::with([
                'asesmenRiasec',
                'asesmenMultipleIntelligence',
                'asesmenKarakteristik',
            ])->where('user_id', $user->id)->first();
        }

        if (! $siswa) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data siswa tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data asesmen siswa berhasil diambil.',
            'data' => [
                'siswa' => [
                    'id' => $siswa->id,
                    'nama_lengkap' => $siswa->nama_lengkap,
                    'nisn' => $siswa->nisn,
                ],
                'karakteristik' => $siswa->asesmenKarakteristik,
                'riasec' => $siswa->asesmenRiasec,
                'multiple_intelligence' => $siswa->asesmenMultipleIntelligence,
            ],
        ], 200);
    }

    public function storeOrUpdateAsesmen(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'siswa_id' => ['nullable', 'exists:siswa,id'],
            'tipe_kepribadian' => ['nullable', 'string', 'max:255'],
            'kecerdasan_majemuk_dominan' => ['nullable', 'string', 'max:255'],
            'kode_riasec' => ['nullable', 'string', 'max:10'],
            'tanggal_asesmen' => ['nullable', 'date'],
            'realistic_score' => ['nullable', 'numeric', 'between:0,100'],
            'investigative_score' => ['nullable', 'numeric', 'between:0,100'],
            'artistic_score' => ['nullable', 'numeric', 'between:0,100'],
            'social_score' => ['nullable', 'numeric', 'between:0,100'],
            'enterprising_score' => ['nullable', 'numeric', 'between:0,100'],
            'conventional_score' => ['nullable', 'numeric', 'between:0,100'],
            'multiple_intelligence' => ['nullable', 'array'],
            'multiple_intelligence.*.jenis_kecerdasan' => ['required_with:multiple_intelligence', 'string', 'max:255'],
            'multiple_intelligence.*.skor' => ['required_with:multiple_intelligence', 'numeric', 'between:0,100'],
        ]);

        $user = $request->user();

        if (! empty($validated['siswa_id'])) {
            $siswa = Siswa::find($validated['siswa_id']);
        } else {
            $siswa = Siswa::where('user_id', $user->id)->first();
        }

        if (! $siswa) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data siswa tidak ditemukan.',
            ], 404);
        }

        $tanggalAsesmen = ! empty($validated['tanggal_asesmen'])
            ? Carbon::parse($validated['tanggal_asesmen'])
            : Carbon::now();

        DB::transaction(function () use ($siswa, $validated, $tanggalAsesmen) {
            AsesmenKarakteristik::updateOrCreate(
                [
                    'siswa_id' => $siswa->id,
                ],
                [
                    'tipe_kepribadian' => $validated['tipe_kepribadian'] ?? null,
                    'kecerdasan_majemuk_dominan' => $validated['kecerdasan_majemuk_dominan'] ?? null,
                    'kode_riasec' => $validated['kode_riasec'] ?? null,
                    'tanggal_asesmen' => $tanggalAsesmen,
                ]
            );

            $hasRiasecScore = isset($validated['realistic_score'])
                || isset($validated['investigative_score'])
                || isset($validated['artistic_score'])
                || isset($validated['social_score'])
                || isset($validated['enterprising_score'])
                || isset($validated['conventional_score']);

            if ($hasRiasecScore || ! empty($validated['kode_riasec'])) {
                AsesmenRiasec::updateOrCreate(
                    [
                        'siswa_id' => $siswa->id,
                    ],
                    [
                        'realistic_score' => $validated['realistic_score'] ?? 0,
                        'investigative_score' => $validated['investigative_score'] ?? 0,
                        'artistic_score' => $validated['artistic_score'] ?? 0,
                        'social_score' => $validated['social_score'] ?? 0,
                        'enterprising_score' => $validated['enterprising_score'] ?? 0,
                        'conventional_score' => $validated['conventional_score'] ?? 0,
                        'kode_hasil' => $validated['kode_riasec'] ?? null,
                        'tanggal_asesmen' => $tanggalAsesmen->toDateString(),
                    ]
                );
            }

            if (! empty($validated['multiple_intelligence'])) {
                foreach ($validated['multiple_intelligence'] as $item) {
                    AsesmenMultipleIntelligence::updateOrCreate(
                        [
                            'siswa_id' => $siswa->id,
                            'jenis_kecerdasan' => $item['jenis_kecerdasan'],
                        ],
                        [
                            'skor' => $item['skor'],
                            'tanggal_asesmen' => $tanggalAsesmen->toDateString(),
                        ]
                    );
                }
            }
        });

        $siswa->load([
            'asesmenKarakteristik',
            'asesmenRiasec',
            'asesmenMultipleIntelligence',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Data asesmen berhasil disimpan.',
            'data' => [
                'siswa' => [
                    'id' => $siswa->id,
                    'nama_lengkap' => $siswa->nama_lengkap,
                    'nisn' => $siswa->nisn,
                ],
                'karakteristik' => $siswa->asesmenKarakteristik,
                'riasec' => $siswa->asesmenRiasec,
                'multiple_intelligence' => $siswa->asesmenMultipleIntelligence,
            ],
        ], 200);
    }
}
