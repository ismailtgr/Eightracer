<?php
namespace App\Services\Beasiswa;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Builder;
class ActiveScholarshipStudents {
 public function restrict(Builder $query): Builder {
  $graduatedThrough=now()->month>=7?now()->year:now()->year-1;
  return $query->where(fn($q)=>$q->whereNull('tanggal_lulus')->orWhereDate('tanggal_lulus','>',today()))
   ->where(fn($q)=>$q->whereNull('tahun_lulus')->orWhere('tahun_lulus','>',$graduatedThrough))
   ->whereHas('riwayatKelas',fn($q)=>$q->whereDate('tanggal_mulai','<=',today())
    ->where(fn($c)=>$c->whereNull('tanggal_selesai')->orWhereDate('tanggal_selesai','>',today()))
    ->whereHas('kelas',fn($k)=>$k->whereIn('tingkat',[10,11,12])->whereIn('tahun_ajaran',[$graduatedThrough.'/'.($graduatedThrough+1),$graduatedThrough.'-'.($graduatedThrough+1)])));
 }
 public function assertActive(Siswa $student): void {
  if(! $this->restrict(Siswa::query()->whereKey($student->id))->exists()) throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->json(['message'=>'Rekomendasi beasiswa hanya tersedia untuk siswa aktif SMA 8. Periksa status kelulusan dan riwayat kelas.'],422));
 }
}
