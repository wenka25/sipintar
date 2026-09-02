<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AkunWarga;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => [
                'required',
                'string',
                'max:512',
            ],

            'platform' => [
                'required',
                'in:android,ios',
            ],

        ]);

        $authenticatedWarga = $request->user();
        if ($authenticatedWarga instanceof AkunWarga) {
            $validated['pelapor_id'] = $authenticatedWarga
                ->pelapor()
                ->latest('id')
                ->value('id');
        }

        $deviceToken = DeviceToken::firstOrNew([
            'token' => $validated['token'],
        ]);

        // Anonymous clients may register a token, but cannot claim an arbitrary
        // reporter. Ownership is established only by the authenticated warga
        // identity or by the existing anonymous-report flow.
        if ($authenticatedWarga instanceof AkunWarga) {
            $deviceToken->pelapor_id = $validated['pelapor_id'];
        }

        $deviceToken->platform = $validated['platform'];
        $deviceToken->is_active = true;
        $deviceToken->last_seen_at = now();
        $deviceToken->token_hash = hash('sha256', $validated['token']);
        $deviceToken->save();

        return response()->json([
            'success' => true,
            'message' => 'Device token berhasil disimpan.',
            'data' => [
                'id' => $deviceToken->id,
                'platform' => $deviceToken->platform,
            ],
        ], 201);
    }
}