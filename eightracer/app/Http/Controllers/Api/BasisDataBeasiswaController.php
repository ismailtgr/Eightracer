<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BasisDataBeasiswa;
use App\Services\Auth\SchoolAccess;
use App\Services\Beasiswa\ScholarshipCatalog;
use Illuminate\Http\Request;

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

        return response()->json($query->orderByDesc('is_active')->orderByRaw('LOWER(TRIM(nama_beasiswa)) ASC')->orderBy('id')->paginate(20)->appends($filters)->through(fn ($row) => $this->catalogData($row)));
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

    public function store(Request $request, ScholarshipCatalog $catalog)
    {
        $this->admin($request);

        return response()->json(['data' => $this->catalogData($catalog->create($request->all()))], 201);
    }

    public function update(Request $request, BasisDataBeasiswa $beasiswa, ScholarshipCatalog $catalog)
    {
        $this->admin($request);

        return response()->json(['data' => $this->catalogData($catalog->update($request->all(), $beasiswa))]);
    }

    public function bulk(Request $request, ScholarshipCatalog $catalog)
    {
        $this->admin($request);

        $rows = $request->input('rows');
        if (! is_array($rows) || array_is_list($rows) === false) {
            return response()->json([
                'message' => 'Format impor tidak valid.',
                'errors' => ['rows' => ['Kirim daftar beasiswa pada field rows.']],
            ], 422);
        }

        $saved = $catalog->createMany($rows);

        return response()->json([
            'message' => count($saved).' beasiswa berhasil ditambahkan ke katalog.',
            'data' => array_map(fn (BasisDataBeasiswa $row) => $this->catalogData($row), $saved),
        ], 201);
    }

    public function activate(Request $request, BasisDataBeasiswa $beasiswa)
    {
        $this->admin($request);
        abort_if(count($request->all()) > 0, 422, 'Aktivasi hanya mengubah status program.');
        $beasiswa->update(['is_active' => true]);
        return response()->json(['message'=>'Beasiswa berhasil diaktifkan.', 'data'=>$this->catalogData($beasiswa->load('kriteriaKelayakanBeasiswa'))]);
    }

    public function archive(Request $request, BasisDataBeasiswa $beasiswa)
    {
        $this->admin($request);
        $beasiswa->update(['is_active' => false]);

        return response()->json(['message' => 'Beasiswa diarsipkan; riwayat rekomendasi tetap tersedia.']);
    }
}
