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
        $credentials = base_path(
            env(
                'FIREBASE_CREDENTIALS',
                'storage/app/firebase/dpk-aspirasi-firebase-adminsdk.json'
            )
        );

        $this->messaging = (new Factory)
            ->withServiceAccount($credentials)
            ->createMessaging();
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