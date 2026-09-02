<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ResetPasswordMail;
use App\Models\AkunWarga;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Fitur "Lupa Password" SIPINTAR.
 *
 * Alur: user memasukkan email -> backend membuat kode OTP 6 digit
 * (cryptographically secure via random_int) -> kode DI-HASH lalu disimpan di
 * tabel password_reset_tokens -> kode dikirim via email -> user memasukkan
 * kode -> user mengatur password baru -> baris token dihapus (single use).
 *
 * Keamanan:
 * - Kode disimpan sebagai hash, bukan plaintext.
 * - Kedaluwarsa 60 menit, maksimal 5 percobaan verifikasi.
 * - Rate limit per email (kirim kode) dan per email (verifikasi/reset).
 * - Response forgot-password selalu sama (anti user enumeration).
 * - Tidak mengubah JWT/login/register yang sudah berjalan. JWT bersifat
 *   stateless: sesi yang sudah terbit tetap berlaku sampai kedaluwarsa.
 */
class ForgotPasswordController extends Controller
{
    private const CODE_TTL_MINUTES = 60;

    private const MAX_VERIFY_ATTEMPTS = 5;

    public function sendResetCode(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower(trim($request->input('email')));

        // Rate limit per email: maks 3 permintaan per 10 menit.
        $throttleKey = 'reset-pw-mail:' . sha1($email);
        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'success' => false,
                'message' => 'Terlalu banyak permintaan. Silakan coba lagi dalam '
                    . max(1, (int) ceil($seconds / 60)) . ' menit.',
            ], 429);
        }
        RateLimiter::hit($throttleKey, 600);

        $account = $this->findAccount($email);

        if ($account !== null) {
            $code = (string) random_int(100000, 999999);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $email],
                [
                    'token' => Hash::make($code),
                    'attempts' => 0,
                    'created_at' => now(),
                ],
            );

            try {
                Mail::to($email)->send(new ResetPasswordMail($code, self::CODE_TTL_MINUTES));
            } catch (\Throwable $e) {
                // Jangan pernah mencatat kode/credential. Hanya pesan error.
                Log::error('Gagal mengirim email reset password.', [
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mengirim email. Silakan coba beberapa saat lagi.',
                ], 500);
            }
        }

        // Response identik meski email tidak terdaftar (anti enumeration).
        return response()->json([
            'success' => true,
            'message' => 'Jika email terdaftar, kode reset password telah dikirim. Periksa kotak masuk atau folder spam Anda.',
        ]);
    }

    public function verifyResetCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'token' => ['required', 'string', 'digits:6'],
        ]);

        $email = strtolower(trim($validated['email']));

        // Rate limit verifikasi: maks 10 percobaan per menit per email.
        $throttleKey = 'reset-pw-verify:' . sha1($email);
        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            return response()->json([
                'success' => false,
                'message' => 'Terlalu banyak percobaan. Silakan coba lagi beberapa saat.',
            ], 429);
        }
        RateLimiter::hit($throttleKey, 60);

        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if ($record === null || $this->isExpired($record)) {
            return $this->invalidCodeResponse();
        }

        if ((int) $record->attempts >= self::MAX_VERIFY_ATTEMPTS) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            return response()->json([
                'success' => false,
                'message' => 'Terlalu banyak percobaan kode salah. Kode telah kedaluwarsa, silakan minta kode baru.',
            ], 400);
        }

        if (!Hash::check($validated['token'], $record->token)) {
            DB::table('password_reset_tokens')->where('email', $email)->increment('attempts');

            return $this->invalidCodeResponse();
        }

        return response()->json([
            'success' => true,
            'message' => 'Kode verifikasi valid. Silakan masukkan password baru.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'token' => ['required', 'string', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $email = strtolower(trim($validated['email']));

        $throttleKey = 'reset-pw-verify:' . sha1($email);
        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            return response()->json([
                'success' => false,
                'message' => 'Terlalu banyak percobaan. Silakan coba lagi beberapa saat.',
            ], 429);
        }
        RateLimiter::hit($throttleKey, 60);

        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if ($record === null || $this->isExpired($record)) {
            return $this->invalidCodeResponse();
        }

        if ((int) $record->attempts >= self::MAX_VERIFY_ATTEMPTS) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            return response()->json([
                'success' => false,
                'message' => 'Terlalu banyak percobaan. Kode telah kedaluwarsa, silakan minta kode baru.',
            ], 400);
        }

        if (!Hash::check($validated['token'], $record->token)) {
            DB::table('password_reset_tokens')->where('email', $email)->increment('attempts');

            return $this->invalidCodeResponse();
        }

        $account = $this->findAccount($email);

        if ($account === null) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan. Silakan minta kode reset baru.',
            ], 400);
        }

        DB::transaction(function () use ($account, $email, $validated) {
            $account->forceFill(['password' => Hash::make($validated['password'])])->save();
            // Single use: hapus baris token setelah password diubah.
            DB::table('password_reset_tokens')->where('email', $email)->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diubah. Silakan login dengan password baru Anda.',
        ]);
    }

    /**
     * Mencari akun (admin/petugas atau warga) berdasarkan email.
     * Email dijamin unik lintas kedua tabel oleh logika registrasi.
     */
    private function findAccount(string $email): ?object
    {
        return User::where('email', $email)->first()
            ?? AkunWarga::where('email', $email)->first();
    }

    private function isExpired(object $record): bool
    {
        $createdAt = $record->created_at !== null
            ? \Illuminate\Support\Carbon::parse($record->created_at)
            : null;

        return $createdAt === null
            || $createdAt->lt(now()->subMinutes(self::CODE_TTL_MINUTES));
    }

    private function invalidCodeResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Kode verifikasi tidak valid atau telah kedaluwarsa.',
        ], 400);
    }
}
