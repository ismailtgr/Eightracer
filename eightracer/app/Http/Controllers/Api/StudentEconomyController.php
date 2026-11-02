<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BantuanSiswa;
use App\Models\MediaSosial;
use App\Models\OrangTua;
use App\Models\Siswa;
use App\Models\Wali;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentEconomyController extends Controller
{
    public function save(Request $r, Siswa $siswa)
    {
        $d = $r->validate(['orang_tua' => 'required|array|size:2', 'orang_tua.*' => 'required|array:jenis_orang_tua,nama,kondisi,pekerjaan,penghasilan,alamat,kontak_wa',
            'orang_tua.*.jenis_orang_tua' => 'required|distinct|in:ayah,ibu', 'orang_tua.*.nama' => 'required|string|max:150', 'orang_tua.*.kondisi' => 'required|in:hidup,meninggal,bercerai',
            'orang_tua.*.pekerjaan' => 'nullable|string|max:100', 'orang_tua.*.penghasilan' => 'nullable|numeric|between:0,9999999999999', 'orang_tua.*.alamat' => 'nullable|string|max:2000', 'orang_tua.*.kontak_wa' => 'nullable|string|max:20',
            'wali' => 'nullable|array:nama,hubungan,pekerjaan,penghasilan,alamat,kontak_wa', 'wali.nama' => 'required_with:wali|string|max:150', 'wali.hubungan' => 'nullable|string|max:50', 'wali.pekerjaan' => 'nullable|string|max:100', 'wali.penghasilan' => 'nullable|numeric|between:0,9999999999999', 'wali.alamat' => 'nullable|string|max:2000', 'wali.kontak_wa' => 'nullable|string|max:20',
            'bantuan_ids' => 'present|array|max:30', 'bantuan_ids.*' => 'integer|distinct|exists:jenis_bantuan,id']);
        abort_if(collect($d['orang_tua'])->contains(fn ($p) => $p['kondisi'] !== 'hidup') && empty($d['wali']) && ! $siswa->wali()->exists(), 422, 'Data wali wajib untuk kondisi orang tua ini.');
        DB::transaction(function () use ($d, $siswa) {
            Siswa::whereKey($siswa->id)->lockForUpdate()->firstOrFail();
            foreach ($d['orang_tua'] as $p) {
                OrangTua::updateOrCreate(['siswa_id' => $siswa->id, 'jenis_orang_tua' => $p['jenis_orang_tua']], $p);
            }
            if (! empty($d['wali'])) {
                Wali::updateOrCreate(['siswa_id' => $siswa->id], $d['wali']);
            }
            $siswa->bantuanSiswa()->update(['aktif' => false]);
            foreach ($d['bantuan_ids'] as $id) {
                BantuanSiswa::updateOrCreate(['siswa_id' => $siswa->id, 'jenis_bantuan_id' => $id], ['aktif' => true, 'tanggal_selesai' => null]);
            }
            $siswa->forceFill(['bantuan_dikonfirmasi' => true])->save();
        });

        return response()->json(['message' => 'Data ekonomi disimpan.']);
    }

    public function updateRelated(Request $r, Siswa $siswa, string $type, int $id)
    {
        $models = ['bantuan' => BantuanSiswa::class, 'media-sosial' => MediaSosial::class];
        abort_unless(isset($models[$type]), 404);
        $model = $models[$type];
        $row = $model::where('siswa_id', $siswa->id)->findOrFail($id);
        if ($r->isMethod('delete')) {
            $row->delete();

            return response()->json(['message' => 'Data dihapus.']);
        }
        $rules = $type === 'bantuan' ? ['jenis_bantuan_id' => 'required|integer|exists:jenis_bantuan,id', 'nomor_penerima' => 'nullable|string|max:100', 'tanggal_mulai' => 'nullable|date', 'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal_mulai', 'aktif' => 'required|boolean'] : ['platform' => 'required|string|max:50', 'username' => 'required|string|max:100'];
        $row->fill($r->validate($rules))->save();

        return response()->json(['data' => $row]);
    }
}
