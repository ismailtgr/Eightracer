<?php

namespace App\Services\School;

use App\Models\SchoolImportBatch;
use App\Models\Siswa;
use App\Models\StudyTracer;
use App\Models\BasisDataBeasiswa;
use App\Models\User;
use App\Services\Auth\SchoolAccess;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class BulkImportEngine
{
    public function __construct(private BulkSpreadsheetReader $reader, private BulkImportModules $modules) {}

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    public function preview(UploadedFile $file, array $options, User $actor): SchoolImportBatch
    {
        $scope = isset($options['scope_admission_id']) ? StudyTracer::where('status_penerimaan', 'diterima')->findOrFail($options['scope_admission_id']) : null;
        $fingerprint = hash('sha256', json_encode([$actor->id, hash_file('sha256', $file->getRealPath()), $options], JSON_THROW_ON_ERROR));
        $old = SchoolImportBatch::where('request_fingerprint', $fingerprint)->where(fn ($q) => $q->whereIn('status', ['completed', 'queued'])->orWhere(fn ($v) => $v->where('status', 'validated')->where('expires_at', '>', now())))->latest('created_at')->first();
        if ($old) {
            return $old;
        }
        $sheets = $this->reader->read($file, $options['delimiter'] ?? 'comma');
        $specs = $options['sheets'] ?? array_map(fn ($name) => ['sheet' => $name, 'module' => $options['module'], 'header_row' => 1], array_keys($sheets));
        $names = array_column($specs, 'sheet');
        $ignored = $options['ignore_sheets'] ?? [];
        if (count($names) !== count(array_unique($names)) || array_diff($names, array_keys($sheets)) || array_diff(array_keys($sheets), [...$names, ...$ignored]) || array_intersect($names, $ignored)) {
            $this->fail('sheets', 'Petakan setiap tab atau sebutkan tab yang diabaikan secara eksplisit.');
        }
        $plan = $errors = $seen = [];
        $sheetSummary = [];
        $total = 0;
        foreach ($specs as $spec) {
            $rows = $sheets[$spec['sheet']];
            $headerRow = $spec['header_row'] ?? 1;
            $header = array_filter($rows[$headerRow] ?? [], fn ($v) => trim((string) $v) !== '');
            $normalized = array_map(fn ($v) => mb_strtolower(trim((string) $v)), $header);
            if (! $header || count($normalized) !== count(array_unique($normalized))) {
                $this->fail('header_row', 'Header hilang atau berulang.');
            }
            if ($scope && $spec['module'] !== 'financial') $this->fail('module', 'Impor ini khusus progres alumni terpilih.');
            $fieldNames = array_values($normalized);
            if ($scope) {
                $aliases = ['ipk' => 'ipk_terkini', 'ukt' => 'nominal_ukt', 'ipi' => 'nominal_ipi', 'kipk' => 'penerima_kipk', 'beasiswa' => 'beasiswa_id'];
                $fieldNames = array_map(fn ($field) => $aliases[$field] ?? $field, $fieldNames);
                if (count($fieldNames) !== count(array_unique($fieldNames))) $this->fail('columns', 'Kolom sumber memetakan field yang sama.');
            }
            $columns = $spec['columns'] ?? array_combine($fieldNames, array_values($header));
            if (! $columns || array_diff(array_keys($columns), [...BulkImportModules::FIELDS[$spec['module']], 'external_id'])) {
                $this->fail('columns', 'Gunakan kolom canonical atau pemetaan kolom yang didukung.');
            }
            $indexes = [];
            foreach ($columns as $field => $heading) {
                $i = array_search(mb_strtolower(trim($heading)), $normalized, true);
                if ($i === false) {
                    $this->fail('columns', 'Kolom sumber tidak ditemukan pada header.');
                }
                $indexes[$field] = $i;
            }
            $sheetCount = 0;
            foreach ($rows as $number => $cells) {
                if ($number <= $headerRow || ! array_filter($cells, fn ($v) => trim((string) $v) !== '')) {
                    continue;
                }
                if (++$total > config('b4.import_rows')) {
                    $this->fail('file', 'Maksimal 2.000 baris data per batch.');
                }
                if ($scope && $total > 40) $this->fail('file', 'Maksimal 40 baris progres untuk satu alumni.');
                $sheetCount++;
                $raw = [];
                foreach ($indexes as $field => $index) {
                    $raw[$field] = trim((string) ($cells[$index] ?? ''));
                }
                if (($options['number_format'] ?? 'canonical') === 'id') {
                    foreach ($raw as $field => &$value) {
                        if (str_starts_with($field, 'nominal_') || str_ends_with($field, '_penghasilan')) {
                            $value = preg_replace('/^Rp\s*/i', '', $value);
                            if ($value !== '' && ! preg_match('/^(?:[0-9]{1,3}(?:\.[0-9]{3})+|[0-9]+)(?:,[0-9]{1,2})?$/D', $value)) {
                                $value = '__format_nominal_tidak_valid__';
                            }
                            $value = str_replace(['.', ','], ['', '.'], $value);
                        } elseif (str_contains($value, ',') && preg_match('/^[0-9]+,[0-9]{1,4}$/D', $value) && ! in_array($field, ['nisn', 'nis', 'nomor_penerima'], true)) {
                            $value = str_replace(',', '.', $value);
                        }
                    } unset($value);
                }
                try {
                    if ($scope) {
                        foreach (['siswa_id' => $scope->siswa_id, 'study_tracer_id' => $scope->id] as $field => $expected) {
                            if (isset($raw[$field]) && $raw[$field] !== '' && (string) $raw[$field] !== (string) $expected) $this->fail($field, 'Baris bukan milik alumni/penerimaan yang sedang dibuka.');
                            $raw[$field] = (string) $expected;
                        }
                        if (isset($raw['status_pendidikan'])) $raw['status_pendidikan'] = mb_strtolower($raw['status_pendidikan']);
                        if (! empty($raw['beasiswa_id']) && ! ctype_digit($raw['beasiswa_id'])) {
                            if ($raw['beasiswa_id'] === '-') {
                                unset($raw['beasiswa_id']);
                            } else {
                                $matches = BasisDataBeasiswa::where('nama_beasiswa', $raw['beasiswa_id'])->pluck('id');
                                if ($matches->count() !== 1) $this->fail('beasiswa_id', 'Nama beasiswa belum cocok dengan satu program katalog. Gunakan ID katalog yang benar.');
                                $raw['beasiswa_id'] = (string) $matches->first();
                            }
                        }
                    }
                    if (strtolower($file->getClientOriginalExtension()) === 'xlsx') {
                        foreach ($raw as $field => &$value) {
                            if (str_starts_with($field, 'tanggal_') && $value !== '' && is_numeric($value)) {
                                $serial = (float) $value;
                                if ($serial < 1 || $serial > 73050 || (! $this->reader->date1904 && (int) $serial === 60)) {
                                    $this->fail($field, 'Tanggal Excel tidak valid.');
                                }
                                $base = $this->reader->date1904 ? '1904-01-01' : ($serial < 60 ? '1899-12-31' : '1899-12-30');
                                $value = Carbon::parse($base)->addDays((int) $serial)->toDateString();
                            }
                        } unset($value);
                    }
                    $row = $this->modules->prepare($spec['module'], $raw, $options['mode']);
                    $this->modules->assertSourceLink($options['source'], $row);
                    $natural = $row['module'].':'.$row['natural_key'];
                    $external = $row['module'].':'.$row['key'];
                    if (isset($seen[$natural]) || isset($seen[$external])) {
                        $this->fail('duplikat', 'Identitas/record yang sama berulang dalam batch.');
                    }
                    $seen[$natural] = $seen[$external] = true;
                    $row['sheet'] = $spec['sheet'];
                    $row['row'] = $number;
                    $plan[] = $row;
                } catch (ValidationException $e) {
                    $errors[] = ['sheet' => $spec['sheet'], 'row' => $number, 'errors' => $e->errors()];
                } catch (HttpExceptionInterface $e) {
                    $errors[] = ['sheet' => $spec['sheet'], 'row' => $number, 'errors' => ['referensi' => ['Data referensi atau identitas belum cocok.']]];
                }
            }
            $sheetSummary[] = ['sheet' => $spec['sheet'], 'module' => $spec['module'], 'jumlah_baris' => $sheetCount];
        }
        if ($total === 0) {
            $this->fail('file', 'Tidak ada baris data untuk diproses.');
        }
        if (strlen(json_encode($plan, JSON_THROW_ON_ERROR)) > 5 * 1024 * 1024) {
            $this->fail('file', 'Pecah batch menjadi file lebih kecil.');
        }
        $digest = hash_hmac('sha256', json_encode($plan, JSON_THROW_ON_ERROR), config('app.key'));
        $id = (string) Str::uuid();

        return SchoolImportBatch::create(['id' => $id, 'created_by' => $actor->id, 'source' => $options['source'], 'mode' => $options['mode'],
            'status' => $errors ? 'invalid' : 'validated', 'fingerprint' => hash('sha256', $fingerprint.$id), 'request_fingerprint' => $fingerprint, 'commit_digest' => $digest, 'plan' => $errors ? [] : $plan,
            'summary' => ['total' => $total, 'valid' => count($plan), 'invalid' => count($errors), 'errors' => array_slice($errors, 0, 100), 'errors_truncated' => count($errors) > 100, 'sheets' => $sheetSummary, 'ignored_sheets' => $ignored],
            'expires_at' => now()->addHours(config('b4.import_expiry_hours'))]);
    }

    public function present(SchoolImportBatch $batch): array
    {
        return ['id' => $batch->id, 'status' => $batch->status, 'source' => $batch->source, 'mode' => $batch->mode, 'commit_digest' => $batch->commit_digest,
            'summary' => $batch->summary, 'receipt' => $batch->receipt, 'expires_at' => $batch->expires_at,
            'sample' => array_map(fn ($r) => ['sheet' => $r['sheet'], 'row' => $r['row'], 'module' => $r['module'], 'data' => $r['data']], array_slice($batch->plan ?? [], 0, 3))];
    }

    public function commit(string $id): void
    {
        try {
            DB::transaction(function () use ($id) {
                $batch = SchoolImportBatch::lockForUpdate()->findOrFail($id);
                if ($batch->status === 'completed') {
                    return;
                }
                abort_unless($batch->status === 'queued' && ! $batch->expires_at->isPast(), 409);
                $actor = User::findOrFail($batch->created_by);
                abort_unless(SchoolAccess::isStaff($actor), 403);
                $plan = $batch->plan;
                abort_unless(hash_equals($batch->commit_digest, hash_hmac('sha256', json_encode($plan, JSON_THROW_ON_ERROR), config('app.key'))), 409);
                $receipt = [];
                $validated = [];
                Siswa::whereIn('id', array_values(array_unique(array_filter(array_column($plan, 'student_id')))))->orderBy('id')->lockForUpdate()->get();
                foreach ($plan as $row) {
                    $fresh = $this->modules->prepare($row['module'], $row['raw'], $batch->mode);
                    $this->modules->assertSourceLink($batch->source, $fresh);
                    abort_unless(hash_equals($row['target_fingerprint'], $fresh['target_fingerprint']), 409, 'Sumber berubah setelah pratinjau.');
                    $validated[] = $fresh;
                }
                foreach ($plan as $index => $row) {
                    $fresh = $validated[$index];
                    $key = ['source' => $batch->source, 'module' => $row['module'], 'external_key' => $row['key']];
                    $old = DB::table('school_import_links')->where($key)->lockForUpdate()->first();
                    abort_if($old && $old->record_table !== $row['record_table'], 409);
                    $record = $this->modules->apply($fresh, $actor);
                    DB::table('school_import_links')->updateOrInsert($key, ['record_table' => $row['record_table'], 'record_id' => $record,
                        'payload_hash' => hash('sha256', json_encode($row['data'], JSON_THROW_ON_ERROR)), 'last_batch_id' => $batch->id, 'updated_at' => now(), 'created_at' => $old?->created_at ?? now()]);
                    $receipt[$row['module']] = ($receipt[$row['module']] ?? 0) + 1;
                }
                $batch->update(['status' => 'completed', 'receipt' => ['processed' => count($plan), 'per_module' => $receipt], 'completed_at' => now(), 'plan' => null]);
            });
        } catch (\Throwable $e) {
            // Keep the encrypted plan only until expiry; never store exception text or row values.
            SchoolImportBatch::whereKey($id)->where('status', 'queued')->update(['status' => 'failed', 'receipt' => json_encode(['processed' => 0, 'code' => $e instanceof ValidationException ? 'validation_changed' : 'stale_or_unavailable'])]);
        }
    }
}
