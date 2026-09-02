<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pelapor', function (Blueprint $table) {
            $table->id();

            $table->string('nama')
                ->nullable();

            $table->string('kontak')
                ->nullable();

            $table->boolean('is_anonim')
                ->default(true);

            $table->foreignId('user_account_id')
                ->nullable()
                ->constrained('akun_warga')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pelapor');
    }
};