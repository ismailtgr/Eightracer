<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BasisDataBeasiswa;
use App\Services\Beasiswa\EligibilityEvaluator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BasisDataBeasiswaController extends Controller
{
    private function admin(Request $request): void
    {
        abort_unless($request->user()->role === 'administrator', 403);
    }

    public function index(Request $request)
    {
        $this->admin($request);

        return response()->json(BasisDataBeasiswa::with('kriteriaKelayakanBeasiswa')->orderBy('id')->paginate(20));
    }

    private function rules(Request $request): array
    {
        return [
            'nama_beasiswa' => 'required|string|max:255', 'penyelenggara' => 'nullable|string|max:255',
            'deskripsi' => 'nullable|string|max:3000', 'jenjang' => 'required|in:SMA,Kuliah,Keduanya',
            'tanggal_buka' => 'nullable|date_format:Y-m-d',
            'tanggal_tutup' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('tanggal_buka'), 'after_or_equal:tanggal_buka')],
            'link_pendaftaran' => 'nullable|url:http,https|max:500', 'is_active' => 'sometimes|boolean',
            'kriteria' => 'required|array|min:1|max:30',
            'kriteria.*.nama_kriteria' => 'required|string|max:255',
            'kriteria.*.tipe_kriteria' => ['required', Rule::in(EligibilityEvaluator::FIELDS)],
            'kriteria.*.operator' => 'required|in:=,!=,>=,<=,>,<',
            'kriteria.*.nilai_kriteria' => 'required|string|max:255',
            'kriteria.*.bobot' => 'nullable|numeric|min:0.01|max:999.99',
        ];
    }

    public function store(Request $request)
    {
        $this->admin($request);

        return response()->json(['data' => $this->save($request, new BasisDataBeasiswa)], 201);
    }

    public function update(Request $request, BasisDataBeasiswa $beasiswa)
    {
        $this->admin($request);

        return response()->json(['data' => $this->save($request, $beasiswa)]);
    }

    private function save(Request $request, BasisDataBeasiswa $beasiswa): BasisDataBeasiswa
    {
        $data = $request->validate($this->rules($request));
        foreach ($data['kriteria'] as $index => $criterion) {
            if ($criterion['tipe_kriteria'] !== 'status_eligible' && ! is_numeric($criterion['nilai_kriteria'])) {
                throw \Illuminate\Validation\ValidationException::withMessages(["kriteria.$index.nilai_kriteria" => 'Nilai syarat harus berupa angka.']);
            }
            if ($criterion['tipe_kriteria'] === 'status_eligible' && ! in_array($criterion['operator'], ['=', '!='], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(["kriteria.$index.operator" => 'Status hanya mendukung = dan !=.']);
            }
        }

        return DB::transaction(function () use ($beasiswa, $data) {
            $criteria = $data['kriteria'];
            unset($data['kriteria']);
            $beasiswa->fill($data)->save();
            $beasiswa->kriteriaKelayakanBeasiswa()->delete();
            $beasiswa->kriteriaKelayakanBeasiswa()->createMany($criteria);

            return $beasiswa->load('kriteriaKelayakanBeasiswa');
        });
    }

    public function archive(Request $request, BasisDataBeasiswa $beasiswa)
    {
        $this->admin($request);
        $beasiswa->update(['is_active' => false]);

        return response()->json(['message' => 'Beasiswa diarsipkan; riwayat rekomendasi tetap tersedia.']);
    }
}
