<?php

namespace App\Console\Commands;

use App\Models\BasisDataBeasiswa;
use App\Models\Siswa;
use App\Services\Beasiswa\TfidfScholarshipModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ScholarshipMlReadiness extends Command
{
    protected $signature = 'ml:beasiswa-check {--json : Tampilkan metadata agregat dalam JSON}';

    protected $description = 'Periksa kesiapan data ML beasiswa tanpa mengirim data atau menulis rekomendasi';

    public function handle(TfidfScholarshipModel $model): int
    {
        $catalog = BasisDataBeasiswa::where('is_active', true)->where(fn ($q) => $q->whereNull('tanggal_tutup')->orWhereDate('tanggal_tutup', '>=', today()))
            ->orderBy('id')->limit(TfidfScholarshipModel::MAX_DOCUMENTS + 1)->pluck('deskripsi', 'id')->all();
        $trained = $model->fit($catalog);
        $profiles = Siswa::where(fn ($q) => $q->whereHas('hobi')->orWhereHas('citaCita')->orWhereHas('prestasiMurid', fn ($p) => $p->where('status_verifikasi', 'terverifikasi')->whereNotNull('bidang')))->count();
        $data = ['metode' => TfidfScholarshipModel::VERSION, 'status_korpus' => $trained['status'],
            'deskripsi_dapat_dipelajari' => $trained['documents'], 'jumlah_fitur' => count($trained['idf']),
            'profil_dengan_sumber_minat_terstruktur' => $profiles,
            'catatan_beasiswa_alumni' => DB::table('alumni_progress_beasiswa')->count(),
            'mutu_rekomendasi_tervalidasi' => false,
            'catatan' => 'Kesiapan struktur bukan bukti akurasi. Uji relevansi bersama BK pada data terpisah sebelum menyatakan peningkatan mutu. Tidak ada prediksi peluang lolos.'];
        $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
