<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Services\Beasiswa\ScholarshipChat;
use Illuminate\Http\Request;

class ScholarshipChatController extends Controller
{
    private function input(Request $r): array
    {
        return $r->validate(['message' => 'required|string|min:3|max:2000', 'beasiswa_id' => 'nullable|integer|exists:basis_data_beasiswa,id', 'target_jenjang' => 'nullable|in:SMA,Kuliah', 'siswa_id' => 'prohibited', 'history' => 'prohibited']);
    }

    public function mine(Request $r, ScholarshipChat $service)
    {
        abort_unless($r->user()->role === 'siswa', 403);
        $input=$this->input($r);
        $personal=(bool)preg_match('/cocok|kelayakan|profil|nilaiku|raporku|penghasilanku|direkomendasikan|sesuai.*(?:saya|aku)|rekomendasi\s+beasiswa/iu',$input['message']);
        if(!$personal && isset($input['beasiswa_id'])) {
            $program=\App\Models\BasisDataBeasiswa::where('is_active',true)->get()->sortByDesc(fn($p)=>mb_strlen($p->nama_beasiswa))->first(fn($p)=>mb_stripos($input['message'],$p->nama_beasiswa)!==false)
                ?? \App\Models\BasisDataBeasiswa::where('is_active',true)->findOrFail($input['beasiswa_id']);
            return response()->json(['data'=>$service->information($program,$input['message'],app(\App\Services\School\LocalStudentPreview::class)->enabled($r))]);
        }
        $s = $r->user()->siswa;
        abort_unless($s, 404);
        return response()->json(['data' => $service->answer($s, $input)]);
    }

    public function staff(Request $r, Siswa $siswa, ScholarshipChat $service)
    {
        return response()->json(['data' => $service->answer($siswa, $this->input($r))]);
    }
}
