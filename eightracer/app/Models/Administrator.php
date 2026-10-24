<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Administrator extends Model
{
    protected $table = 'administrator';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'nip',
        'nama_lengkap',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
