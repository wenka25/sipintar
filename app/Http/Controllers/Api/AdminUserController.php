<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\TemporaryPasswordMail;
use App\Models\AkunWarga;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Manajemen pengguna untuk Admin (TASK A: reset password via Admin).
 *
 * Alur baru "Lupa Password":
 * user meminta bantuan Admin -> Admin memilih user -> Admin menjalankan
 * reset -> server menghasilkan password sementara (disimpan hanya sebagai
 * hash) -> user login dengan password sementara -> user WAJIB mengganti
 * password (must_change_password = true).
 *
 * Keamanan:
 * - Route dilindungi unified.auth + role:admin (lihat routes/api.php).
 * - Password sementara di-generate server-side (Str::password, cryptographically
 *   secure), tidak pernah disimpan plaintext, tidak pernah di-log.
 * - Password sementara hanya dikembalikan SEKALI di response ini.
 * - Admin tidak dapat me-reset akunnya sendiri.
 * - Tidak ada bypass authentication; menggunakan middleware yang sudah ada.
 */
class AdminUserController extends Controller
{
    /**
     * Daftar pengguna (staff: admin/petugas, dan warga) untuk Manajemen Pengguna.
     * Mendukung pencarian sederhana by nama/email dan filter account_type.
     */
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

    /**
     * Reset password sebuah akun oleh Admin. Menghasilkan password sementara,
     * menyimpannya sebagai hash, dan menandai akun wajib ganti password.
     */
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

        // Password sementara: kuat, di-generate server-side (random bytes).
        // TIDAK disimpan plaintext, TIDAK di-log. Hash menggunakan bcrypt
        // (Hash::make) yang sudah dipakai project.
        $temporaryPassword = Str::password(12, symbols: false);

        $account->forceFill([
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => true,
        ])->save();

        try {
            Mail::to($account->email)->send(new TemporaryPasswordMail($temporaryPassword));
        } catch (\Throwable) {
            return response()->json([
                'success' => true,
                'message' => 'Password berhasil direset, tetapi email tidak berhasil dikirim. Gunakan prosedur fallback yang tersedia.',
                'data' => [
                    'email_sent' => false,
                    'email' => $account->email,
                    'temporary_password' => $temporaryPassword,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil direset dan dikirim ke email pengguna.',
            'data' => [
                'email_sent' => true,
                'email' => $account->email,
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
