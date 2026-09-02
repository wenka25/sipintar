<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laporan', function (Blueprint $table) {
            $table->id();

            $table->string('kode_tiket')
                ->unique();

            $table->foreignId('pelapor_id')
                ->constrained('pelapor')
                ->restrictOnDelete();

            $table->enum('tipe', [
                'pengaduan',
                'pertanyaan'
            ]);

            $table->foreignId('kategori_id')
                ->constrained('kategori')
                ->restrictOnDelete();

            $table->string('judul');

            $table->text('deskripsi');

            $table->enum('status', [
                'baru',
                'diproses',
                'selesai',
                'ditolak'
            ])->default('baru');

            $table->enum('sumber', [
                'app',
                'instagram',
                'tiktok',
                'facebook',
                'x'
            ])->default('app');

            $table->string('cabang_perpustakaan')
                ->nullable();

            $table->foreignId('dibuat_oleh_staf_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('status');
            $table->index('tipe');
            $table->index('sumber');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan');
    }
};