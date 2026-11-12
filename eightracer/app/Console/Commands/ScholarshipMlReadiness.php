<?php

namespace App\Console\Commands;

use App\Models\BasisDataBeasiswa;
use App\Models\Siswa;
use App\Services\Beasiswa\TfidfScholarshipModel;
use App\Services\Beasiswa\ScholarshipSchedule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ScholarshipMlReadiness extends Command
{
    protected $signature = 'ml:beasiswa-check {--json : Tampilkan metadata agregat dalam JSON}';

    protected $description = 'Periksa kesiapan data ML beasiswa tanpa mengirim data atau menulis rekomendasi';

    public function handle(TfidfScholarshipModel $model): int
    {
        $catalog = BasisDataBeasiswa::where('is_active', true)->where(fn ($q) => $q->whereNull('tanggal_tutup')->orWhereDate('tanggal_tutup', '>=', ScholarshipSchedule::today()))
            ->orderBy('id')->limit(TfidfScholarshipModel::MAX_DOCUMENTS + 1)->pluck('deskripsi', 'id')->all();
        $trained = $model->fit($catalog);
        $reference = $model->fit(BasisDataBeasiswa::where('is_active', true)->orderBy('id')->limit(TfidfScholarshipModel::MAX_DOCUMENTS + 1)->pluck('deskripsi', 'id')->all());
        $profiles = Siswa::where(fn ($q) => $q->whereHas('hobi')->orWhereHas('citaCita')->orWhereHas('prestasiMurid', fn ($p) => $p->where('status_verifikasi', 'terverifikasi')->whereNotNull('bidang')))->count();
        $data = ['metode' => TfidfScholarshipModel::VERSION, 'status_korpus' => $trained['status'],
            'deskripsi_dapat_dipelajari' => $trained['documents'], 'jumlah_fitur' => count($trained['idf']),
            'profil_dengan_sumber_minat_terstruktur' => $profiles,
            'catatan_beasiswa_alumni' => DB::table('alumni_progress_beasiswa')->count(),
            'program_aktif_belum_tutup' => count($catalog),
            'korpus_referensi' => ['status' => $reference['status'], 'dokumen' => $reference['documents'], 'fitur' => count($reference['idf']), 'catatan' => 'Termasuk periode yang sudah tutup; hanya untuk evaluasi referensi, bukan pendaftaran aktif.'],
            'mutu_rekomendasi_tervalidasi' => false,
            'catatan' => 'Kesiapan struktur bukan bukti akurasi. Uji relevansi memakai rubrik berlabel pada data terpisah sebelum menyatakan peningkatan mutu. Tidak ada prediksi peluang lolos.'];
        $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
