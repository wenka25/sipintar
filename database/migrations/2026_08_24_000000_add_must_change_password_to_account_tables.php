<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// TASK A: flag force-change-password untuk mekanisme reset password via Admin.
// Admin menandai akun dengan must_change_password = true saat membuat password
// sementara. User wajib mengganti password sebelum bisa memakai aplikasi normal.
// Default false sehingga user yang login normal tidak terpengaruh.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('password');
        });

        Schema::table('akun_warga', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });

        Schema::table('akun_warga', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
    }
};
