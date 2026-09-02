<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Laporan;
use App\Models\User;

class Balasan extends Model
{
    protected $table = 'balasan';

    protected $fillable = [
        'laporan_id',
        'isi_balasan',
        'staf_id',
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
            'staf_id'
        );
    }
}