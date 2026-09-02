<?php

namespace Tests\Feature;

use App\Models\AkunWarga;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_warga_can_register_with_linked_pelapor(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'nama' => 'Budi',
            'email' => 'budi@example.com',
            'kontak' => '081234567890',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'budi@example.com');
        $this->assertDatabaseHas('akun_warga', [
            'email' => 'budi@example.com',
        ]);
        $this->assertDatabaseHas('pelapor', [
            'user_account_id' => AkunWarga::where(
                'email',
                'budi@example.com'
            )->value('id'),
        ]);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $payload = [
            'nama' => 'Budi',
            'email' => 'budi@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $this->postJson('/api/auth/register', $payload)
            ->assertCreated();

        $this->postJson('/api/auth/register', $payload)
            ->assertUnprocessable();
    }

    public function test_password_confirmation_is_required(): void
    {
        $this->postJson('/api/auth/register', [
            'nama' => 'Budi',
            'email' => 'budi@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different123',
        ])->assertUnprocessable();
    }
}
