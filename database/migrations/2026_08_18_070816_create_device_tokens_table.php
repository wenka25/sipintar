<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('pelapor_id')
                ->nullable()
                ->constrained('pelapor')
                ->cascadeOnDelete();

            $table->string('token', 512)
                ;

            $table->char('token_hash', 64)
                ->unique();

            $table->string('platform')
                ->default('android');

            $table->boolean('is_active')
                ->default(true);

            $table->timestamp('last_seen_at')
                ->nullable();

            $table->timestamps();

            $table->index('pelapor_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};