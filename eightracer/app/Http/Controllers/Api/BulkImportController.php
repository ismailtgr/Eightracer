<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\CommitSchoolImport;
use App\Models\SchoolImportBatch;
use App\Services\School\BulkImportEngine;
use App\Services\School\BulkImportModules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BulkImportController extends Controller
{
    private function owned(Request $r, string $batch): SchoolImportBatch
    {
        return SchoolImportBatch::where('created_by', $r->user()->id)->findOrFail($batch);
    }

    public function template(string $module)
    {
        abort_unless(isset(BulkImportModules::FIELDS[$module]), 404);
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, [...BulkImportModules::FIELDS[$module], 'external_id'], ',', '"', '');
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return response("\xEF\xBB\xBF".$csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="template-'.$module.'.csv"']);
    }

    public function preview(Request $r, BulkImportEngine $engine)
    {
        foreach (['sheets', 'ignore_sheets'] as $field) {
            if (is_string($r->input($field))) {
                try {
                    $r->merge([$field => json_decode($r->input($field), true, 32, JSON_THROW_ON_ERROR)]);
                } catch (\Throwable) {
                    throw ValidationException::withMessages([$field => 'Gunakan JSON yang valid.']);
                }
            }
        }
        $modules = implode(',', array_keys(BulkImportModules::FIELDS));
        $d = $r->validate(['file' => 'required|file|extensions:csv,xlsx|max:5120', 'source' => 'required|string|max:100|regex:/^[A-Za-z0-9_.:-]+$/D', 'mode' => 'sometimes|in:insert_only,upsert',
            'module' => 'required_without:sheets|nullable|in:'.$modules, 'sheets' => 'nullable|array|min:1|max:50', 'sheets.*.sheet' => 'required|string|max:100', 'sheets.*.module' => 'required|in:'.$modules,
            'sheets.*.header_row' => 'sometimes|integer|between:1,10', 'sheets.*.columns' => 'sometimes|array|min:1|max:52', 'sheets.*.columns.*' => 'required|string|max:100',
            'ignore_sheets' => 'sometimes|array|max:50', 'ignore_sheets.*' => 'required|string|max:100', 'delimiter' => 'sometimes|in:comma,semicolon,tab', 'number_format' => 'sometimes|in:canonical,id']);
        unset($d['file']);
        $d['mode'] ??= 'insert_only';

        return response()->json(['data' => $engine->present($engine->preview($r->file('file'), $d, $r->user()))], 201);
    }

    public function show(Request $r, string $batch, BulkImportEngine $engine)
    {
        return response()->json(['data' => $engine->present($this->owned($r, $batch))]);
    }

    public function commit(Request $r, string $batch, BulkImportEngine $engine)
    {
        $d = $r->validate(['commit_digest' => 'required|string|size:64']);
        $row = DB::transaction(function () use ($r, $batch, $d) {
            $row = SchoolImportBatch::where('created_by', $r->user()->id)->lockForUpdate()->findOrFail($batch);
            abort_unless(hash_equals($row->commit_digest, $d['commit_digest']), 409);
            if (in_array($row->status, ['completed', 'queued'], true)) {
                return $row;
            }
            abort_unless($row->status === 'validated' && ! $row->expires_at->isPast(), 409);
            $row->update(['status' => 'queued']);
            CommitSchoolImport::dispatch($row->id)->afterCommit();

            return $row;
        });

        return response()->json(['data' => $engine->present($row->fresh())], 202);
    }
}
