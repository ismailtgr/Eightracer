<?php

namespace App\Services\Beasiswa;

/** Learns vocabulary and IDF from catalog documents; no student outcomes or identities. */
class TfidfScholarshipModel
{
    public const VERSION = 'tfidf-cosine-v1';

    public const MAX_DOCUMENTS = 500;

    private const STOP_WORDS = ['dan', 'atau', 'yang', 'untuk', 'dengan', 'dari', 'pada', 'dalam', 'adalah', 'ini', 'itu', 'oleh', 'sebagai', 'akan', 'dapat', 'bagi', 'para', 'serta', 'the', 'and', 'for', 'with', 'beasiswa', 'program', 'siswa', 'mahasiswa', 'sekolah', 'kuliah', 'sma', 'peserta', 'pendaftaran', 'penerima'];

    public function fit(array $documents): array
    {
        ksort($documents, SORT_NUMERIC);
        if (count($documents) > self::MAX_DOCUMENTS) {
            return ['status' => 'catalog_limit', 'idf' => [], 'vectors' => [], 'documents' => 0, 'hash' => null];
        }
        $counts = $frequencies = [];
        foreach ($documents as $id => $document) {
            $terms = $this->counts((string) $document);
            if ($terms === []) {
                continue;
            }
            $counts[$id] = $terms;
            foreach (array_keys($terms) as $term) {
                $frequencies[$term] = ($frequencies[$term] ?? 0) + 1;
            }
        }
        $n = count($counts);
        $idf = $vectors = [];
        if ($n >= 2) {
            foreach ($frequencies as $term => $df) {
                $idf[$term] = log((1 + $n) / (1 + $df)) + 1;
            }
            ksort($idf);
            foreach ($counts as $id => $terms) {
                $vectors[$id] = $this->vector($terms, $idf);
            }
        }

        return ['status' => $n >= 2 ? 'ready' : 'catalog_insufficient', 'idf' => $idf, 'vectors' => $vectors,
            'documents' => $n, 'hash' => hash('sha256', json_encode([self::VERSION, $counts], JSON_THROW_ON_ERROR))];
    }

    public function similarities(array $model, string $query): array
    {
        $q = $this->vector($this->counts($query), $model['idf']);
        if ($q === []) {
            return [];
        }
        $scores = [];
        foreach ($model['vectors'] as $id => $vector) {
            $dot = 0.0;
            foreach ($q as $term => $weight) {
                $dot += $weight * ($vector[$term] ?? 0);
            }
            $scores[$id] = round(max(0.0, min(1.0, $dot)), 8);
        }

        return $scores;
    }

    private function counts(string $text): array
    {
        $text = mb_strtolower(strip_tags(mb_substr($text, 0, 12000)), 'UTF-8');
        $text = preg_replace('~https?://\S+|\S+@\S+~u', ' ', $text) ?? '';
        preg_match_all('/\p{L}{3,32}/u', $text, $matches);
        $words = array_slice(array_values(array_filter($matches[0], fn ($w) => ! in_array($w, self::STOP_WORDS, true))), 0, 256);
        $terms = $words;
        for ($i = 1; $i < count($words); $i++) {
            $terms[] = $words[$i - 1].' '.$words[$i];
        }
        $counts = array_count_values($terms);
        ksort($counts);

        return $counts;
    }

    private function vector(array $counts, array $idf): array
    {
        $vector = [];
        $norm = 0.0;
        foreach ($counts as $term => $count) {
            if (! isset($idf[$term])) {
                continue;
            }
            $value = (1 + log($count)) * $idf[$term];
            $vector[$term] = $value;
            $norm += $value * $value;
        }
        $norm = sqrt($norm);
        foreach ($vector as $term => $value) {
            $vector[$term] = $value / $norm;
        }

        return $vector;
    }
}
