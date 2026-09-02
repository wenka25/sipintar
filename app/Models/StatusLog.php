<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatusLog extends Model
{
    protected $table = 'status_logs';

    protected $fillable = [
        'laporan_id',
        'status_lama',
        'status_baru',
        'catatan',
        'diubah_oleh',
    ];

    public function laporan(): BelongsTo
    {
        return $this->belongsTo(
            Laporan::class,
            'laporan_id'
        );
    }

    public function staf(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'diubah_oleh'
        );
    }
}