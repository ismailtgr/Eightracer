<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BukuAlumni extends Model
{
    protected $table = 'buku_alumni';

    public $timestamps = false;

    protected $fillable = [
        'tahun_lulusan',
        'judul_buku',
        'file_pdf_url',
        'thumbnail_cover_url',
        'is_published',
        'tanggal_rilis',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'tanggal_rilis' => 'date:Y-m-d',
        ];
    }

    public function studyTracer(): HasMany
    {
        return $this->hasMany(StudyTracer::class, 'buku_alumni_id');
    }
}
