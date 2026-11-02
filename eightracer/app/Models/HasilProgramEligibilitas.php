<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HasilProgramEligibilitas extends Model
{
    protected $table = 'hasil_program_eligibilitas';

    protected $guarded = [];

    protected $hidden = [];

    protected function casts(): array
    {
        return ['evaluasi' => 'array', 'skor' => 'decimal:4'];
    }
}
