<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PerguruanTinggi;
use Illuminate\Http\Request;

class PerguruanTinggiController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search'   => 'nullable|string|max:100',
            'jenis_pt' => 'nullable|in:Negeri,Swasta',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = PerguruanTinggi::query();

        if (! empty($validated['search'])) {
            $search = trim($validated['search']);
            $query->where(function ($q) use ($search) {
                $q->whereLike('nama_pt', "%{$search}%")
                  ->orWhereLike('nama_singkat', "%{$search}%");
            });
        }

        if (! empty($validated['jenis_pt'])) {
            $query->where('jenis_pt', $validated['jenis_pt']);
        }

        $perPage = $validated['per_page'] ?? 20;

        $paginated = $query->orderBy('nama_pt')->paginate($perPage);

        return response()->json([
            'data' => collect($paginated->items())->map(fn($pt) => [
                'id'           => $pt->id,
                'nama_pt'      => $pt->nama_pt,
                'nama_singkat' => $pt->nama_singkat,
                'jenis_pt'     => $pt->jenis_pt,
                'kota'         => $pt->kota,
                'provinsi'     => $pt->provinsi,
                'akreditasi'   => $pt->akreditasi,
            ]),
            'meta' => [
                'total'        => $paginated->total(),
                'per_page'     => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
            ],
        ]);
    }
}