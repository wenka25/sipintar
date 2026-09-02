<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kategori extends Model
{
    protected $table = 'kategori';

    protected $fillable = [
        'nama',
    ];

    public function laporan(): HasMany
    {
        return $this->hasMany(
            Laporan::class,
            'kategori_id'
        );
    }
}