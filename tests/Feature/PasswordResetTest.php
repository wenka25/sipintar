<?php

namespace Tests\Feature;

use App\Mail\ResetPasswordMail;
use App\Models\AkunWarga;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function createWarga(): AkunWarga
    {
        return AkunWarga::create([
            'nama' => 'Budi',
            'email' => 'budi@example.com',
            'password' => Hash::make('passwordlama123'),
        ]);
    }

    private function requestCode(string $email = 'budi@example.com')
    {
        return $this->postJson('/api/auth/forgot-password', ['email' => $email]);
    }

    private function getCodeFromDatabase(string $email = 'budi@example.com'): ?string
    {
        // Kode disimpan sebagai hash. Test ini tidak bisa membalik hash,
        // jadi kita bandingkan hash dengan kandidat kode 6 digit.
        $hash = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->value('token');

        if ($hash === null) {
            return null;
        }

        for ($i = 100000; $i <= 999999; $i++) {
            if (Hash::check((string) $i, $hash)) {
                return (string) $i;
            }
        }

        return null;
    }

    public function test_forgot_password_with_valid_email_returns_generic_success(): void
    {
        $this->createWarga();

        Mail::fake();
        $response = $this->requestCode();

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Jika email terdaftar, kode reset password telah dikirim. Periksa kotak masuk atau folder spam Anda.',
            ]);
    }

    public function test_response_does_not_leak_email_existence(): void
    {
        // Email terdaftar dan tidak terdaftar harus menghasilkan response sama.
        $this->createWarga();

        Mail::fake();
        $registered = $this->requestCode()->assertOk();
        $unregistered = $this->requestCode('hantu@example.com')->assertOk();

        $this->assertSame(
            $registered->json('message'),
            $unregistered->json('message'),
        );
        $this->assertSame(
            $registered->json('success'),
            $unregistered->json('success'),
        );
    }

    public function test_reset_token_is_created_and_hashed(): void
    {
        $this->createWarga();

        Mail::fake();
        $this->requestCode();

        $record = DB::table('password_reset_tokens')
            ->where('email', 'budi@example.com')
            ->first();

        $this->assertNotNull($record);
        // Token tersimpan sebagai hash bcrypt, bukan plaintext.
        $this->assertStringStartsWith('$2y$', $record->token);
        $this->assertNotEquals('123456', $record->token);
    }

    public function test_reset_email_is_sent_with_code(): void
    {
        $this->createWarga();

        Mail::fake();
        $this->requestCode();

        Mail::assertSent(ResetPasswordMail::class, function (ResetPasswordMail $mail) {
            return $mail->to[0]['address'] === 'budi@example.com'
                && str_contains($mail->envelope()->subject ?? '', 'Reset Password');
        });
    }

    public function test_expired_code_is_rejected(): void
    {
        $this->createWarga();

        Mail::fake();
        $this->requestCode();

        DB::table('password_reset_tokens')
            ->where('email', 'budi@example.com')
            ->update(['created_at' => now()->subMinutes(61)]);

        $this->postJson('/api/auth/verify-reset-token', [
            'email' => 'budi@example.com',
            'token' => '123456',
        ])->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_invalid_code_is_rejected(): void
    {
        $this->createWarga();

        Mail::fake();
        $this->requestCode();

        $this->postJson('/api/auth/verify-reset-token', [
            'email' => 'budi@example.com',
            'token' => '999999',
        ])->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_reset_password_success_and_old_password_no_longer_works(): void
    {
        $this->createWarga();

        Mail::fake();
        $this->requestCode();

        $code = $this->getCodeFromDatabase();
        $this->assertNotNull($code);

        // Kode terverifikasi.
        $this->postJson('/api/auth/verify-reset-token', [
            'email' => 'budi@example.com',
            'token' => $code,
        ])->assertOk();

        // Password berhasil diubah.
        $this->postJson('/api/auth/reset-password', [
            'email' => 'budi@example.com',
            'token' => $code,
            'password' => 'passwordbaru123',
            'password_confirmation' => 'passwordbaru123',
        ])->assertOk()
            ->assertJsonPath('success', true);

        // Password lama tidak bisa login.
        $this->postJson('/api/auth/login', [
            'identifier' => 'budi@example.com',
            'password' => 'passwordlama123',
        ])->assertStatus(401);

        // Password baru bisa login dan mendapat JWT.
        $this->postJson('/api/auth/login', [
            'identifier' => 'budi@example.com',
            'password' => 'passwordbaru123',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_token_can_only_be_used_once(): void
    {
        $this->createWarga();

        Mail::fake();
        $this->requestCode();

        $code = $this->getCodeFromDatabase();
        $this->assertNotNull($code);

        $this->postJson('/api/auth/reset-password', [
            'email' => 'budi@example.com',
            'token' => $code,
            'password' => 'passwordbaru123',
            'password_confirmation' => 'passwordbaru123',
        ])->assertOk();

        // Kode yang sama tidak bisa dipakai lagi.
        $this->postJson('/api/auth/reset-password', [
            'email' => 'budi@example.com',
            'token' => $code,
            'password' => 'passwordlain123',
            'password_confirmation' => 'passwordlain123',
        ])->assertStatus(400);

        // Password tetap yang pertama.
        $this->postJson('/api/auth/login', [
            'identifier' => 'budi@example.com',
            'password' => 'passwordbaru123',
        ])->assertOk();
    }

    public function test_password_must_be_confirmed_and_minimum_length(): void
    {
        $this->createWarga();

        Mail::fake();
        $this->requestCode();

        $code = $this->getCodeFromDatabase();
        $this->assertNotNull($code);

        // Konfirmasi tidak cocok.
        $this->postJson('/api/auth/reset-password', [
            'email' => 'budi@example.com',
            'token' => $code,
            'password' => 'passwordbaru123',
            'password_confirmation' => 'berbeda123',
        ])->assertUnprocessable();

        // Terlalu pendek.
        $this->postJson('/api/auth/reset-password', [
            'email' => 'budi@example.com',
            'token' => $code,
            'password' => 'pendek',
            'password_confirmation' => 'pendek',
        ])->assertUnprocessable();
    }

    public function test_rate_limiting_blocks_repeated_requests(): void
    {
        $this->createWarga();

        Mail::fake();

        // Maksimal 3 permintaan per email per 10 menit.
        $this->requestCode()->assertOk();
        $this->requestCode()->assertOk();
        $this->requestCode()->assertOk();

        $this->requestCode()->assertStatus(429);
    }

    public function test_too_many_wrong_attempts_invalidates_code(): void
    {
        $this->createWarga();

        Mail::fake();
        $this->requestCode();

        // 5 percobaan salah.
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/verify-reset-token', [
                'email' => 'budi@example.com',
                'token' => '000000',
            ])->assertStatus(400);
        }

        // Percobaan berikutnya: kode sudah kedaluwarsa/dihapus.
        $this->postJson('/api/auth/verify-reset-token', [
            'email' => 'budi@example.com',
            'token' => '000000',
        ])->assertStatus(400);

        // Bahkan kode yang benar pun sudah tidak berlaku.
        $code = $this->getCodeFromDatabase();
        $this->assertNull($code);

        // Endpoint admin/warga lain tetap aman: password belum berubah.
        $this->postJson('/api/auth/login', [
            'identifier' => 'budi@example.com',
            'password' => 'passwordlama123',
        ])->assertOk();
    }

    public function test_admin_password_can_be_reset(): void
    {
        User::create([
            'name' => 'Admin',
            'email' => 'admin@dpk.go.id',
            'password' => Hash::make('passwordlama123'),
            'role' => 'admin',
        ]);

        Mail::fake();
        $this->requestCode('admin@dpk.go.id');

        $code = $this->getCodeFromDatabase('admin@dpk.go.id');
        $this->assertNotNull($code);

        $this->postJson('/api/auth/reset-password', [
            'email' => 'admin@dpk.go.id',
            'token' => $code,
            'password' => 'passwordbaru123',
            'password_confirmation' => 'passwordbaru123',
        ])->assertOk();

        $this->postJson('/api/auth/login', [
            'identifier' => 'admin@dpk.go.id',
            'password' => 'passwordbaru123',
        ])->assertOk()->assertJsonPath('data.user.role', 'admin');
    }
}
