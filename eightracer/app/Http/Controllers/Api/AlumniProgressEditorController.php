<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AlumniProgress;
use App\Models\SchoolImportBatch;
use App\Models\StudyTracer;
use App\Services\School\AlumniService;
use App\Services\School\BulkImportEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AlumniProgressEditorController extends Controller
{
    private function admission(int $id): StudyTracer
    {
        return StudyTracer::where('status_penerimaan', 'diterima')->with('siswa')->findOrFail($id);
    }

    public function save(Request $request, int $id, ?int $progress = null)
    {
        $admission = $this->admission($id);
        $request->validate(['siswa_id' => 'prohibited', 'study_tracer_id' => 'prohibited']);
        $previous = $progress ? AlumniProgress::where('study_tracer_id', $id)->where('is_current', true)->findOrFail($progress) : null;
        $request->merge(['study_tracer_id' => $admission->id]);
        $row = app(AlumniService::class)->saveProgress($request, $admission->siswa, $previous);

        return response()->json(['data' => ['id' => $row->id, 'revision' => $row->revision]], 201);
    }

    public function archive(Request $request, int $id, int $progress)
    {
        $this->admission($id);
        DB::transaction(function () use ($id, $progress) {
            $row = AlumniProgress::where('study_tracer_id', $id)->lockForUpdate()->findOrFail($progress);
            $row->update(['is_current' => false, 'is_published' => false, 'publish_financials' => false]);
        });

        return response()->json(['message' => 'Riwayat diarsipkan; jejak perubahan tetap tersimpan.']);
    }

    public function template(int $id)
    {
        $this->admission($id);
        $columns = ['periode', 'semester', 'status_pendidikan', 'tanggal_update', 'ipk', 'kipk', 'ukt', 'ipi', 'beasiswa', 'status_beasiswa', 'nominal_beasiswa'];
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, $columns, ',', '"', '');
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return response("\xEF\xBB\xBF".$csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="template-progres-alumni-'.$id.'.csv"']);
    }

    public function preview(Request $request, int $id, BulkImportEngine $engine)
    {
        $this->admission($id);
        $request->validate(['file' => 'required|file|extensions:csv,xlsx|max:5120', 'number_format' => 'sometimes|in:canonical,id']);
        $batch = $engine->preview($request->file('file'), [
            'module' => 'financial', 'mode' => 'upsert', 'source' => 'b4-progress-'.$id,
            'scope_admission_id' => $id, 'number_format' => $request->input('number_format', 'canonical'),
        ], $request->user());

        $result = $engine->present($batch);
        $result['sample'] = array_map(fn ($row) => ['sheet' => $row['sheet'], 'row' => $row['row'], 'operation' => $row['existing_id'] ? 'koreksi' : 'tambah', 'data' => $row['data']], $batch->plan ?? []);

        return response()->json(['data' => $result], 201);
    }

    private function owned(Request $request, int $id, string $batch): SchoolImportBatch
    {
        $this->admission($id);

        return SchoolImportBatch::where('created_by', $request->user()->id)->where('source', 'b4-progress-'.$id)->findOrFail($batch);
    }

    public function commit(Request $request, int $id, string $batch, BulkImportEngine $engine)
    {
        $data = $request->validate(['commit_digest' => 'required|string|size:64']);
        $this->owned($request, $id, $batch);
        $row = DB::transaction(function () use ($request, $id, $batch, $data) {
            $row = $this->owned($request, $id, $batch);
            $row = SchoolImportBatch::whereKey($row->id)->lockForUpdate()->firstOrFail();
            abort_unless(hash_equals($row->commit_digest, $data['commit_digest']), 409);
            if ($row->status === 'completed') return $row;
            abort_unless(in_array($row->status, ['validated', 'queued'], true) && $row->expires_at->isFuture(), 409);
            $row->update(['status' => 'queued']);

            return $row;
        });
        $engine->commit($row->id);
        $row->refresh();

        return response()->json(['data' => $engine->present($row)], $row->status === 'completed' ? 200 : 409);
    }
}
