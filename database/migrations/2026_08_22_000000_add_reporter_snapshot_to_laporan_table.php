<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laporan', function (Blueprint $table) {
            $table->string('pelapor_nama')->nullable()->after('pelapor_id');
            $table->string('pelapor_kontak')->nullable()->after('pelapor_nama');
            $table->boolean('is_anonim')->nullable()->after('pelapor_kontak');
        });

        // Updating a joined table is not supported by SQLite, which is used
        // by the test suite. Backfill each existing report instead, preserving
        // any snapshot value that is already present.
        DB::table('laporan')
            ->join('pelapor', 'laporan.pelapor_id', '=', 'pelapor.id')
            ->where(function ($query) {
                $query->whereNull('laporan.pelapor_nama')
                    ->orWhereNull('laporan.pelapor_kontak')
                    ->orWhereNull('laporan.is_anonim');
            })
            ->orderBy('laporan.id')
            ->select([
                'laporan.id',
                'laporan.pelapor_nama',
                'laporan.pelapor_kontak',
                'laporan.is_anonim',
                'pelapor.nama as reporter_nama',
                'pelapor.kontak as reporter_kontak',
                'pelapor.is_anonim as reporter_is_anonim',
            ])
            ->each(function (object $laporan): void {
                $updates = [];

                if ($laporan->pelapor_nama === null) {
                    $updates['pelapor_nama'] = $laporan->reporter_nama;
                }
                if ($laporan->pelapor_kontak === null) {
                    $updates['pelapor_kontak'] = $laporan->reporter_kontak;
                }
                if ($laporan->is_anonim === null) {
                    $updates['is_anonim'] = $laporan->reporter_is_anonim;
                }

                if ($updates !== []) {
                    DB::table('laporan')->where('id', $laporan->id)->update($updates);
                }
            });
    }

    public function down(): void
    {
        Schema::table('laporan', function (Blueprint $table) {
            $table->dropColumn(['pelapor_nama', 'pelapor_kontak', 'is_anonim']);
        });
    }
};
