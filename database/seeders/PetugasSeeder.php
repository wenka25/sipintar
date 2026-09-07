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
            // Petugas Perpustakaan
            'petugas.perpus@gmail.com' => [
                'name' => 'Petugas Perpustakaan',
                'kode' => 'PERPUS_UM',
            ],
            // Petugas Kearsipan
            'petugas.arsip@gmail.com' => [
                'name' => 'Petugas Kearsipan',
                'kode' => 'ARSIP_UM',
            ],
            // Petugas Umum
            'petugas.layanan@gmail.com' => [
                'name' => 'Petugas Umum',
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
