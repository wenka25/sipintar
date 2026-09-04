<?php

namespace Tests\Feature;

use App\Mail\TemporaryPasswordMail;
use App\Models\AkunWarga;
use App\Models\PasswordResetRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * TASK B: alur permintaan reset password (terpisah dari laporan).
 * user ajukan (public) -> admin lihat/verify -> admin reset
 * -> must_change_password = true -> user ganti password -> selesai.
 */
class PasswordResetRequestTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::firstOrCreate(
            ['email' => 'admin@dpk.go.id'],
            ['name' => 'Admin', 'password' => Hash::make('password-admin-123'), 'role' => 'admin'],
        );
    }

    private function createWarga(): AkunWarga
    {
        return AkunWarga::firstOrCreate(
            ['email' => 'budi@example.com'],
            ['nama' => 'Budi', 'password' => Hash::make('password-warga-123')],
        );
    }

    private function loginAsAdmin(): string
    {
        $this->createAdmin();

        return $this->postJson('/api/auth/login', [
            'identifier' => 'admin@dpk.go.id',
            'password' => 'password-admin-123',
        ])->assertOk()->json('data.token');
    }

    public function test_warga_can_submit_password_reset_request_without_login(): void
    {
        $this->createWarga();

        $this->postJson('/api/password-reset-requests', [
            'identifier_type' => 'email',
            'identifier' => 'budi@example.com',
            'message' => 'Saya lupa password.',
        ])->assertStatus(201)->assertJsonPath('success', true);

        $this->assertDatabaseHas('password_reset_requests', [
            'identifier' => 'budi@example.com',
            'status' => 'pending',
            'requested_account_type' => 'warga',
        ]);
    }

    public function test_duplicate_pending_request_is_rejected(): void
    {
        $this->createWarga();

        $payload = ['identifier_type' => 'email', 'identifier' => 'budi@example.com'];
        $this->postJson('/api/password-reset-requests', $payload)->assertStatus(201);
        $this->postJson('/api/password-reset-requests', $payload)->assertStatus(409);
    }

    public function test_password_reset_requests_are_isolated_from_laporan(): void
    {
        $this->createWarga();

        $this->postJson('/api/password-reset-requests', [
            'identifier_type' => 'email',
            'identifier' => 'budi@example.com',
        ])->assertStatus(201);

        // Tabel laporan tidak tersentuh oleh request reset password.
        $this->assertDatabaseCount('laporan', 0);
    }

    public function test_admin_can_verify_reset_and_complete_request(): void
    {
        $this->createWarga();
        $token = $this->loginAsAdmin();
        Mail::fake();

        $this->postJson('/api/password-reset-requests', [
            'identifier_type' => 'email',
            'identifier' => 'budi@example.com',
        ])->assertStatus(201);

        $list = $this->getJson('/api/admin/password-reset-requests?status=pending', [
            'Authorization' => "Bearer {$token}",
        ])->assertOk()->json('data.requests');

        $this->assertCount(1, $list);
        $requestId = $list[0]['id'];

        $this->postJson("/api/admin/password-reset-requests/{$requestId}/verify", [], [
            'Authorization' => "Bearer {$token}",
        ])->assertOk()->assertJsonPath('data.request.status', 'verified');

        $resetResponse = $this->postJson(
            "/api/admin/password-reset-requests/{$requestId}/reset",
            [],
            ['Authorization' => "Bearer {$token}"],
        )->assertOk()
            ->assertJsonPath('data.email_sent', true)
            ->assertJsonMissingPath('data.temporary_password');

        $temporaryPassword = null;
        Mail::assertSent(TemporaryPasswordMail::class, function (TemporaryPasswordMail $mail) use (&$temporaryPassword): bool {
            $temporaryPassword = $mail->temporaryPassword;
            return true;
        });
        $this->assertNotNull($temporaryPassword);

        // Request selesai; password plaintext tidak pernah tersimpan.
        $this->assertDatabaseHas('password_reset_requests', [
            'id' => $requestId,
            'status' => 'completed',
        ]);
        $this->assertDatabaseMissing('akun_warga', [
            'email' => 'budi@example.com',
            'password' => $temporaryPassword,
        ]);

        // User login dengan password sementara dan wajib ganti password.
        $login = $this->postJson('/api/auth/login', [
            'identifier' => 'budi@example.com',
            'password' => $temporaryPassword,
        ])->assertOk();

        $this->assertTrue($login->json('data.user.must_change_password'));

        $wargaToken = $login->json('data.token');
        $this->postJson('/api/auth/change-password', [
            'current_password' => $temporaryPassword,
            'password' => 'password-baru-123',
            'password_confirmation' => 'password-baru-123',
        ], ['Authorization' => "Bearer {$wargaToken}"])->assertOk();
    }

    public function test_admin_can_reject_request(): void
    {
        $this->createWarga();
        $token = $this->loginAsAdmin();

        $this->postJson('/api/password-reset-requests', [
            'identifier_type' => 'email',
            'identifier' => 'budi@example.com',
        ])->assertStatus(201);

        $requestId = PasswordResetRequest::first()->id;

        $this->postJson("/api/admin/password-reset-requests/{$requestId}/reject", [
            'admin_note' => 'Tidak dapat diverifikasi.',
        ], ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('data.request.status', 'rejected');
    }

    public function test_petugas_and_guest_cannot_manage_reset_requests(): void
    {
        $this->createAdmin();
        $this->createWarga();

        $petugas = User::firstOrCreate(
            ['email' => 'petugas@dpk.go.id'],
            ['name' => 'Petugas', 'password' => Hash::make('password-petugas-123'), 'role' => 'petugas'],
        );

        $petugasToken = $this->postJson('/api/auth/login', [
            'identifier' => 'petugas@dpk.go.id',
            'password' => 'password-petugas-123',
        ])->assertOk()->json('data.token');

        $this->getJson('/api/admin/password-reset-requests', [
            'Authorization' => "Bearer {$petugasToken}",
        ])->assertStatus(403);

        $this->getJson('/api/admin/password-reset-requests')->assertStatus(401);

        $this->postJson('/api/password-reset-requests', [
            'identifier_type' => 'email',
            'identifier' => 'petugas@dpk.go.id',
        ])->assertStatus(201);
    }

    public function test_reset_completed_request_is_rejected(): void
    {
        $this->createWarga();
        $token = $this->loginAsAdmin();

        $this->postJson('/api/password-reset-requests', [
            'identifier_type' => 'email',
            'identifier' => 'budi@example.com',
        ])->assertStatus(201);

        $requestId = PasswordResetRequest::first()->id;

        $this->postJson("/api/admin/password-reset-requests/{$requestId}/reset", [], [
            'Authorization' => "Bearer {$token}",
        ])->assertOk();

        $this->postJson("/api/admin/password-reset-requests/{$requestId}/reset", [], [
            'Authorization' => "Bearer {$token}",
        ])->assertStatus(422);
    }

    public function test_rejected_request_cannot_be_reset(): void
    {
        $this->createWarga();
        $token = $this->loginAsAdmin();

        $this->postJson('/api/password-reset-requests', [
            'identifier_type' => 'email',
            'identifier' => 'budi@example.com',
        ])->assertStatus(201);

        $requestId = PasswordResetRequest::first()->id;

        $this->postJson("/api/admin/password-reset-requests/{$requestId}/reject", [], [
            'Authorization' => "Bearer {$token}",
        ])->assertOk();

        $this->postJson("/api/admin/password-reset-requests/{$requestId}/reset", [], [
            'Authorization' => "Bearer {$token}",
        ])->assertStatus(422);
    }

    public function test_completed_request_cannot_be_verified_or_rejected_again(): void
    {
        $this->createWarga();
        $token = $this->loginAsAdmin();

        $this->postJson('/api/password-reset-requests', [
            'identifier_type' => 'email',
            'identifier' => 'budi@example.com',
        ])->assertStatus(201);

        $requestId = PasswordResetRequest::first()->id;

        $this->postJson("/api/admin/password-reset-requests/{$requestId}/reset", [], [
            'Authorization' => "Bearer {$token}",
        ])->assertOk();

        $this->postJson("/api/admin/password-reset-requests/{$requestId}/verify", [], [
            'Authorization' => "Bearer {$token}",
        ])->assertStatus(422);

        $this->postJson("/api/admin/password-reset-requests/{$requestId}/reject", [], [
            'Authorization' => "Bearer {$token}",
        ])->assertStatus(422);
    }
}
