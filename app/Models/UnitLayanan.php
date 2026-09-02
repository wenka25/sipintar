<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitLayanan extends Model
{
    protected $table = 'unit_layanan';
    protected $fillable = ['nama', 'kode', 'deskripsi', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function laporan(): HasMany
    {
        return $this->hasMany(Laporan::class, 'unit_layanan_id');
    }
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_unit_layanan');
    }
}
