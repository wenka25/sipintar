<?php

namespace App\Services;

use App\Models\Laporan;
use Illuminate\Support\Str;

class TicketService
{
    public function generate(): string
    {
        do {
            $kode = 'DPK-' . now()->format('Ymd') . '-' . strtoupper(
                Str::random(6)
            );
        } while (
            Laporan::where('kode_tiket', $kode)->exists()
        );

        return $kode;
    }
}