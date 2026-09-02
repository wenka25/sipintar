<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FirebaseService
{
    protected $messaging;

    public function __construct()
    {
        $credentialsPath = $this->resolveCredentialsPath();

        $this->messaging = (new Factory)
            ->withServiceAccount($credentialsPath)
            ->createMessaging();
    }

    protected function resolveCredentialsPath(): string
    {
        // Production Railway:
        // credential Firebase disimpan sebagai Base64 di environment variable.
        $base64 = env('FIREBASE_CREDENTIALS_BASE64');

        if ($base64) {
            $path = storage_path(
                'app/firebase/runtime-firebase-credentials.json'
            );

            $directory = dirname($path);

            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $decoded = base64_decode($base64, true);

            if ($decoded === false) {
                throw new \RuntimeException(
                    'FIREBASE_CREDENTIALS_BASE64 tidak valid.'
                );
            }

            file_put_contents($path, $decoded);

            return $path;
        }

        // Local development:
        // tetap menggunakan file JSON lokal yang sekarang.
        $credentials = env(
            'FIREBASE_CREDENTIALS',
            'storage/app/firebase/dpk-aspirasi-firebase-adminsdk.json'
        );

        return base_path($credentials);
    }

    public function sendNotification(
        string $token,
        string $title,
        string $body,
        array $data = []
    ): array {
        $notification = Notification::create($title, $body);

        $message = CloudMessage::new()
            ->withToken($token)
            ->withNotification($notification)
            ->withData($data);

        return $this->messaging->send($message);
    }
}
