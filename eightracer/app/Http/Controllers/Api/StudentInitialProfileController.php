<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Models\StudentProfileSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class StudentInitialProfileController extends Controller
{
    private const FIELDS = ['nama_panggilan', 'alamat_rumah', 'bahasa_keseharian'];

    private function rules(): array
    {
        return ['nama_panggilan' => 'sometimes|nullable|string|max:50', 'alamat_rumah' => 'sometimes|nullable|string|max:2000', 'bahasa_keseharian' => 'sometimes|nullable|string|max:50', 'hobi' => 'sometimes|array|max:20', 'hobi.*' => 'required|integer|distinct|exists:hobi,id', 'cita_cita' => 'sometimes|array|max:20', 'cita_cita.*' => 'required|integer|distinct|exists:cita_cita,id'];
    }

    private function fingerprint(Siswa $s): string
    {
        return hash('sha256', json_encode([$s->only(self::FIELDS), $s->hobi()->orderBy('hobi.id')->pluck('hobi.id')->all(), $s->citaCita()->orderBy('cita_cita.id')->pluck('cita_cita.id')->all()], JSON_THROW_ON_ERROR));
    }

    private function own(Request $r): Siswa
    {
        abort_unless($r->user()->role === 'siswa' && $r->user()->siswa, 404);

        return $r->user()->siswa;
    }

    public function show(Request $r)
    {
        $s = $this->own($r);

        return response()->json(['data' => [...$s->only(['id', 'nisn', 'nama_lengkap', 'jenis_kelamin', ...self::FIELDS]), 'hobi' => $s->hobi()->get(['hobi.id', 'nama_hobi']), 'cita_cita' => $s->citaCita()->get(['cita_cita.id', 'nama_cita_cita']),
            'submissions' => StudentProfileSubmission::where('siswa_id', $s->id)->latest()->limit(10)->get()]]);
    }

    public function submit(Request $r)
    {
        abort_if(array_diff(array_keys($r->all()), [...self::FIELDS, 'hobi', 'cita_cita']), 422);
        $d = $r->validate($this->rules());
        abort_unless(count($d), 422);
        $s = $this->own($r);
        $row = DB::transaction(function () use ($r, $d, $s) {
            $s = Siswa::lockForUpdate()->findOrFail($s->id);
            $row = StudentProfileSubmission::where('siswa_id', $s->id)->where('status', 'pending')->first() ?? new StudentProfileSubmission(['id' => (string) Str::uuid()]);
            $row->fill(['siswa_id' => $s->id, 'submitted_by' => $r->user()->id, 'payload' => $d, 'source_fingerprint' => $this->fingerprint($s), 'status' => 'pending'])->save();

            return $row;
        });

        return response()->json(['data' => $row, 'message' => 'Perubahan awal menunggu pemeriksaan admin/BK.'], 201);
    }

    public function index(Request $r)
    {
        return response()->json(StudentProfileSubmission::where('status', 'pending')->latest()->paginate(20));
    }

    public function detail(string $submission)
    {
        $row = StudentProfileSubmission::findOrFail($submission);

        return response()->json(['data' => $row, 'proposed_changes' => $row->payload]);
    }

    public function review(Request $r, string $submission)
    {
        $d = $r->validate(['status' => 'required|in:approved,rejected']);
        $row = DB::transaction(function () use ($r, $submission, $d) {
            $row = StudentProfileSubmission::lockForUpdate()->findOrFail($submission);
            abort_unless($row->status === 'pending', 409);
            $s = Siswa::lockForUpdate()->findOrFail($row->siswa_id);
            if ($d['status'] === 'approved') {
                abort_unless(hash_equals($row->source_fingerprint, $this->fingerprint($s)), 409);
                $payload = Validator::make($row->payload, $this->rules())->validate();
                $s->fill(array_intersect_key($payload, array_flip(self::FIELDS)))->save();
                if (array_key_exists('hobi', $payload)) {
                    $s->hobi()->sync($payload['hobi']);
                }
                if (array_key_exists('cita_cita', $payload)) {
                    $s->citaCita()->sync(collect($payload['cita_cita'])->mapWithKeys(fn ($id, $i) => [$id => ['prioritas' => $i + 1]])->all());
                }
            }
            $row->update(['status' => $d['status'], 'reviewed_by' => $r->user()->id, 'reviewed_at' => now()]);

            return $row;
        });

        return response()->json(['data' => $row]);
    }
}
