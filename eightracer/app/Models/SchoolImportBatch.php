<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolImportBatch extends Model
{
    protected $table = 'school_import_batches';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['plan'];

    protected function casts(): array
    {
        return ['plan' => 'encrypted:array', 'summary' => 'array', 'receipt' => 'array', 'expires_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
