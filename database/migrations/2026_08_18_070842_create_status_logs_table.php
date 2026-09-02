<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('laporan_id')
                ->constrained('laporan')
                ->cascadeOnDelete();

            $table->string('status_lama')
                ->nullable();

            $table->string('status_baru');

            $table->text('catatan')
                ->nullable();

            $table->foreignId('diubah_oleh')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('laporan_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_logs');
    }
};