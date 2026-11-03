<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CatatanKarakter;
use App\Models\PrestasiMurid;
use App\Models\RekapPresensi;
use App\Models\RiwayatOrganisasi;
use App\Models\SaudaraTiri;
use App\Models\Siswa;
use App\Services\School\SchoolRecordWriter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentSupportController extends Controller
{
    public function show(Siswa $siswa)
    {
        return response()->json(['data' => $siswa->load(['nilaiRapor.mataPelajaran', 'rekapPresensi', 'catatanKarakter', 'riwayatOrganisasi', 'prestasiMurid', 'saudaraTiri', 'hobi', 'citaCita', 'riwayatKelas.kelas'])]);
    }

    public function grades(Request $r, Siswa $siswa)
    {
        return response()->json(['data' => app(SchoolRecordWriter::class)->saveGrades($r, $siswa)]);
    }

    public function attendance(Request $r, Siswa $siswa)
    {
        $d = $r->validate(['semester' => 'required|integer|between:1,5', 'jumlah_hadir' => 'required|integer|between:0,366', 'jumlah_sakit' => 'required|integer|between:0,366', 'jumlah_izin' => 'required|integer|between:0,366', 'jumlah_alpa' => 'required|integer|between:0,366', 'persentase_kehadiran' => 'prohibited']);
        $total = array_sum(array_intersect_key($d, array_flip(['jumlah_hadir', 'jumlah_sakit', 'jumlah_izin', 'jumlah_alpa'])));
        abort_unless($total > 0 && $total <= 366, 422);
        $d['persentase_kehadiran'] = round($d['jumlah_hadir'] / $total * 100, 2);

        return response()->json(['data' => RekapPresensi::updateOrCreate(['siswa_id' => $siswa->id, 'semester' => $d['semester']], $d)]);
    }

    private function definition(string $type): array
    {
        $types = [
            'karakter' => [CatatanKarakter::class, ['semester' => 'required|integer|between:1,5', 'jenis_catatan' => 'required|string|max:100', 'poin_disiplin' => 'required|integer|between:-10000,10000', 'deskripsi' => 'nullable|string|max:2000', 'tanggal_pencatatan' => 'required|date|before_or_equal:now']],
            'organisasi' => [RiwayatOrganisasi::class, ['jenis_organisasi' => 'required|string|max:100', 'nama_organisasi' => 'required|string|max:255', 'jabatan' => 'nullable|string|max:100', 'bobot_jabatan' => 'nullable|numeric|between:0,100', 'periode_jabatan' => 'nullable|string|max:50']],
            'saudara' => [SaudaraTiri::class, ['nama' => 'required|string|max:100', 'jenis_kelamin' => 'required|in:L,P', 'hubungan' => 'required|string|max:50', 'usia' => 'nullable|integer|between:0,120', 'keterangan' => 'nullable|string|max:255']],
            'prestasi' => [PrestasiMurid::class, ['jenis_prestasi' => 'required|string|max:100', 'nama_kompetisi' => 'required|string|max:255', 'bidang' => 'nullable|string|max:100', 'tingkat' => 'nullable|in:Sekolah,Kabupaten,Kota,Provinsi,Nasional,Internasional', 'peringkat' => 'nullable|string|max:50', 'bobot_persentase' => 'prohibited', 'status_verifikasi' => 'required|in:pending,terverifikasi,ditolak', 'penyelenggara' => 'nullable|string|max:255', 'tahun' => 'nullable|integer|between:1900,2100', 'attachment_id' => 'nullable|integer']],
        ];
        abort_unless(isset($types[$type]), 404);

        return $types[$type];
    }

    public function record(Request $r, Siswa $siswa, string $type, ?int $id = null)
    {
        [$model,$rules] = $this->definition($type);
        $d = $r->validate($rules);
        if (isset($d['attachment_id'])) {
            abort_unless(DB::table('private_attachments')->where('id', $d['attachment_id'])->where('siswa_id', $siswa->id)->exists(), 422);
        }
        $record = $id ? $model::where('siswa_id', $siswa->id)->findOrFail($id) : new $model;
        $record->forceFill([...$d, 'siswa_id' => $siswa->id])->save();

        return response()->json(['data' => $record], $id ? 200 : 201);
    }

    public function destroy(Siswa $siswa, string $type, int $id)
    {
        [$model] = $this->definition($type);
        $model::where('siswa_id', $siswa->id)->findOrFail($id)->delete();

        return response()->json(['message' => 'Data dihapus.']);
    }

    public function completeness(Request $r, Siswa $siswa)
    {
        $d = $r->validate(['bantuan_dikonfirmasi' => 'sometimes|boolean', 'prestasi_dikonfirmasi' => 'sometimes|boolean', 'organisasi_dikonfirmasi' => 'sometimes|boolean', 'karakter_dikonfirmasi' => 'sometimes|boolean']);
        abort_unless(count($d), 422);
        $siswa->forceFill($d)->save();

        return response()->json(['message' => 'Kelengkapan data diperbarui.']);
    }

    public function classroom(Request $r, Siswa $siswa)
    {
        $d = $r->validate(['kelas_id' => 'required|integer|exists:kelas,id', 'tanggal_mulai' => 'required|date|before_or_equal:today', 'keterangan' => 'nullable|string|max:255']);
        $record = DB::transaction(function () use ($siswa, $d) {
            $s = Siswa::whereKey($siswa->id)->lockForUpdate()->firstOrFail();
            $old = $s->riwayatKelas()->whereNull('tanggal_selesai')->get();
            foreach ($old as $row) {
                abort_unless(Carbon::parse($d['tanggal_mulai'])->gt($row->tanggal_mulai), 422);
                $row->update(['tanggal_selesai' => Carbon::parse($d['tanggal_mulai'])->subDay()]);
            }

            return $s->riwayatKelas()->create($d);
        });

        return response()->json(['data' => $record], 201);
    }

    public function interests(Request $r, Siswa $siswa)
    {
        $d = $r->validate(['hobi' => 'present|array|max:20', 'hobi.*' => 'integer|distinct|exists:hobi,id', 'cita_cita' => 'present|array|max:20', 'cita_cita.*' => 'integer|distinct|exists:cita_cita,id']);
        DB::transaction(function () use ($siswa, $d) {
            $siswa->hobi()->sync($d['hobi']);
            $siswa->citaCita()->sync(collect($d['cita_cita'])->mapWithKeys(fn ($id, $index) => [$id => ['prioritas' => $index + 1]])->all());
        });

        return response()->json(['message' => 'Minat diperbarui.']);
    }
}
