<?php

namespace Database\Seeders;

use App\Models\UnitLayanan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PetugasSeeder extends Seeder
{
    public function run(): void
    {
        $assignments = [
            // Email untuk Petugas Perpustakaan Umum
            'petugas.perpus@gmail.com' => [
                'name' => 'Petugas Perpustakaan',
                'kode' => 'PERPUS_UM',
            ],
            // Email untuk Petugas Kearsipan Umum
            'petugas.arsip@gmail.com' => [
                'name' => 'Petugas Kearsipan',
                'kode' => 'ARSIP_UM',
            ],
            // Email untuk Petugas Layanan Perpustakaan
            'petugas.layanan@gmail.com' => [
                'name' => 'Petugas Layanan',
                'kode' => 'LAYAN_PERPUS',
            ],
        ];

        foreach ($assignments as $email => $assignment) {
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $assignment['name'],
                    'password' => Hash::make('Petugas@12345'), // Password yang sama untuk semua
                    'role' => 'petugas',
                ],
            );

            $unit = UnitLayanan::where('kode', $assignment['kode'])->first();
            if ($unit) {
                $user->unitLayanan()->syncWithoutDetaching([$unit->id]);
            }
        }
    }
}
