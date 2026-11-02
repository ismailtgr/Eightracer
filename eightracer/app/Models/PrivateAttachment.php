<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrivateAttachment extends Model
{
    protected $table = 'private_attachments';

    protected $guarded = [];

    protected $hidden = ['path'];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }
}
