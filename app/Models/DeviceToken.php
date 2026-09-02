<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceToken extends Model
{
    protected $table = 'device_tokens';

    protected $fillable = [
        'pelapor_id',
        'token',
        'token_hash',
        'platform',
        'is_active',
        'last_seen_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public function pelapor(): BelongsTo
    {
        return $this->belongsTo(
            Pelapor::class,
            'pelapor_id'
        );
    }
}