<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublishedCohortSummary extends Model
{
    protected $table = 'published_cohort_summaries';

    protected $guarded = [];

    protected $hidden = ['member_siswa_ids'];

    protected function casts(): array
    {
        return ['summary' => 'array', 'member_siswa_ids' => 'array', 'is_published' => 'boolean'];
    }
}
