<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Kategori;

class KategoriSeeder extends Seeder
{
    public function run(): void
    {
        $kategori = [
            'Fasilitas',
            'Pelayanan',
            'Koleksi Buku',
            'Program/Kegiatan',
            'Lainnya',
        ];

        foreach ($kategori as $nama) {
            Kategori::updateOrCreate(
                ['nama' => $nama],
                ['nama' => $nama],
            );
        }
    }
}