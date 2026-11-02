<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlumniProgressBeasiswa extends Model
{
    protected $table = 'alumni_progress_beasiswa';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['beasiswa_snapshot' => 'array', 'nominal_beasiswa' => 'decimal:2', 'nominal_ukt' => 'decimal:2'];
    }

    public function alumniProgress()
    {
        return $this->belongsTo(AlumniProgress::class, 'alumni_progress_id');
    }

    public function beasiswa()
    {
        return $this->belongsTo(BasisDataBeasiswa::class, 'beasiswa_id');
    }
}
