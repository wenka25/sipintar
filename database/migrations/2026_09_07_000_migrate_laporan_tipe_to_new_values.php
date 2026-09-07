<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE laporan MODIFY tipe ENUM('pengaduan', 'pertanyaan', 'aspirasi', 'permintaan_informasi') NOT NULL");
        }

        DB::table('laporan')
            ->where('tipe', 'pertanyaan')
            ->update(['tipe' => 'permintaan_informasi']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE laporan MODIFY tipe ENUM('pengaduan', 'aspirasi', 'permintaan_informasi') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::table('laporan')->where('tipe', 'aspirasi')->exists()) {
            throw new RuntimeException('Rollback tipe laporan tidak aman karena terdapat data aspirasi.');
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE laporan MODIFY tipe ENUM('pengaduan', 'pertanyaan', 'aspirasi', 'permintaan_informasi') NOT NULL");
        }

        DB::table('laporan')
            ->where('tipe', 'permintaan_informasi')
            ->update(['tipe' => 'pertanyaan']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE laporan MODIFY tipe ENUM('pengaduan', 'pertanyaan') NOT NULL");
        }
    }
};
