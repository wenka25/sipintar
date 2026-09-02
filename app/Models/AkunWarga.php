<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tymon\JWTAuth\Contracts\JWTSubject;

class AkunWarga extends Authenticatable implements JWTSubject
{
    protected $table = 'akun_warga';

    protected $fillable = [
        'nama',
        'email',
        'no_hp',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'account_type' => 'warga',
            'role' => 'warga',
        ];
    }

    public function pelapor(): HasMany
    {
        return $this->hasMany(Pelapor::class, 'user_account_id');
    }
}