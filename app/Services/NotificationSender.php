<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\Laporan;
use App\Models\Pelapor;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Exception\Messaging\InvalidArgument;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Throwable;

class NotificationSender
{
    public function __construct(
        private readonly FirebaseService $firebaseService
    ) {
    }

    public function sendToPelapor(
        Pelapor $pelapor,
        string $title,
        string $body,
        array $data,
        int $laporanId
    ): void {
        $pelapor->deviceTokens()
            ->where('is_active', true)
            ->get()
            ->each(function (DeviceToken $deviceToken) use (
                $title,
                $body,
                $data,
                $laporanId
            ): void {
                $this->sendToDevice(
                    $deviceToken,
                    $title,
                    $body,
                    $data,
                    $laporanId
                );
            });
    }

    public function sendToLaporan(
        Laporan $laporan,
        string $title,
        string $body,
        array $data
    ): void {
        $reportTokens = $laporan->deviceTokens()
            ->where('is_active', true)
            ->get();
        $pelaporTokens = $laporan->pelapor
            ? $laporan->pelapor->deviceTokens()->where('is_active', true)->get()
            : collect();

        $reportTokens->merge($pelaporTokens)
            ->unique('id')
            ->each(fn (DeviceToken $deviceToken) => $this->sendToDevice(
                $deviceToken,
                $title,
                $body,
                $data,
                $laporan->id
            ));
    }

    public function sendToDevice(
        DeviceToken $deviceToken,
        string $title,
        string $body,
        array $data,
        int $laporanId
    ): void {
        try {
            $this->firebaseService->sendNotification(
                $deviceToken->token,
                $title,
                $body,
                $data
            );
        } catch (NotFound|InvalidArgument $exception) {
            $deviceToken->update([
                'is_active' => false,
            ]);

            Log::warning('FCM token tidak terdaftar.', [
                'device_token_id' => $deviceToken->id,
                'laporan_id' => $laporanId,
            ]);
        } catch (Throwable $exception) {
            Log::error('Pengiriman notifikasi FCM gagal.', [
                'device_token_id' => $deviceToken->id,
                'laporan_id' => $laporanId,
                'exception' => $exception,
            ]);
        }
    }
}
