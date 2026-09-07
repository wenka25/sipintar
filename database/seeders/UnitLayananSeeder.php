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
                ['kode' => 'PERPUS_UM', 'nama' => 'Perpustakaan'],
                ['kode' => 'ARSIP_UM', 'nama' => 'Kearsipan'],
                ['kode' => 'LAYAN_PERPUS', 'nama' => 'Umum'],
            ] as $unit
        ) {
            UnitLayanan::updateOrCreate(['kode' => $unit['kode']], $unit + ['is_active' => true]);
        }
    }
}
