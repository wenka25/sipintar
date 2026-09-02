<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pelapor extends Model
{
    protected $table = 'pelapor';

    protected $fillable = [
        'nama',
        'kontak',
        'is_anonim',
        'user_account_id',
    ];

    protected $casts = [
        'is_anonim' => 'boolean',
    ];

    public function akunWarga(): BelongsTo
    {
        return $this->belongsTo(
            AkunWarga::class,
            'user_account_id'
        );
    }

    public function laporan(): HasMany
    {
        return $this->hasMany(Laporan::class, 'pelapor_id');
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class, 'pelapor_id');
    }
}