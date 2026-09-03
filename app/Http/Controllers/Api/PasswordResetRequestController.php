<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AkunWarga;
use App\Models\PasswordResetRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * TASK B: Permintaan reset password (fitur TERPISAH dari laporan).
 *
 * Alur: user lupa password mengajukan request (tanpa login, public)
 * -> Admin melihat daftar request pending -> Admin verify/reject
 * -> Admin menjalankan reset (reuse logika password sementara yang SUDAH ADA
 *    pada AdminUserController::resetPassword: hash-only, must_change_password)
 * -> request ditandai completed.
 *
 * Keamanan:
 * - Endpoint publik hanya membuat request, tidak pernah mengubah password.
 * - Endpoint verifikasi/reset/reject dilindungi unified.auth + role:admin
 *   (lihat routes/api.php).
 * - Tidak ada OTP, tidak ada password plaintext yang disimpan.
 * - Rate-limit sederhana: tolak duplikat pending untuk identifier yang sama.
 */
class PasswordResetRequestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identifier_type' => ['required', 'string', 'in:email,kontak'],
            'identifier' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $identifier = strtolower(trim($validated['identifier']));

        // Rate-limit: satu request pending per identifier.
        $existing = PasswordResetRequest::where('identifier_type', $validated['identifier_type'])
            ->where('identifier', $identifier)
            ->where('status', PasswordResetRequest::STATUS_PENDING)
            ->first();

        if ($existing !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Permintaan reset password Anda sudah ada dan sedang diproses Admin.',
            ], 409);
        }

        // Resolusi akun dilakukan server-side (tidak menerima id dari publik),
        // agar Admin langsung tahu akun mana yang perlu direset.
        $accountId = null;
        $accountType = null;
        $user = User::whereIn('role', ['admin', 'petugas'])
            ->whereRaw('LOWER(email) = ?', [$identifier])->first();
        $warga = AkunWarga::whereRaw('LOWER(email) = ?', [$identifier])->first();

        if ($user !== null) {
            $accountId = $user->id;
            $accountType = 'staff';
        } elseif ($warga !== null) {
            $accountId = $warga->id;
            $accountType = 'warga';
        }

        $request_ = PasswordResetRequest::create([
            'identifier_type' => $validated['identifier_type'],
            'identifier' => $identifier,
            'requested_account_id' => $accountId,
            'requested_account_type' => $accountType,
            'message' => $validated['message'] ?? null,
            'status' => PasswordResetRequest::STATUS_PENDING,
        ]);

        // Response tidak membocorkan apakah akun ada atau tidak.
        return response()->json([
            'success' => true,
            'message' => 'Permintaan reset password terkirim. Admin akan memverifikasi permintaan Anda.',
            'data' => ['id' => $request_->id, 'status' => $request_->status],
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:' . implode(',', PasswordResetRequest::STATUSES)],
        ]);

        $query = PasswordResetRequest::query()->latest('id');

        if (($validated['status'] ?? null) !== null) {
            $query->where('status', $validated['status']);
        }

        $requests = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => [
                'requests' => collect($requests->items())->map(fn ($r) => $this->format($r))->all(),
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    public function verify(Request $request, int $id): JsonResponse
    {
        $resetRequest = $this->findOrFail($id);

        // Hanya request pending yang boleh diverifikasi (state machine).
        if ($resetRequest->status !== PasswordResetRequest::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Permintaan ini sudah diproses dan tidak dapat diverifikasi ulang.',
            ], 422);
        }

        $resetRequest->update([
            'status' => PasswordResetRequest::STATUS_VERIFIED,
            'handled_by' => $request->user()->id,
            'handled_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => ['request' => $this->format($resetRequest->refresh())],
        ]);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $resetRequest = $this->findOrFail($id);

        // Hanya request pending yang boleh ditolak (state machine).
        if ($resetRequest->status !== PasswordResetRequest::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Permintaan ini sudah diproses dan tidak dapat ditolak ulang.',
            ], 422);
        }

        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $resetRequest->update([
            'status' => PasswordResetRequest::STATUS_REJECTED,
            'admin_note' => $validated['admin_note'] ?? null,
            'handled_by' => $request->user()->id,
            'handled_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => ['request' => $this->format($resetRequest->refresh())],
        ]);
    }

    /**
     * Admin melakukan reset password untuk request yang sudah diverifikasi.
     * REUSE pola yang sama dengan AdminUserController::resetPassword
     * (password sementara hash-only + must_change_password = true).
     */
    public function reset(Request $request, int $id): JsonResponse
    {
        $resetRequest = $this->findOrFail($id);

        // State machine: hanya pending/verified yang dapat direset.
        // Request rejected dan completed TIDAK dapat diproses ulang.
        if (!in_array(
            $resetRequest->status,
            [PasswordResetRequest::STATUS_PENDING, PasswordResetRequest::STATUS_VERIFIED],
            true
        )) {
            return response()->json([
                'success' => false,
                'message' => 'Permintaan ini sudah selesai atau ditolak dan tidak dapat diproses ulang.',
            ], 422);
        }

        $accountId = $resetRequest->requested_account_id;
        $accountType = $resetRequest->requested_account_type;

        if ($accountId === null || $accountType === null) {
            return response()->json([
                'success' => false,
                'message' => 'Akun untuk identifier ini tidak ditemukan. Tolak permintaan ini.',
            ], 422);
        }

        $account = $accountType === 'staff'
            ? User::whereIn('role', ['admin', 'petugas'])->find($accountId)
            : AkunWarga::find($accountId);

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

        DB::transaction(function () use ($account, $temporaryPassword, $request, $resetRequest) {
            $account->forceFill([
                'password' => Hash::make($temporaryPassword),
                'must_change_password' => true,
            ])->save();

            $resetRequest->update([
                'status' => PasswordResetRequest::STATUS_COMPLETED,
                'handled_by' => $request->user()->id,
                'handled_at' => $resetRequest->handled_at ?? now(),
                'completed_at' => now(),
            ]);
        });

        // Password sementara hanya dikembalikan SEKALI di response ini.
        return response()->json([
            'success' => true,
            'message' => 'Password berhasil direset. Berikan password sementara ini kepada pengguna.',
            'data' => [
                'temporary_password' => $temporaryPassword,
                'request' => $this->format($resetRequest->refresh()),
            ],
        ]);
    }

    private function findOrFail(int $id): PasswordResetRequest
    {
        $resetRequest = PasswordResetRequest::find($id);

        if ($resetRequest === null) {
            abort(response()->json([
                'success' => false,
                'message' => 'Permintaan reset password tidak ditemukan.',
            ], 404));
        }

        return $resetRequest;
    }

    private function format(PasswordResetRequest $r): array
    {
        return [
            'id' => $r->id,
            'identifier_type' => $r->identifier_type,
            'identifier' => $r->identifier,
            'requested_account_id' => $r->requested_account_id,
            'requested_account_type' => $r->requested_account_type,
            'message' => $r->message,
            'status' => $r->status,
            'admin_note' => $r->admin_note,
            'handled_by' => $r->handled_by,
            'handled_at' => $r->handled_at?->toIso8601String(),
            'completed_at' => $r->completed_at?->toIso8601String(),
            'created_at' => $r->created_at?->toIso8601String(),
        ];
    }
}
