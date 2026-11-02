<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AlumniProgress;
use App\Models\PrestasiMurid;
use App\Models\Siswa;
use App\Models\StudyTracer;
use App\Services\School\AlumniService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudyTracerController extends Controller
{
    private function query(Request $r, bool $public = false)
    {
        $r->validate(['search' => 'nullable|string|max:100', 'angkatan' => 'nullable|digits:4', 'jenis_pt' => 'nullable|in:Negeri,Swasta,Kedinasan,Luar Negeri', 'program_studi' => 'nullable|string|max:100', 'jalur_masuk' => 'nullable|string|max:50', 'perguruan_tinggi_id' => 'nullable|integer', 'penerima_kipk' => 'nullable|boolean', 'beasiswa_id' => 'nullable|integer|exists:basis_data_beasiswa,id', 'per_page' => 'sometimes|integer|between:1,100']);
        $q = StudyTracer::with(['siswa', 'perguruanTinggi']);
        if ($public) {
            $q->with('prestasi')->where('izinkan_tampil_di_buku', true)->where('status_penerimaan', 'diterima');
        }
        if ($r->filled('search')) {
            $term = $r->input('search');
            $q->whereHas('siswa', fn ($s) => $s->where('nama_lengkap', 'like', '%'.$term.'%'));
        }
        if ($r->filled('angkatan')) {
            $q->whereHas('siswa', fn ($s) => $s->where('tahun_lulus', $r->input('angkatan')));
        }
        if ($r->filled('jenis_pt')) {
            $q->whereHas('perguruanTinggi', fn ($p) => $p->where('jenis_pt', $r->input('jenis_pt')));
        }
        foreach (['program_studi', 'jalur_masuk', 'perguruan_tinggi_id'] as $field) {
            if ($r->filled($field)) {
                $q->where($field, $r->input($field));
            }
        }
        if ($r->has('penerima_kipk') || $r->filled('beasiswa_id')) {
            $q->whereHas('alumniProgress', function ($p) use ($r, $public) {
                $p->where('is_current', true);
                if ($public) {
                    $p->where('is_published', true);
                }if ($r->has('penerima_kipk')) {
                    $p->where('penerima_kipk', $r->boolean('penerima_kipk'));
                }if ($r->filled('beasiswa_id')) {
                    $p->whereHas('alumniProgressBeasiswa', fn ($b) => $b->where('beasiswa_id', $r->input('beasiswa_id'))->where('status_beasiswa', 'diterima'));
                }
            });
        }

        return $q;
    }

    public function index(Request $r)
    {
        return response()->json($this->query($r)->latest('id')->paginate($r->integer('per_page', 20)));
    }

    public function show(int $id)
    {
        return response()->json(['data' => StudyTracer::with(['siswa', 'perguruanTinggi', 'prestasi'])->findOrFail($id)]);
    }

    public function save(Request $r, ?int $id = null)
    {
        $d = $r->validate(['siswa_id' => 'required_without:alumni_baru|nullable|integer|exists:siswa,id', 'alumni_baru' => 'nullable|array:nama_lengkap,jenis_kelamin,nisn,tahun_lulus',
            'alumni_baru.nama_lengkap' => 'required_with:alumni_baru|string|max:150', 'alumni_baru.jenis_kelamin' => 'nullable|in:L,P', 'alumni_baru.nisn' => 'nullable|digits:10|unique:siswa,nisn', 'alumni_baru.tahun_lulus' => 'required_with:alumni_baru|digits:4',
            'perguruan_tinggi_id' => 'required|integer|exists:perguruan_tinggi,id', 'strata_pendidikan' => 'required|in:D3,D4,S1,S2,S3', 'program_studi' => 'required|string|max:255',
            'jalur_masuk' => 'required|in:SNBP,SNBT,PMDP,SIMAMA,SIMAMI,Mandiri,Tes Kedinasan,Reguler,Undangan,Lainnya', 'sub_jalur_mandiri' => 'nullable|in:Rapor,Skor UTBK,Tes Kampus,Konsorsium',
            'pilihan_ke' => 'nullable|integer|between:1,20', 'status_penerimaan' => 'required|in:menunggu,diterima,tidak_diterima', 'nilai_rapor_pendaftaran' => 'nullable|numeric|between:0,100', 'skor_utbk' => 'nullable|numeric|between:0,1000',
            'kontak_wa_terbaru' => 'nullable|string|max:20', 'is_pelamar_kipk' => 'sometimes|boolean', 'tanggal_pengisian' => 'nullable|date|before_or_equal:today', 'tanggal_masuk_pt' => 'nullable|date', 'tahun_masuk_pt' => 'nullable|integer|between:1900,2100',
            'prestasi_ids' => 'sometimes|array|max:30', 'prestasi_ids.*' => 'integer|distinct|exists:prestasi_murid,id', 'izinkan_tampil_di_buku' => 'prohibited']);
        abort_if(isset($d['siswa_id']) && ! empty($d['alumni_baru']), 422);
        if ($d['jalur_masuk'] === 'SNBP' || ($d['jalur_masuk'] === 'Mandiri' && ($d['sub_jalur_mandiri'] ?? null) === 'Rapor')) {
            abort_unless(isset($d['nilai_rapor_pendaftaran']), 422);
        }
        if ($d['jalur_masuk'] === 'SNBT' || ($d['jalur_masuk'] === 'Mandiri' && ($d['sub_jalur_mandiri'] ?? null) === 'Skor UTBK')) {
            abort_unless(isset($d['skor_utbk']), 422);
        }
        if ($d['jalur_masuk'] !== 'Mandiri') {
            abort_if(! empty($d['sub_jalur_mandiri']), 422);
        }
        $record = DB::transaction(function () use ($d, $id) {
            $ids = $d['prestasi_ids'] ?? null;
            unset($d['prestasi_ids']);
            if (! empty($d['alumni_baru'])) {
                abort_if($id, 422);
                $s = Siswa::create([...$d['alumni_baru'], 'user_id' => null]);
                $d['siswa_id'] = $s->id;
            }unset($d['alumni_baru']);
            $t = $id ? StudyTracer::lockForUpdate()->findOrFail($id) : new StudyTracer;
            if ($id) {
                abort_unless((int) $t->siswa_id === (int) $d['siswa_id'], 422);
            }
            $t->fill($d);
            if ($id) {
                $t->forceFill(['izinkan_tampil_di_buku' => false, 'consent_reference' => null, 'consent_at' => null, 'consent_by' => null]);
            }$t->save();
            if ($ids !== null) {
                abort_unless(PrestasiMurid::where('siswa_id', $t->siswa_id)->whereIn('id', $ids)->count() === count($ids), 422);
                $t->prestasi()->sync($ids);
            }

            return $t->load(['siswa', 'perguruanTinggi', 'prestasi']);
        });

        return response()->json(['data' => $record], $id ? 200 : 201);
    }

    public function destroy(int $id)
    {
        $t = StudyTracer::findOrFail($id);
        abort_if($t->alumniProgress()->exists(), 409, 'Riwayat progres harus dipertahankan.');
        $t->delete();

        return response()->json(['message' => 'Data dihapus.']);
    }

    public function publish(Request $r, int $id)
    {
        $d = $r->validate(['is_published' => 'required|boolean', 'consent_reference' => 'required_if:is_published,true|nullable|string|max:500', 'persetujuan_dikonfirmasi' => 'exclude_unless:is_published,true|required|accepted']);
        $t = StudyTracer::findOrFail($id);
        abort_if($d['is_published'] && $t->status_penerimaan !== 'diterima', 422);
        $t->forceFill(['izinkan_tampil_di_buku' => $d['is_published'], 'consent_reference' => $d['consent_reference'] ?? null, 'consent_at' => $d['is_published'] ? now() : null, 'consent_by' => $d['is_published'] ? $r->user()->id : null])->save();

        return response()->json(['message' => 'Izin publikasi diperbarui.']);
    }

    public function studentIndex(Request $r, AlumniService $service)
    {
        return response()->json($this->query($r, true)->latest('id')->paginate($r->integer('per_page', 20))->through(fn ($t) => $service->admission($t)));
    }

    public function studentShow(int $id, AlumniService $service)
    {
        $t = StudyTracer::where('izinkan_tampil_di_buku', true)->where('status_penerimaan', 'diterima')->with(['siswa', 'perguruanTinggi', 'prestasi'])->findOrFail($id);

        return response()->json(['data' => ['masuk_pt' => $service->admission($t), 'progress' => $t->alumniProgress()->where('is_current', true)->where('is_published', true)->with('alumniProgressBeasiswa.beasiswa')->orderBy('tanggal_update')->get()->map(fn ($p) => $service->progress($p)), 'timeline' => $service->timeline($t)]]);
    }

    public function progress(Request $r, int $id, AlumniService $service)
    {
        $p = AlumniProgress::findOrFail($id);

        return response()->json(['data' => $service->saveProgress($r, $p->siswa, $p)], 201);
    }

    public function publishProgress(Request $r, int $id)
    {
        $d = $r->validate(['is_published' => 'required|boolean', 'publish_financials' => 'required|boolean', 'consent_reference' => 'required_if:is_published,true|nullable|string|max:500', 'persetujuan_dikonfirmasi' => 'exclude_unless:is_published,true|required|accepted']);
        $p = AlumniProgress::findOrFail($id);
        abort_unless($p->is_current, 409);
        abort_if(! $d['is_published'] && $d['publish_financials'], 422);
        $p->forceFill(['is_published' => $d['is_published'], 'publish_financials' => $d['publish_financials'], 'consent_reference' => $d['consent_reference'] ?? null, 'consent_at' => $d['is_published'] ? now() : null, 'consent_by' => $d['is_published'] ? $r->user()->id : null])->save();

        return response()->json(['message' => 'Izin periode diperbarui.']);
    }
}
