<?php

namespace App\Services\Beasiswa;

/** Catalog retrieval is distinct from personal eligibility and never writes student data. */
class ScholarshipGeneralGuidance
{
    public function __construct(private TfidfScholarshipModel $model) {}

    public function hasAssessmentData(array $profile): bool
    {
        foreach (['nilai_tka', 'rata_rapor', 'rata_rapor_kelas_12', 'penghasilan_orang_tua',
            'persentase_kehadiran', 'jumlah_prestasi', 'jumlah_organisasi', 'poin_karakter',
            'ipk', 'semester_kuliah', 'status_eligible'] as $field) {
            if (array_key_exists($field, $profile) && $profile[$field] !== null && $profile[$field] !== '') return true;
        }
        return trim((string) ($profile['ml_interest_document'] ?? '')) !== '';
    }

    public function answer(array $programs, string $question, ?int $selectedId = null, bool $useMl = true): array
    {
        $documents = [];
        foreach ($programs as $program) {
            $documents[$program['id']] = implode(' ', array_filter([
                $program['nama_beasiswa'] ?? '', $program['penyelenggara'] ?? '',
                $program['deskripsi'] ?? '',
                implode(' ', array_column($program['kriteria_kelayakan_beasiswa'] ?? [], 'nama_kriteria')),
            ]));
        }
        $trained = $useMl ? $this->model->fit($documents) : ['status' => 'disabled'];
        $scores = $trained['status'] === 'ready' ? $this->model->similarities($trained, $question) : [];
        if ($selectedId !== null) {
            $programs = array_values(array_filter($programs, fn ($p) => (int) $p['id'] === $selectedId));
        } else {
            usort($programs, fn ($a, $b) => (($scores[$b['id']] ?? 0) <=> ($scores[$a['id']] ?? 0))
                ?: strcasecmp($a['nama_beasiswa'], $b['nama_beasiswa']) ?: ($a['id'] <=> $b['id']));
        }
        $programs = array_slice($programs, 0, 5);
        $parts = ["Bisa, aku bantu! 👋\n\nData profilmu belum cukup untuk penilaian pribadi. Berikut informasi dari katalog beasiswa yang dapat kamu pelajari."];
        $sources = $suggestions = [];
        foreach ($programs as $p) {
            $name = $this->plain($p['nama_beasiswa']);
            $criteria = array_map(fn ($c) => '- '.$this->plain($c['nama_kriteria'] ?? 'Kriteria').': '
                .$this->plain($c['operator'] ?? '').' '.$this->plain($c['nilai_kriteria'] ?? ''), $p['kriteria_kelayakan_beasiswa'] ?? []);
            $url = $this->officialUrl($p['link_pendaftaran'] ?? null);
            $parts[] = '**'.$name."**\n\n**Penyelenggara:** ".($this->plain($p['penyelenggara'] ?? '') ?: 'Belum tersedia di katalog.')
                ."\n\n**Sasaran dan Informasi Program:**\nJenjang: ".$this->plain($p['jenjang'] ?? '')
                .".\n".($this->plain($p['deskripsi'] ?? '') ?: 'Informasi sasaran dan manfaat belum tersedia di katalog.')
                ."\n\n**Persyaratan:**\n".($criteria ? implode("\n", $criteria) : 'Rincian persyaratan belum tersedia; periksa pengumuman resmi.')
                ."\n\n**Jadwal Pendaftaran:**\n- Dibuka: ".$this->date($p['tanggal_buka'] ?? null)
                ."\n- Ditutup: ".$this->date($p['tanggal_tutup'] ?? null)
                ."\n\n**Cara Mendaftar:**\n- Periksa persyaratan, manfaat, dokumen, dan tahapan seleksi pada pengumuman resmi."
                ."\n- Ikuti petunjuk pendaftaran penyelenggara; tahapan yang belum tercatat tidak dapat dipastikan."
                .($url ? "\n- Tautan penyelenggara: ".$url : "\n- Tautan pendaftaran belum tersedia di katalog.");
            $sources[] = ['id' => 'catalog:'.$p['id'], 'label' => $name, 'url' => $url];
            $suggestions[] = array_intersect_key($p, array_flip(['id', 'nama_beasiswa', 'jenjang']));
        }
        if (!$programs) $parts[] = 'Belum ada program aktif yang dapat ditampilkan dari katalog.';
        $parts[] = 'Pilihan ini berdasarkan informasi program dan pertanyaanmu, bukan hasil penilaian kecocokan atau peluang lolos. Jika mau, ceritakan jenjang tujuan atau minatmu agar pembahasan lebih terarah.';
        return ['answer' => implode("\n\n", $parts), 'status' => 'general_information', 'llm_used' => false,
            'beasiswa_id' => $selectedId, 'suggestions' => $suggestions, 'sources' => $sources,
            'candidates' => [], 'ranking' => ['status' => 'general_information', 'metode' => TfidfScholarshipModel::VERSION,
                'alasan_status' => 'profile_insufficient', 'scope' => 'question_catalog_relevance'],
            'context' => 'Informasi umum beasiswa'];
    }

    private function plain(mixed $value): string
    {
        return trim(str_replace(['*', '[', ']', '`'], '', strip_tags((string) $value)));
    }

    private function date(?string $value): string
    {
        if (!$value || !preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) return 'Jadwal belum diumumkan di katalog.';
        return implode('-', array_reverse(explode('-', substr($value, 0, 10))));
    }

    private function officialUrl(mixed $value): ?string
    {
        $parts = is_string($value) ? parse_url($value) : false;
        return $parts && ($parts['scheme'] ?? '') === 'https' && !empty($parts['host'])
            && !isset($parts['user']) && !isset($parts['pass']) ? $value : null;
    }
}
