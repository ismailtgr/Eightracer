<?php
$p = \App\Models\AlumniProgress::where('is_current', true)->with('siswa','studyTracer')->first();
echo "catatan: " . ($p->siswa->catatan ?? 'NULL') . PHP_EOL;
echo "study_tracer_id: " . ($p->study_tracer_id ?? 'NULL') . PHP_EOL;
echo "status_penerimaan: " . ($p->studyTracer?->status_penerimaan ?? 'NULL') . PHP_EOL;
