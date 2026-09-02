<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laporan_device_tokens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('laporan_id')
                ->constrained('laporan')
                ->cascadeOnDelete();

            $table->foreignId('device_token_id')
                ->constrained('device_tokens')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique([
                'laporan_id',
                'device_token_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_device_tokens');
    }
};