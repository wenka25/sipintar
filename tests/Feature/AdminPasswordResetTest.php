<?php

namespace Tests\Feature;

use App\Mail\TemporaryPasswordMail;
use App\Models\AkunWarga;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * TASK A: fitur "Lupa Password" via Admin.
 *
 * Alur baru:
 * user -> hubungi Admin -> Admin reset password (endpoint admin-only)
 * -> password sementara dihasilkan server-side (disimpan sebagai hash,
 * dikembalikan hanya sekali) -> user login dengan password sementara
 * (must_change_password = true) -> user wajib ganti password via
 * /auth/change-password -> login normal kembali berfungsi.
 */
class AdminPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::firstOrCreate(
            ['email' => 'admin@dpk.go.id'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password-admin-123'),
                'role' => 'admin',
            ],
        );
    }

    private function createPetugas(): User
    {
        return User::firstOrCreate(
            ['email' => 'petugas@dpk.go.id'],
            [
                'name' => 'Petugas',
                'password' => Hash::make('password-petugas-123'),
                'role' => 'petugas',
            ],
        );
    }

    private function createWarga(): AkunWarga
    {
        return AkunWarga::firstOrCreate(
            ['email' => 'budi@example.com'],
            [
                'nama' => 'Budi',
                'password' => Hash::make('password-warga-123'),
            ],
        );
    }

    private function loginAsAdmin(): string
    {
        $admin = $this->createAdmin();

        return $this->postJson('/api/auth/login', [
            'identifier' => 'admin@dpk.go.id',
            'password' => 'password-admin-123',
        ])->assertOk()->json('data.token');
    }

    public function test_admin_can_list_users(): void
    {
        $this->createAdmin();
        $this->createWarga();
        $token = $this->loginAsAdmin();

        $this->getJson('/api/admin/users', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['users' => [['id', 'account_type', 'name', 'email', 'role']]]]);
    }

    public function test_admin_can_reset_warga_password_and_user_must_change_it(): void
    {
        $this->createAdmin();
        $warga = $this->createWarga();
        $token = $this->loginAsAdmin();
        Mail::fake();

        $response = $this->postJson(
            "/api/admin/users/{$warga->id}/reset-password",
            ['account_type' => 'warga'],
            ['Authorization' => "Bearer {$token}"],
        )->assertOk()->assertJsonPath('success', true);

        $response->assertJsonPath('data.email_sent', true)
            ->assertJsonPath('data.email', $warga->email)
            ->assertJsonMissingPath('data.temporary_password');

        $temporaryPassword = null;
        Mail::assertSent(TemporaryPasswordMail::class, function (TemporaryPasswordMail $mail) use (&$temporaryPassword, $warga): bool {
            $temporaryPassword = $mail->temporaryPassword;
            return $mail->hasTo($warga->email)
                && $mail->envelope()->subject === 'SIPINTAR — Password Sementara Akun Anda'
                && str_contains($mail->render(), $temporaryPassword);
        });
        $this->assertNotNull($temporaryPassword);

        // Password lama tidak berlaku.
        $this->postJson('/api/auth/login', [
            'identifier' => 'budi@example.com',
            'password' => 'password-warga-123',
        ])->assertStatus(401);

        // Password sementara berlaku dan membawa flag must_change_password.
        $login = $this->postJson('/api/auth/login', [
            'identifier' => 'budi@example.com',
            'password' => $temporaryPassword,
        ])->assertOk();

        $this->assertTrue($login->json('data.user.must_change_password'));
        $wargaToken = $login->json('data.token');

        // Password sementara tidak pernah disimpan plaintext.
        $this->assertDatabaseMissing('akun_warga', ['email' => 'budi@example.com', 'password' => $temporaryPassword]);

        // User wajib mengganti password.
        $this->postJson('/api/auth/change-password', [
            'current_password' => $temporaryPassword,
            'password' => 'password-baru-123',
            'password_confirmation' => 'password-baru-123',
        ], ['Authorization' => "Bearer {$wargaToken}"])->assertOk()->assertJsonPath('success', true);

        // Password baru berlaku, lama/temporer tidak.
        $this->postJson('/api/auth/login', [
            'identifier' => 'budi@example.com',
            'password' => 'password-baru-123',
        ])->assertOk()->assertJsonPath('data.user.must_change_password', false);

        $this->postJson('/api/auth/login', [
            'identifier' => 'budi@example.com',
            'password' => $temporaryPassword,
        ])->assertStatus(401);
    }

    public function test_admin_can_reset_petugas_password(): void
    {
        $this->createAdmin();
        $petugas = $this->createPetugas();
        $token = $this->loginAsAdmin();
        Mail::fake();

        $response = $this->postJson(
            "/api/admin/users/{$petugas->id}/reset-password",
            ['account_type' => 'staff'],
            ['Authorization' => "Bearer {$token}"],
        )->assertOk()
            ->assertJsonPath('data.email_sent', true)
            ->assertJsonMissingPath('data.temporary_password');

        $temporaryPassword = null;
        Mail::assertSent(TemporaryPasswordMail::class, function (TemporaryPasswordMail $mail) use (&$temporaryPassword, $petugas): bool {
            $temporaryPassword = $mail->temporaryPassword;
            return $mail->hasTo($petugas->email);
        });
        $this->assertNotNull($temporaryPassword);

        $this->postJson('/api/auth/login', [
            'identifier' => 'petugas@dpk.go.id',
            'password' => $temporaryPassword,
        ])->assertOk()->assertJsonPath('data.user.role', 'petugas');
    }

    public function test_admin_cannot_reset_own_account(): void
    {
        $admin = $this->createAdmin();
        $token = $this->loginAsAdmin();

        $this->postJson(
            "/api/admin/users/{$admin->id}/reset-password",
            ['account_type' => 'staff'],
            ['Authorization' => "Bearer {$token}"],
        )->assertStatus(422);
    }

    public function test_petugas_cannot_use_admin_user_endpoints(): void
    {
        $this->createAdmin();
        $petugas = $this->createPetugas();
        $warga = $this->createWarga();

        $token = $this->postJson('/api/auth/login', [
            'identifier' => 'petugas@dpk.go.id',
            'password' => 'password-petugas-123',
        ])->assertOk()->json('data.token');

        $this->getJson('/api/admin/users', ['Authorization' => "Bearer {$token}"])
            ->assertStatus(403);

        $this->postJson(
            "/api/admin/users/{$warga->id}/reset-password",
            ['account_type' => 'warga'],
            ['Authorization' => "Bearer {$token}"],
        )->assertStatus(403);
    }

    public function test_anonymous_cannot_use_admin_user_endpoints(): void
    {
        $this->createAdmin();
        $warga = $this->createWarga();

        $this->getJson('/api/admin/users')->assertStatus(401);
        $this->postJson("/api/admin/users/{$warga->id}/reset-password", ['account_type' => 'warga'])
            ->assertStatus(401);
    }

    public function test_warga_cannot_reset_password_of_other_user(): void
    {
        $this->createAdmin();
        $warga = $this->createWarga();
        $target = $this->createPetugas();

        $token = $this->postJson('/api/auth/login', [
            'identifier' => 'budi@example.com',
            'password' => 'password-warga-123',
        ])->assertOk()->json('data.token');

        $this->postJson(
            "/api/admin/users/{$target->id}/reset-password",
            ['account_type' => 'staff'],
            ['Authorization' => "Bearer {$token}"],
        )->assertStatus(403);
    }

    public function test_change_password_requires_current_password(): void
    {
        $this->createAdmin();
        $warga = $this->createWarga();

        $token = $this->postJson('/api/auth/login', [
            'identifier' => 'budi@example.com',
            'password' => 'password-warga-123',
        ])->assertOk()->json('data.token');

        $this->postJson('/api/auth/change-password', [
            'current_password' => 'password-salah',
            'password' => 'password-baru-123',
            'password_confirmation' => 'password-baru-123',
        ], ['Authorization' => "Bearer {$token}"])->assertStatus(422);

        // Password lama tetap berlaku.
        $this->postJson('/api/auth/login', [
            'identifier' => 'budi@example.com',
            'password' => 'password-warga-123',
        ])->assertOk();
    }

    public function test_old_otp_endpoints_are_removed(): void
    {
        $this->postJson('/api/auth/forgot-password', ['email' => 'budi@example.com'])
            ->assertStatus(404);
        $this->postJson('/api/auth/verify-reset-token', ['email' => 'budi@example.com', 'token' => '123456'])
            ->assertStatus(404);
        $this->postJson('/api/auth/reset-password', [
            'email' => 'budi@example.com',
            'token' => '123456',
            'password' => 'password-baru-123',
        ])->assertStatus(404);
    }
}
