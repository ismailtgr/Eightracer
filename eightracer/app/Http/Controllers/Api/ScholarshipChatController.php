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
        $s = $r->user()->siswa;
        abort_unless($s, 404);

        return response()->json(['data' => $service->answer($s, $this->input($r))]);
    }

    public function staff(Request $r, Siswa $siswa, ScholarshipChat $service)
    {
        return response()->json(['data' => $service->answer($siswa, $this->input($r))]);
    }
}
