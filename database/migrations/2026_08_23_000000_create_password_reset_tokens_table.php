<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tabel standar Laravel untuk token reset password. Dipakai oleh fitur
// "Lupa Password" untuk admin/petugas (users) maupun warga (akun_warga),
// karena email dijamin unik di kedua tabel.
// Kolom `token` menyimpan HASH kode OTP (bukan nilai mentah).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
    }
};
