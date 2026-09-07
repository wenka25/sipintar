<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LaporanTypeMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_question_value_is_migrated_without_changing_related_data(): void
    {
        // RefreshDatabase has already run this migration, so recreate the legacy
        // MySQL enum before inserting a row that represents the old data.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE laporan MODIFY tipe ENUM('pengaduan', 'pertanyaan', 'aspirasi', 'permintaan_informasi') NOT NULL");
        }

        $report = DB::table('laporan')->insertGetId([
            'kode_tiket' => 'TYPE-MIGRATION-1',
            'pelapor_id' => DB::table('pelapor')->insertGetId([
                'nama' => 'Migrasi Test',
                'is_anonim' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]),
            'tipe' => 'pertanyaan',
            'kategori_id' => DB::table('kategori')->insertGetId([
                'nama' => 'Migrasi',
                'created_at' => now(),
                'updated_at' => now(),
            ]),
            'judul' => 'Laporan migrasi',
            'deskripsi' => 'Data lama',
            'status' => 'baru',
            'sumber' => 'app',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('status_logs')->insert([
            'laporan_id' => $report,
            'status_baru' => 'baru',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require base_path('database/migrations/2026_09_07_000_migrate_laporan_tipe_to_new_values.php');
        $migration->up();

        $this->assertDatabaseHas('laporan', [
            'id' => $report,
            'kode_tiket' => 'TYPE-MIGRATION-1',
            'tipe' => 'permintaan_informasi',
        ]);
        $this->assertDatabaseHas('status_logs', ['laporan_id' => $report]);
    }
}
