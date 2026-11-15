<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\BasisDataBeasiswa;
use App\Models\Siswa;
use App\Services\Auth\SchoolAccess;
use App\Services\Beasiswa\ProgramScholarshipAnalysis;
use App\Services\Beasiswa\StudentScholarshipCatalog;
use Illuminate\Http\Request;

class ProgramScholarshipAnalysisController extends Controller
{
    private function staff(Request $r):void {abort_unless(SchoolAccess::isStaff($r->user()),403);}
    private function available(BasisDataBeasiswa $p):void {
        abort_unless($p->is_active,404);
        abort_if($p->tanggal_tutup && $p->tanggal_tutup->format('Y-m-d') < \App\Services\Beasiswa\ScholarshipSchedule::today(),422,'Program tidak tersedia untuk analisis saat ini.');
    }
    public function index(Request $r, StudentScholarshipCatalog $catalog) {
        $this->staff($r); abort_if(array_diff(array_keys($r->all()),['page']),422);$r->validate(['page'=>'nullable|integer|min:1|max:1000']);
        $page=BasisDataBeasiswa::where('is_active',true)->orderByRaw('LOWER(nama_beasiswa)')->orderBy('id')->paginate(12);
        $page->getCollection()->transform(fn($p)=>$catalog->program($p));
        return response()->json($page);
    }
    public function analyse(Request $r,BasisDataBeasiswa $beasiswa,ProgramScholarshipAnalysis $service) {
        $this->staff($r);$this->available($beasiswa);abort_if(array_diff(array_keys($r->all()),['after_id']),422);
        $input=$r->validate(['after_id'=>'nullable|integer|min:0']);
        return response()->json($service->batch($beasiswa,$input['after_id']??0));
    }
    public function explain(Request $r,BasisDataBeasiswa $beasiswa,Siswa $siswa,ProgramScholarshipAnalysis $service) {
        $this->staff($r);$this->available($beasiswa);abort_if($r->all(),422);
        return response()->json(['data'=>$service->explain($siswa,$beasiswa)]);
    }
    public function note(Request $r,BasisDataBeasiswa $beasiswa,Siswa $siswa,ProgramScholarshipAnalysis $service) {
        $this->staff($r);$this->available($beasiswa);abort_if(array_diff(array_keys($r->all()),['fingerprint','revision','reason','timing']),422);
        $input=$r->validate(['fingerprint'=>'required|string|size:64','revision'=>'required|integer|min:0','reason'=>'nullable|string|max:1500','timing'=>'nullable|string|max:180']);
        foreach(['reason','timing'] as $field) abort_if(isset($input[$field])&&!\App\Support\PlainText::safe($input[$field]),422);
        return response()->json(['data'=>$service->saveNote($siswa,$beasiswa,$input),'message'=>'Catatan admin berhasil disimpan.']);
    }
}
