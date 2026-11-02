<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramEligibilitas extends Model
{
    protected $table = 'program_eligibilitas';

    protected $guarded = [];

    protected $hidden = [];

    protected function casts(): array
    {
        return ['bobot_prestasi' => 'array', 'bantuan_ids' => 'array', 'kriteria' => 'array', 'is_active' => 'boolean', 'version' => 'integer'];
    }
}
