<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Kategori;
use App\Models\Pelapor;
use App\Models\Lampiran;
use App\Models\StatusLog;
use App\Models\Balasan;
use App\Models\DeviceToken;
use App\Models\UnitLayanan;

class Laporan extends Model
{
    protected $table = 'laporan';

    protected $fillable = [
        'kode_tiket',
        'pelapor_id',
        'pelapor_nama',
        'pelapor_kontak',
        'is_anonim',
        'unit_layanan_id',
        'tipe',
        'kategori_id',
        'judul',
        'deskripsi',
        'status',
        'sumber',
        'cabang_perpustakaan',
        'dibuat_oleh_staf_id',
    ];

    protected $casts = [
        'is_anonim' => 'boolean',
    ];

    public function pelapor(): BelongsTo
    {
        return $this->belongsTo(
            Pelapor::class,
            'pelapor_id'
        );
    }

    public function unitLayanan(): BelongsTo
    {
        return $this->belongsTo(UnitLayanan::class, 'unit_layanan_id');
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(
            Kategori::class,
            'kategori_id'
        );
    }

    public function staf(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'dibuat_oleh_staf_id'
        );
    }

    public function lampiran(): HasMany
    {
        return $this->hasMany(
            Lampiran::class,
            'laporan_id'
        );
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(
            StatusLog::class,
            'laporan_id'
        );
    }

    public function balasan(): HasMany
    {
        return $this->hasMany(
            Balasan::class,
            'laporan_id'
        );
    }

    public function deviceTokens(): BelongsToMany
    {
        return $this->belongsToMany(
            DeviceToken::class,
            'laporan_device_tokens',
            'laporan_id',
            'device_token_id'
        )->withTimestamps();
    }
}
