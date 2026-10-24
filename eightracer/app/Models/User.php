<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'users';

    protected $fillable = [
        'username',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function administrator(): HasOne
    {
        return $this->hasOne(Administrator::class, 'user_id');
    }

    public function guru(): HasOne
    {
        return $this->hasOne(Guru::class, 'user_id');
    }

    public function siswa(): HasOne
    {
        return $this->hasOne(Siswa::class, 'user_id');
    }
}
