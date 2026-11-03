<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentProfileSubmission extends Model
{
    protected $table = 'student_profile_submissions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['payload', 'source_fingerprint'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'reviewed_at' => 'datetime'];
    }
}
