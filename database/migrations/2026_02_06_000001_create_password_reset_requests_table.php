<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_reset_requests', function (Blueprint $table) {
            $table->id();
            $table->string('identifier_type', 20); // email | kontak
            $table->string('identifier', 255);
            $table->unsignedBigInteger('requested_account_id')->nullable();
            $table->string('requested_account_type', 20)->nullable(); // staff | warga
            $table->text('message')->nullable();
            $table->string('status', 20)->default('pending'); // pending|verified|rejected|completed
            $table->text('admin_note')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['identifier_type', 'identifier', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_requests');
    }
};
