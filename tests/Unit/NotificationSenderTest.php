<?php

namespace Tests\Unit;

use App\Models\DeviceToken;
use App\Services\FirebaseService;
use App\Services\NotificationSender;
use Kreait\Firebase\Exception\Messaging\InvalidArgument;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Mockery;
use Tests\TestCase;

class NotificationSenderTest extends TestCase
{
    public function test_invalid_token_is_deactivated(): void
    {
        $firebase = Mockery::mock(FirebaseService::class);
        $firebase->shouldReceive('sendNotification')
            ->once()
            ->andThrow(NotFound::becauseTokenNotFound('invalid-token'));

        $deviceToken = Mockery::mock(DeviceToken::class)->makePartial();
        $deviceToken->id = 10;
        $deviceToken->token = 'invalid-token';
        $deviceToken->shouldReceive('update')
            ->once()
            ->with(['is_active' => false]);

        (new NotificationSender($firebase))->sendToDevice(
            $deviceToken,
            'Title',
            'Body',
            [],
            20
        );

        $this->assertTrue(true);
    }

    public function test_temporary_firebase_error_does_not_deactivate_token(): void
    {
        $firebase = Mockery::mock(FirebaseService::class);
        $firebase->shouldReceive('sendNotification')
            ->once()
            ->andThrow(new \RuntimeException('temporary failure'));

        $deviceToken = Mockery::mock(DeviceToken::class)->makePartial();
        $deviceToken->id = 11;
        $deviceToken->token = 'temporary-error-token';
        $deviceToken->shouldNotReceive('update');

        (new NotificationSender($firebase))->sendToDevice(
            $deviceToken,
            'Title',
            'Body',
            [],
            21
        );

        $this->assertTrue(true);
    }
}