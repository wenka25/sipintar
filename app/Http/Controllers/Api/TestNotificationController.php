<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Throwable;

class TestNotificationController extends Controller
{
    public function send(Request $request, FirebaseService $firebaseService)
    {
        $request->validate([
            'token' => ['required', 'string'],
        ]);

        try {
            $firebaseService->sendNotification(
                $request->token,
                'DPK Aspirasi',
                'Ini adalah notifikasi percobaan dari sistem DPK.'
            );

            return response()->json([
                'success' => true,
                'message' => 'Notifikasi berhasil dikirim.',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim notifikasi.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}