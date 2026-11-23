<?php

namespace App\Services\Beasiswa;

use App\Models\BasisDataBeasiswa;
use App\Services\School\Criteria;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Satu sumber aturan untuk simpan tunggal dan impor massal katalog beasiswa.
 * Impor massal bersifat all-or-nothing: semua baris divalidasi lebih dulu,
 * lalu disimpan dalam satu transaksi.
 */
class ScholarshipCatalog
{
    public const MAX_BULK_ROWS = 20;

    private const DUPLICATE_MESSAGE = 'Beasiswa dengan nama dan jenjang ini sudah ada. Perbarui atau aktifkan data yang sudah tercatat.';

    public function rules(array $input): array
    {
        $filled = static fn (string $key): bool => isset($input[$key]) && $input[$key] !== '' && trim((string) $input[$key]) !== '';

        return [
            'nama_beasiswa' => 'required|string|max:255', 'penyelenggara' => 'nullable|string|max:255',
            'deskripsi' => 'nullable|string|max:3000', 'jenjang' => 'required|in:SMA,Kuliah,Keduanya',
            'tanggal_buka' => 'nullable|date_format:Y-m-d',
            'tanggal_tutup' => ['nullable', 'date_format:Y-m-d', Rule::when($filled('tanggal_buka'), 'after_or_equal:tanggal_buka')],
            'link_pendaftaran' => 'nullable|url:http,https|max:500', 'is_active' => 'sometimes|boolean',
            'status_program' => 'prohibited', 'status_pendaftaran' => 'prohibited', 'official_policy' => 'prohibited',
            'durasi' => 'nullable|numeric|gt:0|max:100', 'satuan_durasi' => 'required_with:durasi|nullable|in:tahun,semester,bulan', 'periode_apply' => 'nullable|string|max:255',
            'program_eligibilitas_id' => 'nullable|integer|exists:program_eligibilitas,id',
            'kriteria' => 'required|array|min:1|max:30',
            'kriteria.*.nama_kriteria' => 'required|string|max:255',
            'kriteria.*.tipe_kriteria' => ['required', Rule::in(EligibilityEvaluator::FIELDS)],
            'kriteria.*.operator' => 'required|in:=,!=,>=,<=,>,<',
            'kriteria.*.nilai_kriteria' => 'required|string|max:255',
            'kriteria.*.bobot' => 'nullable|numeric|min:0.01|max:999.99',
        ];
    }

    public function create(array $input): BasisDataBeasiswa
    {
        $data = $this->validate($input);

        try {
            return DB::transaction(fn () => $this->persist($data));
        } catch (QueryException $exception) {
            $this->rethrowDuplicate($exception);
        }
    }

    public function update(array $input, BasisDataBeasiswa $beasiswa): BasisDataBeasiswa
    {
        $data = $this->validate($input, $beasiswa);

        try {
            return DB::transaction(fn () => $this->persist($data, $beasiswa));
        } catch (QueryException $exception) {
            $this->rethrowDuplicate($exception);
        }
    }

    /**
     * @return array<int, BasisDataBeasiswa>
     */
    public function createMany(array $rows): array
    {
        $count = count($rows);
        if ($count < 1 || $count > self::MAX_BULK_ROWS) {
            throw ValidationException::withMessages([
                'rows' => 'Impor harus berisi 1 sampai '.self::MAX_BULK_ROWS.' beasiswa.',
            ]);
        }

        $validated = [];
        $seen = [];
        foreach (array_values($rows) as $index => $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages(["rows.$index" => 'Data baris harus berupa objek beasiswa.']);
            }
            $data = $this->validate($row, null, "rows.$index.");
            $key = mb_strtolower(trim($data['nama_beasiswa'])).'|'.$data['jenjang'];
            if (isset($seen[$key])) {
                throw ValidationException::withMessages([
                    "rows.$index.nama_beasiswa" => 'Nama dan jenjang ini berulang dalam satu file. Satukan menjadi satu baris.',
                ]);
            }
            $seen[$key] = true;
            $validated[] = $data;
        }

        try {
            return DB::transaction(fn () => array_map(fn (array $data) => $this->persist($data), $validated));
        } catch (QueryException $exception) {
            $this->rethrowDuplicate($exception);
        }
    }

    public function validate(array $input, ?BasisDataBeasiswa $existing = null, string $prefix = ''): array
    {
        try {
            $data = Validator::make($input, $this->rules($input))->validate();
        } catch (ValidationException $exception) {
            throw $this->prefixed($exception, $prefix);
        }

        return $this->normalize($data, $existing, $prefix);
    }

    private function normalize(array $data, ?BasisDataBeasiswa $existing, string $prefix): array
    {
        $open = array_key_exists('tanggal_buka', $data) ? $data['tanggal_buka'] : $existing?->tanggal_buka?->format('Y-m-d');
        $close = array_key_exists('tanggal_tutup', $data) ? $data['tanggal_tutup'] : $existing?->tanggal_tutup?->format('Y-m-d');
        if ($open && $close && $close < $open) {
            throw ValidationException::withMessages([$prefix.'tanggal_tutup' => 'Tanggal tutup harus sama atau setelah tanggal buka.']);
        }

        try {
            $data['kriteria'] = Criteria::validate($data['kriteria']);
        } catch (ValidationException $exception) {
            throw $this->prefixed($exception, $prefix);
        }

        if (collect($data['kriteria'])->contains(fn ($criterion) => in_array($criterion['tipe_kriteria'], ['status_eligible', 'skor_prestasi', 'bantuan_sosial_sesuai'], true))
            && empty($data['program_eligibilitas_id'])) {
            throw ValidationException::withMessages([$prefix.'program_eligibilitas_id' => 'Pilih program eligibilitas untuk kriteria status.']);
        }

        foreach ($data['kriteria'] as $index => $criterion) {
            if ($criterion['tipe_kriteria'] !== 'status_eligible' && ! is_numeric($criterion['nilai_kriteria'])) {
                throw ValidationException::withMessages([$prefix."kriteria.$index.nilai_kriteria" => 'Nilai syarat harus berupa angka.']);
            }
            if ($criterion['tipe_kriteria'] === 'status_eligible' && ! in_array($criterion['operator'], ['=', '!='], true)) {
                throw ValidationException::withMessages([$prefix."kriteria.$index.operator" => 'Status hanya mendukung = dan !=.']);
            }
        }

        $this->assertUnique($data, $existing, $prefix);

        return $data;
    }

    private function assertUnique(array $data, ?BasisDataBeasiswa $existing, string $prefix): void
    {
        $name = mb_strtolower(trim($data['nama_beasiswa']));
        $duplicate = BasisDataBeasiswa::whereRaw('LOWER(TRIM(nama_beasiswa)) = ?', [$name])
            ->where('jenjang', $data['jenjang'])
            ->when($existing?->exists, fn ($query) => $query->where('id', '!=', $existing->id))
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages([$prefix.'nama_beasiswa' => self::DUPLICATE_MESSAGE]);
        }
    }

    private function persist(array $data, ?BasisDataBeasiswa $existing = null): BasisDataBeasiswa
    {
        $beasiswa = $existing ?? new BasisDataBeasiswa;
        $criteria = $data['kriteria'];
        unset($data['kriteria']);
        if ($beasiswa->exists && $beasiswa->official_policy) {
            $policy = $beasiswa->official_policy;
            $policy['review_required'] = true;
            $beasiswa->official_policy = $policy;
        }
        $beasiswa->fill($data)->save();
        $beasiswa->kriteriaKelayakanBeasiswa()->delete();
        $beasiswa->kriteriaKelayakanBeasiswa()->createMany($criteria);

        return $beasiswa->load('kriteriaKelayakanBeasiswa');
    }

    private function prefixed(ValidationException $exception, string $prefix): ValidationException
    {
        if ($prefix === '') {
            return $exception;
        }
        $errors = [];
        foreach ($exception->errors() as $field => $messages) {
            $errors[$prefix.$field] = $messages;
        }

        return ValidationException::withMessages($errors);
    }

    private function rethrowDuplicate(QueryException $exception): never
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $message = mb_strtolower($exception->getMessage());
        if (in_array($sqlState, ['23000', '23505'], true)
            && (str_contains($message, 'basis_data_beasiswa') || str_contains($message, 'nama_jenjang'))) {
            throw ValidationException::withMessages(['nama_beasiswa' => self::DUPLICATE_MESSAGE]);
        }
        throw $exception;
    }
}
