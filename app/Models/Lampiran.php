<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lampiran extends Model
{
    protected $table = 'lampiran';

    protected $fillable = [
        'laporan_id',
        'url_file',
        'tipe',
        'nama_file',
    ];

    public function laporan(): BelongsTo
    {
        return $this->belongsTo(
            Laporan::class,
            'laporan_id'
        );
    }
}