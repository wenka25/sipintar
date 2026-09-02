<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\UnitLayanan;

class UnitLayananSeeder extends Seeder
{
    public function run(): void
    {
        foreach (
            [
                ['kode' => 'PERPUS_UM', 'nama' => 'Perpustakaan Umum'],
                ['kode' => 'ARSIP_UM', 'nama' => 'Kearsipan Umum'],
                ['kode' => 'LAYAN_PERPUS', 'nama' => 'Layanan Perpustakaan'],
            ] as $unit
        ) {
            UnitLayanan::updateOrCreate(['kode' => $unit['kode']], $unit + ['is_active' => true]);
        }
    }
}
