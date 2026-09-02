<?php

namespace App\Services;

use App\Models\Laporan;
use Illuminate\Support\Facades\DB;

class StatusTransitionService
{
    public function transition(Laporan $laporan, string $status, ?string $catatan, ?int $userId): array
    {
        return DB::transaction(function () use ($laporan, $status, $catatan, $userId): array {
            $oldStatus = $laporan->status;
            $laporan->update(['status' => $status]);
            $log = $laporan->statusLogs()->create([
                'status_lama' => $oldStatus,
                'status_baru' => $status,
                'catatan' => $catatan,
                'diubah_oleh' => $userId,
            ]);
            return [$oldStatus, $log];
        });
    }
}
