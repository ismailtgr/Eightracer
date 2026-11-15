<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\BasisDataBeasiswa;
use App\Services\Auth\SchoolAccess;
use App\Services\Beasiswa\StudentScholarshipCatalog;
use Illuminate\Http\Request;
class StudentScholarshipCatalogController extends Controller {
 private function access(Request $r):void { abort_unless($r->user()->role==='siswa'||SchoolAccess::isStaff($r->user()),403); }
 public function index(Request $request,StudentScholarshipCatalog $catalog) {
  $this->access($request);abort_if(array_diff(array_keys($request->all()),['page']),422);
  $request->validate(['page'=>'sometimes|integer|min:1|max:10000']);
  return response()->json(BasisDataBeasiswa::where('is_active',true)->orderByRaw('LOWER(TRIM(nama_beasiswa)) ASC')->orderBy('id')->paginate(12)->through(fn($p)=>$catalog->program($p)));
 }
 public function show(Request $request,BasisDataBeasiswa $beasiswa,StudentScholarshipCatalog $catalog) {
  $this->access($request);abort_if($request->query(),422);abort_unless($beasiswa->is_active,404);
  return response()->json(['data'=>$catalog->program($beasiswa->load('kriteriaKelayakanBeasiswa'),true,app(\App\Services\School\LocalStudentPreview::class)->enabled($request))]);
 }
}
