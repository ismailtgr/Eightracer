<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guru;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class GuruBkController extends Controller
{

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $query = Guru::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('nip', 'like', "%{$search}%")
                  ->orWhere('jabatan', 'like', "%{$search}%");
            });
        }

        $daftarGuru = $query->orderBy('nama_lengkap')->get()->map(
            fn (Guru $g) => $this->formatGuru($g)
        )->values();

        return response()->json([
            'judul'    => 'Direktori Guru BK',
            'subjudul' => 'Menyediakan catatan evaluasi atau saran strategi belajar tambahan secara khusus apabila diperlukan berdasarkan perkembangan nilai belajar siswa',
            'total'    => $daftarGuru->count(),
            'data'     => $daftarGuru,
        ]);
    }

    public function show(Guru $guru): JsonResponse
    {
        return response()->json([
            'judul_modal'    => 'Kelola Profil Guru Bimbingan dan Konseling',
            'subjudul_modal' => 'Tambahkan atau perbarui identitas diri',
            'ketentuan_foto' => 'Format JPG/PNG maksimal 2MB',
            'data'           => $this->formatGuru($guru),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'nip'          => 'required|string|max:50|unique:guru,nip',
            'jabatan'      => 'nullable|string|max:150',
            'foto_profil'  => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ], [
            'nama_lengkap.required' => 'Nama Lengkap wajib diisi.',
            'nip.required'          => 'Nomor Induk Pegawai (NIP) wajib diisi.',
            'nip.unique'            => 'Nomor Induk Pegawai (NIP) sudah terdaftar.',
            'foto_profil.image'     => 'File harus berupa gambar.',
            'foto_profil.mimes'     => 'Format foto profil harus JPG atau PNG.',
            'foto_profil.max'       => 'Ukuran foto profil maksimal 2MB.',
        ]);

        $guru = DB::transaction(function () use ($request, $validated) {
            $nip = trim($validated['nip']);

            $user = User::firstOrCreate(
                ['username' => $nip],
                [
                    'password' => $nip,
                    'role'     => 'guru',
                ]
            );

            $pathFoto = null;
            if ($request->hasFile('foto_profil')) {
                $pathFoto = $request->file('foto_profil')->store('guru-bk', 'public');
            }

            return Guru::create([
                'user_id'      => $user->id,
                'nip'          => $nip,
                'nama_lengkap' => trim($validated['nama_lengkap']),
                'jabatan'      => $validated['jabatan'] ?? 'Guru Bimbingan dan Konseling',
                'foto_profil'  => $pathFoto,
            ]);
        });

        return response()->json([
            'message' => 'Profil Guru BK berhasil ditambahkan.',
            'data'    => $this->formatGuru($guru),
        ], 201);
    }

    public function update(Request $request, Guru $guru): JsonResponse
    {
        $validated = $request->validate([
            'nama_lengkap' => 'sometimes|required|string|max:255',
            'nip'          => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('guru', 'nip')->ignore($guru->id),
            ],
            'jabatan'      => 'nullable|string|max:150',
            'foto_profil'  => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'hapus_foto'   => 'nullable|boolean',
        ], [
            'nama_lengkap.required' => 'Nama Lengkap wajib diisi.',
            'nip.required'          => 'Nomor Induk Pegawai (NIP) wajib diisi.',
            'nip.unique'            => 'Nomor Induk Pegawai (NIP) sudah digunakan Guru BK lain.',
            'foto_profil.image'     => 'File harus berupa gambar.',
            'foto_profil.mimes'     => 'Format foto profil harus JPG atau PNG.',
            'foto_profil.max'       => 'Ukuran foto profil maksimal 2MB.',
        ]);

        DB::transaction(function () use ($request, $validated, $guru) {
            $payload = [];

            if (array_key_exists('nama_lengkap', $validated)) {
                $payload['nama_lengkap'] = trim($validated['nama_lengkap']);
            }

            if (array_key_exists('nip', $validated)) {
                $nipBaru = trim($validated['nip']);
                $payload['nip'] = $nipBaru;

                if ($guru->user && $guru->user->username !== $nipBaru) {
                    $sudahDipakai = User::where('username', $nipBaru)->where('id', '!=', $guru->user_id)->exists();
                    if (!$sudahDipakai) {
                        $guru->user->update(['username' => $nipBaru]);
                    }
                }
            }

            if (array_key_exists('jabatan', $validated) && $validated['jabatan'] !== null) {
                $payload['jabatan'] = trim($validated['jabatan']);
            }

            if (!empty($validated['hapus_foto']) && $guru->foto_profil) {
                Storage::disk('public')->delete($guru->foto_profil);
                $payload['foto_profil'] = null;
            }

            if ($request->hasFile('foto_profil')) {
                if ($guru->foto_profil) {
                    Storage::disk('public')->delete($guru->foto_profil);
                }
                $payload['foto_profil'] = $request->file('foto_profil')->store('guru-bk', 'public');
            }

            $guru->update($payload);
        });

        return response()->json([
            'message' => 'Profil Guru BK berhasil diperbarui.',
            'data'    => $this->formatGuru($guru->fresh()),
        ]);
    }

    public function hapusFoto(Guru $guru): JsonResponse
    {
        if ($guru->foto_profil) {
            Storage::disk('public')->delete($guru->foto_profil);
            $guru->update(['foto_profil' => null]);
        }

        return response()->json([
            'message' => 'Foto profil Guru BK berhasil dihapus.',
            'data'    => $this->formatGuru($guru->fresh()),
        ]);
    }

    public function destroy(Guru $guru): JsonResponse
    {
        $nama = $guru->nama_lengkap;

        DB::transaction(function () use ($guru) {
            if ($guru->foto_profil) {
                Storage::disk('public')->delete($guru->foto_profil);
            }
            $guru->delete();
        });

        return response()->json([
            'message' => "Profil Guru BK {$nama} berhasil dihapus.",
        ]);
    }

    private function formatGuru(Guru $g): array
    {
        return [
            'id'              => $g->id,
            'inisial'         => $g->inisial,
            'nama_lengkap'    => $g->nama_lengkap,
            'nip'             => $g->nip,
            'label_nip'       => 'NIP: ' . $g->nip,
            'jabatan'         => $g->jabatan ?? 'Guru Bimbingan dan Konseling',
            'foto_profil'     => $g->foto_profil,
            'foto_profil_url' => $g->foto_profil_url,
        ];
    }
}
