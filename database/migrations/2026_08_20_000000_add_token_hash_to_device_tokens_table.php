<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('device_tokens', 'token_hash')) {
            Schema::table('device_tokens', function (Blueprint $table) {
                $table->char('token_hash', 64)
                    ->nullable()
                    ->after('token');
            });
        }

        DB::table('device_tokens')
            ->whereNull('token_hash')
            ->select(['id', 'token'])
            ->orderBy('id')
            ->get()
            ->each(function (object $deviceToken): void {
                DB::table('device_tokens')
                    ->where('id', $deviceToken->id)
                    ->update([
                        'token_hash' => hash('sha256', $deviceToken->token),
                    ]);
            });

        $indexes = collect(Schema::getIndexes('device_tokens'))
            ->pluck('name')
            ->all();

        if (in_array('device_tokens_token_unique', $indexes, true)) {
            Schema::table('device_tokens', function (Blueprint $table) {
                $table->dropUnique('device_tokens_token_unique');
            });
        }

        if (!in_array('device_tokens_token_hash_unique', $indexes, true)) {
            Schema::table('device_tokens', function (Blueprint $table) {
                $table->unique('token_hash');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('device_tokens', 'token_hash')) {
            Schema::table('device_tokens', function (Blueprint $table) {
                $table->dropUnique('device_tokens_token_hash_unique');
                $table->dropColumn('token_hash');
            });
        }
    }
};