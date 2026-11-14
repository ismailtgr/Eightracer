<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  if(Schema::hasTable('student_scholarship_results')) {
   if(!Schema::hasColumns('student_scholarship_results',['siswa_id','source_fingerprint','requested_target','result','generated_at']))throw new RuntimeException('Result table structure does not match migration.');
   return;
  }
  Schema::create('student_scholarship_results', function(Blueprint $t) {
   $t->foreignId('siswa_id')->primary()->constrained('siswa')->restrictOnDelete();
   $t->char('source_fingerprint',64);
   $t->string('requested_target',10)->nullable();
   $t->jsonb('result');
   $t->timestampTz('generated_at');
  });
  // Only the Laravel server accesses this table; Supabase REST roles get no access.
  if(DB::getDriverName()==='pgsql') {
   DB::statement('ALTER TABLE student_scholarship_results ENABLE ROW LEVEL SECURITY');
   DB::statement('REVOKE ALL ON student_scholarship_results FROM PUBLIC');
   if(DB::selectOne("SELECT 1 FROM pg_roles WHERE rolname = 'eightracer_runtime'")) {
    DB::statement('GRANT SELECT, INSERT, UPDATE ON student_scholarship_results TO eightracer_runtime');
    DB::statement('CREATE POLICY student_scholarship_results_runtime ON student_scholarship_results FOR ALL TO eightracer_runtime USING (true) WITH CHECK (true)');
   }
   foreach(['anon','authenticated'] as $role) {
    if(DB::selectOne('SELECT 1 FROM pg_roles WHERE rolname = ?',[$role])) DB::statement('REVOKE ALL ON student_scholarship_results FROM '.$role);
   }
  }
 }
 public function down(): void { Schema::dropIfExists('student_scholarship_results'); }
};
