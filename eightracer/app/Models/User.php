<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, \Illuminate\Notifications\Notifiable;

    protected $table = 'users';

    protected $fillable = ['email', 'full_name',
        'username',
        'password',
        'role',
    ];

    protected $hidden = [
        'password', 'remember_token', 'email', 'email_verified_at', 'google_id', 'photo_path',
    ];

    protected function casts(): array
    {
        return ['google_id' => 'string',
            'password' => 'hashed', 'email_verified_at' => 'datetime',
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
