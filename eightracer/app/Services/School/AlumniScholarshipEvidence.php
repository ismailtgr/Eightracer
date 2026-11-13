<?php

namespace App\Services\School;

use App\Models\SchoolScholarshipObservation;
use App\Models\StudyTracer;

class AlumniScholarshipEvidence
{
    public function summarize(StudyTracer $admission, bool $publishedOnly = false, bool $includeSchoolSources = true): array
    {
        // Both keys matter: one alumnus may have several different PT acceptances.
        $progress = $admission->alumniProgress()->where('siswa_id', $admission->siswa_id)
            ->where('is_current', true)->when($publishedOnly, fn ($q) => $q->where('is_published', true))
            ->with('alumniProgressBeasiswa.beasiswa')->orderByRaw('tanggal_update DESC NULLS LAST')->orderByDesc('id')->get();
        $sources = $includeSchoolSources ? SchoolScholarshipObservation::where('siswa_id', $admission->siswa_id)
            ->where('study_tracer_id', $admission->id)->orderBy('id')->get() : collect();
        $programs = [];
        foreach ($progress as $row) foreach ($row->alumniProgressBeasiswa as $award) {
            $name = $award->beasiswa_snapshot['nama_beasiswa'] ?? $award->beasiswa?->nama_beasiswa;
            if (!filled($name)) continue;
            $key = $this->key($name);
            // The newest recorded status wins for a program; never turn a rejection into acceptance.
            if (!isset($programs[$key])) $programs[$key] = ['nama_beasiswa' => $name,
                'status' => $award->status_beasiswa, 'source' => 'progress', 'beasiswa_id' => $award->beasiswa_id];
        }
        foreach ($sources as $source) {
            $name = SchoolScholarshipAnalytics::sourceProgram($source);
            if (!$name && preg_match('/^BEBAS\s+IPI$/iu', trim($source->nilai_sumber))) $name = 'Bebas IPI';
            if (!$name) continue;
            $key = $this->key($name);
            if (!isset($programs[$key])) $programs[$key] = ['nama_beasiswa' => $key === 'kip' ? 'KIP-K' : $name,
                'status' => 'catatan_sekolah', 'source' => 'school_spreadsheet', 'beasiswa_id' => null];
        }
        $kip = $progress->first(fn ($row) => $row->penerima_kipk !== null)?->penerima_kipk;
        $kipSource = $kip !== null ? 'progress' : null;
        if ($kip === null && isset($programs['kip']) && in_array($programs['kip']['status'], ['diterima', 'catatan_sekolah'], true)) {
            $kip = true; $kipSource = $programs['kip']['source'];
        }
        if ($kip === null) {
            $negative = $sources->contains(fn ($row) => in_array(preg_replace('/[\s-]+/u', '', mb_strtoupper($row->nama_kolom)), ['KIP','KIPK'], true)
                && in_array(mb_strtoupper(trim($row->nilai_sumber)), ['TIDAK','BUKAN'], true));
            if ($negative) { $kip = false; $kipSource = 'school_spreadsheet'; }
        }
        $conflict = $kip === false && isset($programs['kip']) && in_array($programs['kip']['status'], ['diterima', 'catatan_sekolah'], true);
        $items = array_values($programs);
        usort($items, fn ($a, $b) => strcasecmp($a['nama_beasiswa'], $b['nama_beasiswa']));
        return ['penerima_kipk' => $kip, 'kip_source' => $kipSource, 'needs_review' => $conflict,
            'beasiswa' => $items];
    }

    private function key(string $name): string
    {
        $value = mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $name)));
        return in_array(preg_replace('/[\s\/-]+/u', '', $value), ['KIP','KIPK','KIPKIPK'], true) ? 'kip' : $value;
    }
}
