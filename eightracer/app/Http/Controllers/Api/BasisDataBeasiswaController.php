<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BasisDataBeasiswa;
use App\Services\Auth\SchoolAccess;
use App\Services\Beasiswa\EligibilityEvaluator;
use App\Services\School\Criteria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BasisDataBeasiswaController extends Controller
{
    private function admin(Request $request): void
    {
        abort_unless(SchoolAccess::isStaff($request->user()), 403);
    }

    public function index(Request $request)
    {
        $this->admin($request);

        $filters = $request->validate([
            'search' => 'nullable|string|max:100',
            'jenjang' => 'nullable|in:SMA,Kuliah,Keduanya',
            'page' => 'sometimes|integer|min:1',
        ]);
        $query = BasisDataBeasiswa::with('kriteriaKelayakanBeasiswa');
        if ($request->filled('search')) {
            $query->whereLike('nama_beasiswa', '%'.$filters['search'].'%');
        }
        if ($request->filled('jenjang')) {
            $query->whereIn('jenjang', array_unique([$filters['jenjang'], 'Keduanya']));
        }

        return response()->json($query->orderBy('id')->paginate(20)->appends($filters)->through(fn ($row) => $this->catalogData($row)));
    }

    public function show(Request $request, BasisDataBeasiswa $beasiswa)
    {
        $this->admin($request);

        return response()->json(['data' => $this->catalogData($beasiswa->load('kriteriaKelayakanBeasiswa'))]);
    }

    private function catalogData(BasisDataBeasiswa $row): array
    {
        return [...$row->toArray(), 'kriteria' => $row->kriteriaKelayakanBeasiswa->toArray()];
    }

    private function rules(Request $request): array
    {
        return [
            'nama_beasiswa' => 'required|string|max:255', 'penyelenggara' => 'nullable|string|max:255',
            'deskripsi' => 'nullable|string|max:3000', 'jenjang' => 'required|in:SMA,Kuliah,Keduanya',
            'tanggal_buka' => 'nullable|date_format:Y-m-d',
            'tanggal_tutup' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('tanggal_buka'), 'after_or_equal:tanggal_buka')],
            'link_pendaftaran' => 'nullable|url:http,https|max:500', 'is_active' => 'sometimes|boolean',
            'status_program' => 'prohibited', 'status_pendaftaran' => 'prohibited',
            'durasi' => 'nullable|numeric|gt:0|max:100', 'satuan_durasi' => 'required_with:durasi|nullable|in:tahun,semester,bulan', 'periode_apply' => 'nullable|string|max:255',
            'program_eligibilitas_id' => 'nullable|integer|exists:program_eligibilitas,id',
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

        return response()->json(['data' => $this->catalogData($this->save($request, new BasisDataBeasiswa))], 201);
    }

    public function update(Request $request, BasisDataBeasiswa $beasiswa)
    {
        $this->admin($request);

        return response()->json(['data' => $this->catalogData($this->save($request, $beasiswa))]);
    }

    private function save(Request $request, BasisDataBeasiswa $beasiswa): BasisDataBeasiswa
    {
        $data = $request->validate($this->rules($request));
        $open = array_key_exists('tanggal_buka', $data) ? $data['tanggal_buka'] : $beasiswa->tanggal_buka?->format('Y-m-d');
        $close = array_key_exists('tanggal_tutup', $data) ? $data['tanggal_tutup'] : $beasiswa->tanggal_tutup?->format('Y-m-d');
        if ($open && $close && $close < $open) {
            throw ValidationException::withMessages(['tanggal_tutup' => 'Tanggal tutup harus sama atau setelah tanggal buka.']);
        }
        $data['kriteria'] = Criteria::validate($data['kriteria']);
        if (collect($data['kriteria'])->contains(fn ($c) => in_array($c['tipe_kriteria'], ['status_eligible', 'skor_prestasi', 'bantuan_sosial_sesuai'], true))) {
            abort_unless(! empty($data['program_eligibilitas_id']), 422, 'Pilih program eligibilitas untuk kriteria status.');
        }
        foreach ($data['kriteria'] as $index => $criterion) {
            if ($criterion['tipe_kriteria'] !== 'status_eligible' && ! is_numeric($criterion['nilai_kriteria'])) {
                throw ValidationException::withMessages(["kriteria.$index.nilai_kriteria" => 'Nilai syarat harus berupa angka.']);
            }
            if ($criterion['tipe_kriteria'] === 'status_eligible' && ! in_array($criterion['operator'], ['=', '!='], true)) {
                throw ValidationException::withMessages(["kriteria.$index.operator" => 'Status hanya mendukung = dan !=.']);
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
