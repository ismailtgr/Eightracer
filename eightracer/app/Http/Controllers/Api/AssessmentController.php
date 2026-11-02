<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AsesmenKarakteristik;
use App\Models\AsesmenMultipleIntelligence;
use App\Models\AsesmenRiasec;
use App\Models\Siswa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssessmentController extends Controller
{
    public function history(Siswa $siswa)
    {
        return response()->json(['data' => ['karakteristik' => $siswa->asesmenKarakteristik()->orderByDesc('tanggal_asesmen')->get(), 'riasec' => $siswa->asesmenRiasec()->orderByDesc('tanggal_asesmen')->get(), 'multiple_intelligence' => $siswa->asesmenMultipleIntelligence()->orderByDesc('tanggal_asesmen')->get()]]);
    }

    public function mine(Request $r)
    {
        $s = $r->user()->siswa;
        abort_unless($s, 404);

        return $this->history($s);
    }

    public function store(Request $r, Siswa $siswa)
    {
        $rules = ['tanggal_asesmen' => 'required|date|before_or_equal:now', 'tipe_kepribadian' => 'nullable|in:Melankolis,Plegmatis,Koleris,Sanguinis', 'gaya_belajar' => 'nullable|in:Visual,Auditori,Kinestetik,Multimodal', 'iq' => 'nullable|numeric|between:0,300',
            'riasec' => 'nullable|array:realistic_score,investigative_score,artistic_score,social_score,enterprising_score,conventional_score', 'multiple_intelligence' => 'nullable|array|min:1|max:12',
            'multiple_intelligence.*' => 'required|array:jenis_kecerdasan,skor', 'multiple_intelligence.*.jenis_kecerdasan' => 'required|string|max:100|distinct', 'multiple_intelligence.*.skor' => 'required|numeric|between:0,100', 'koreksi_dari_id' => 'nullable|integer'];
        $letters = ['realistic_score' => 'R', 'investigative_score' => 'I', 'artistic_score' => 'A', 'social_score' => 'S', 'enterprising_score' => 'E', 'conventional_score' => 'C'];
        foreach ($letters as $field => $letter) {
            $rules['riasec.'.$field] = 'required_with:riasec|numeric|between:0,100';
        }
        $d = $r->validate($rules);
        abort_unless(($d['riasec'] ?? null) || ($d['multiple_intelligence'] ?? null) || ($d['tipe_kepribadian'] ?? null) || ($d['gaya_belajar'] ?? null) || isset($d['iq']), 422);
        $record = DB::transaction(function () use ($d, $r, $siswa, $letters) {
            Siswa::whereKey($siswa->id)->lockForUpdate()->firstOrFail();
            if (! empty($d['koreksi_dari_id'])) {
                AsesmenKarakteristik::where('siswa_id', $siswa->id)->findOrFail($d['koreksi_dari_id']);
                abort_if(AsesmenKarakteristik::where('supersedes_id', $d['koreksi_dari_id'])->exists(), 409);
            }
            AsesmenKarakteristik::where('siswa_id', $siswa->id)->update(['is_current' => false]);
            $codes = null;
            if (! empty($d['riasec']) && max($d['riasec']) > 0) {
                $scores = $d['riasec'];
                uksort($scores, fn ($a, $b) => ($scores[$b] <=> $scores[$a]) ?: strcmp($letters[$a], $letters[$b]));
                $codes = implode('', array_map(fn ($key) => $letters[$key], array_slice(array_keys($scores), 0, 3)));
            }
            $mi = collect($d['multiple_intelligence'] ?? [])->sortByDesc('skor')->first();
            $record = new AsesmenKarakteristik;
            $record->forceFill(['siswa_id' => $siswa->id, 'tanggal_asesmen' => $d['tanggal_asesmen'], 'tipe_kepribadian' => $d['tipe_kepribadian'] ?? null, 'gaya_belajar' => $d['gaya_belajar'] ?? null, 'iq' => $d['iq'] ?? null,
                'kode_riasec' => $codes, 'kecerdasan_majemuk_dominan' => $mi['jenis_kecerdasan'] ?? null, 'is_current' => false, 'supersedes_id' => $d['koreksi_dari_id'] ?? null, 'created_by' => $r->user()->id])->save();
            if (! empty($d['riasec'])) {
                $row = new AsesmenRiasec;
                $row->forceFill([...$d['riasec'], 'siswa_id' => $siswa->id, 'asesmen_id' => $record->id, 'tanggal_asesmen' => $d['tanggal_asesmen'], 'kode_hasil' => $codes])->save();
            }
            foreach ($d['multiple_intelligence'] ?? [] as $item) {
                $row = new AsesmenMultipleIntelligence;
                $row->forceFill([...$item, 'siswa_id' => $siswa->id, 'asesmen_id' => $record->id, 'tanggal_asesmen' => $d['tanggal_asesmen']])->save();
            }

            $superseded = AsesmenKarakteristik::where('siswa_id', $siswa->id)->whereNotNull('supersedes_id')->pluck('supersedes_id');
            AsesmenKarakteristik::where('siswa_id', $siswa->id)->whereNotIn('id', $superseded)->latest('tanggal_asesmen')->latest('id')->first()->update(['is_current' => true]);

            return $record->fresh();
        });

        return response()->json(['data' => $record], 201);
    }
}
