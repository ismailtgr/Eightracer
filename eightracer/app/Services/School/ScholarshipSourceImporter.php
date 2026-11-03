<?php

namespace App\Services\School;

use App\Models\SchoolScholarshipObservation;
use App\Models\Siswa;
use App\Models\StudyTracer;
use App\Support\PlainText;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ScholarshipSourceImporter
{
    public const SOURCES = [
        '1-wq5eGYqf91L_1nOOlBRo3KVswRK4qSI' => 2024,
        '1CPKUio-wXx0jLTn8Nj4ibsM3kOsyNC10' => 2025,
        '1i3igBq5PbH8TPk60Pz75M2Qfz8MrVYsA' => 2026,
    ];

    public static function classification(string $column, string $value): array
    {
        $v = mb_strtoupper(trim($value));
        if (in_array($v, ['-', '--'], true)) {
            return ['jenis_keterangan' => 'tanpa_keterangan', 'indikator_kip' => null];
        }
        if (in_array($column, ['KIP', 'KIPK'], true) && in_array($v, ['YA', 'TIDAK'], true)) {
            return ['jenis_keterangan' => 'indikator_kip', 'indikator_kip' => $v === 'YA'];
        }
        // A mark/code is preserved verbatim, without asserting application or award status.
        if ($v === 'V' || $v === 'TPKS') {
            return ['jenis_keterangan' => 'kode_sumber', 'indikator_kip' => null];
        }
        if (in_array($v, ['KIP', 'KIPK', 'KIP-K', 'KIP K'], true)) {
            return ['jenis_keterangan' => 'referensi_kip', 'indikator_kip' => null];
        }
        if ($v === 'BEBAS IPI') {
            return ['jenis_keterangan' => 'pembebasan_biaya', 'indikator_kip' => null];
        }
        if (str_contains($v, 'BEASISWA') || $v === 'PRESTASI BASKET') {
            return ['jenis_keterangan' => 'referensi_beasiswa', 'indikator_kip' => null];
        }

        return ['jenis_keterangan' => 'keterangan_belum_jelas', 'indikator_kip' => null];
    }

    public function import(array $rows, array $verifiedHashes): array
    {
        Validator::make(['rows' => $rows], ['rows' => 'required|array|min:1|max:5000'])->validate();

        return DB::transaction(function () use ($rows, $verifiedHashes) {
            $result = ['created' => 0, 'existing' => 0];
            foreach ($rows as $input) {
                $d = Validator::make($input, [
                    'source_file_id' => 'required|string|max:64', 'source_sha256' => 'required|regex:/^[a-f0-9]{64}$/',
                    'source_sheet' => 'required|string|max:100', 'source_row' => 'required|integer|min:1',
                    'source_column' => 'required|regex:/^[A-Z]{1,4}$/', 'tahun_lulus' => 'required|integer|between:2024,2026',
                    'siswa_id' => 'nullable|integer|exists:siswa,id', 'study_tracer_id' => 'nullable|integer|exists:study_tracer,id',
                    'nama_kolom' => 'required|in:KIP,KIPK,BEASISWA', 'nilai_sumber' => 'required|string|max:500',
                    'kampus_sumber' => 'nullable|string|max:255', 'prodi_sumber' => 'nullable|string|max:255',
                ])->validate();
                if ((self::SOURCES[$d['source_file_id']] ?? null) !== $d['tahun_lulus']
                    || ($verifiedHashes[$d['source_file_id']] ?? null) !== $d['source_sha256']) {
                    throw ValidationException::withMessages(['source' => 'Identitas atau checksum sumber tidak cocok.']);
                }
                foreach (['source_sheet', 'nilai_sumber', 'kampus_sumber', 'prodi_sumber'] as $field) {
                    if (isset($d[$field]) && ! PlainText::safe($d[$field])) {
                        throw ValidationException::withMessages([$field => 'Teks sumber tidak valid.']);
                    }
                }
                $student = isset($d['siswa_id']) ? Siswa::findOrFail($d['siswa_id']) : null;
                if ($student && (int) $student->tahun_lulus !== $d['tahun_lulus']) {
                    throw ValidationException::withMessages(['siswa_id' => 'Angkatan siswa tidak sesuai sumber.']);
                }
                if (isset($d['study_tracer_id']) && (! $student || (int) StudyTracer::findOrFail($d['study_tracer_id'])->siswa_id !== $student->id)) {
                    throw ValidationException::withMessages(['study_tracer_id' => 'Penerimaan bukan milik siswa sumber.']);
                }
                $key = hash('sha256', json_encode([$d['source_file_id'], $d['source_sha256'], $d['source_sheet'], $d['source_row'], $d['source_column']], JSON_THROW_ON_ERROR));
                $values = $d + self::classification($d['nama_kolom'], $d['nilai_sumber']);
                $old = SchoolScholarshipObservation::where('source_key', $key)->first();
                if ($old) {
                    foreach ($values as $field => $value) {
                        if ($old->$field !== $value && (string) $old->$field !== (string) $value) {
                            throw ValidationException::withMessages(['source' => 'Pemetaan sumber yang sama berubah; perlu rekonsiliasi.']);
                        }
                    }
                    $result['existing']++;
                } else {
                    SchoolScholarshipObservation::create(['source_key' => $key] + $values);
                    $result['created']++;
                }
            }

            return $result;
        });
    }
}
