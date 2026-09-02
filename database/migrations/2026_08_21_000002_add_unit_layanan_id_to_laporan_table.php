<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laporan', function (Blueprint $table) {
            $table->foreignId('unit_layanan_id')->nullable()->after('pelapor_id')->constrained('unit_layanan')->restrictOnDelete();
            $table->index(['unit_layanan_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('laporan', function (Blueprint $table) {
            $table->dropForeign(['unit_layanan_id']);
            $table->dropIndex(['unit_layanan_id', 'status']);
            $table->dropColumn('unit_layanan_id');
        });
    }
};
