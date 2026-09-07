<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AkunWarga;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'account_type' => ['nullable', 'string', 'in:staff,warga'],
        ]);

        $search = trim($validated['search'] ?? '');
        $accountType = $validated['account_type'] ?? null;
        $selfId = (int) $request->user()->id;

        $items = [];

        if ($accountType === null || $accountType === 'staff') {
            $staffQuery = User::query()
                ->whereIn('role', ['admin', 'petugas'])
                ->orderBy('name');

            if ($search !== '') {
                $staffQuery->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            foreach ($staffQuery->get() as $user) {
                $items[] = $this->formatAccount($user, 'staff', $selfId);
            }
        }

        if ($accountType === null || $accountType === 'warga') {
            $wargaQuery = AkunWarga::query()->orderBy('nama');

            if ($search !== '') {
                $wargaQuery->where(function ($q) use ($search) {
                    $q->where('nama', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            foreach ($wargaQuery->limit(200)->get() as $warga) {
                $items[] = $this->formatAccount($warga, 'warga', $selfId);
            }
        }

        return response()->json([
            'success' => true,
            'data' => ['users' => $items],
        ]);
    }

    public function resetPassword(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'account_type' => ['required', 'string', 'in:staff,warga'],
        ]);

        $accountType = $validated['account_type'];

        $account = $accountType === 'staff'
            ? User::whereIn('role', ['admin', 'petugas'])->find($id)
            : AkunWarga::find($id);

        if ($account === null) {
            return response()->json([
                'success' => false,
                'message' => 'Pengguna tidak ditemukan.',
            ], 404);
        }

        // Admin tidak boleh me-reset akunnya sendiri.
        if ($accountType === 'staff' && (int) $account->id === (int) $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak dapat mereset password akun Anda sendiri.',
            ], 422);
        }

        $temporaryPassword = Str::password(12, symbols: false);

        $account->forceFill([
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => true,
        ])->save();

        // Password sementara hanya dikembalikan pada respons ini.
        return response()->json([
            'success' => true,
            'message' => 'Password berhasil direset. Berikan password sementara kepada pengguna secara manual.',
            'data' => [
                'email' => $account->email,
                'temporary_password' => $temporaryPassword,
            ],
        ]);
    }

    private function formatAccount(object $account, string $type, int $selfId): array
    {
        $isStaff = $type === 'staff';

        return [
            'id' => $account->id,
            'account_type' => $type,
            'name' => $isStaff ? $account->name : $account->nama,
            'email' => $account->email,
            'role' => $isStaff ? $account->role : 'warga',
            'must_change_password' => (bool) $account->must_change_password,
            'is_self' => $isStaff && (int) $account->id === $selfId,
        ];
    }
}
