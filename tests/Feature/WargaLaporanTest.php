<?php

namespace Tests\Feature;

use App\Models\AkunWarga;
use App\Models\DeviceToken;
use App\Models\Kategori;
use App\Models\Laporan;
use App\Models\Pelapor;
use App\Models\UnitLayanan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class WargaLaporanTest extends TestCase
{
    use RefreshDatabase;

    private function createWarga(string $email, string $name = 'Warga Test'): array
    {
        $warga = AkunWarga::create([
            'nama' => $name,
            'email' => $email,
            'no_hp' => '0812' . random_int(10000, 99999),
            'password' => Hash::make('password123'),
        ]);
        $pelapor = $warga->pelapor()->create([
            'nama' => $name,
            'is_anonim' => false,
        ]);
        $token = JWTAuth::customClaims([
            'account_type' => 'warga',
            'role' => 'warga',
            'pelapor_id' => $pelapor->id,
        ])->fromUser($warga);

        return [$warga, $pelapor, $token];
    }

    private function createReport(int $pelaporId, string $code, string $title): Laporan
    {
        $unit = UnitLayanan::firstOrCreate(
            ['kode' => 'UNIT_TEST'],
            ['nama' => 'Unit Test', 'is_active' => true]
        );
        $category = Kategori::firstOrCreate(['nama' => 'Kategori Test']);

        return Laporan::create([
            'kode_tiket' => $code,
            'pelapor_id' => $pelaporId,
            'unit_layanan_id' => $unit->id,
            'tipe' => 'pengaduan',
            'kategori_id' => $category->id,
            'judul' => $title,
            'deskripsi' => 'Deskripsi test',
            'status' => 'baru',
            'sumber' => 'app',
        ]);
    }

    public function test_guest_gets_401_for_warga_reports(): void
    {
        $this->getJson('/api/warga/laporan')->assertUnauthorized();
    }

    public function test_warga_gets_200_and_only_own_reports(): void
    {
        [, $pelaporA, $tokenA] = $this->createWarga('a@example.com', 'Warga A');
        [, $pelaporB] = $this->createWarga('b@example.com', 'Warga B');
        $this->createReport($pelaporA->id, 'DPK-WARGA-A', 'Laporan A');
        $this->createReport($pelaporB->id, 'DPK-WARGA-B', 'Laporan B');

        $this->withHeader('Authorization', "Bearer $tokenA")
            ->getJson('/api/warga/laporan')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.kode_tiket', 'DPK-WARGA-A');
    }

    public function test_anonymous_report_stays_anonymous_after_registration_and_login(): void
    {
        $deviceToken = 'device-token-for-test';
        $pelapor = Pelapor::create(['nama' => null, 'is_anonim' => true]);
        DeviceToken::create([
            'pelapor_id' => $pelapor->id,
            'token' => $deviceToken,
            'token_hash' => hash('sha256', $deviceToken),
            'platform' => 'android',
        ]);
        $this->createReport($pelapor->id, 'DPK-ANON-1', 'Laporan Anonymous');

        $this->postJson('/api/auth/register', [
            'nama' => 'Warga Link',
            'email' => 'link@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'device_token' => $deviceToken,
        ])->assertCreated();

        $this->assertDatabaseHas('pelapor', [
            'id' => $pelapor->id,
            'is_anonim' => 1,
            'user_account_id' => null,
        ]);
        $this->assertDatabaseCount('laporan', 1);

        $login = $this->postJson('/api/auth/login', [
            'identifier' => 'link@example.com',
            'password' => 'password123',
        ])->assertOk();
        $token = $login->json('data.token');

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/warga/laporan')
            ->assertOk()
            ->assertJsonCount(0, 'data.data');

        $this->getJson('/api/laporan/perangkat?device_token=' . $deviceToken)
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.kode_tiket', 'DPK-ANON-1');
    }

    public function test_anonymous_report_stays_visible_to_guest_and_isolated_from_warga_accounts(): void
    {
        $deviceToken = 'device-token-claimed-report';
        $anonymousPelapor = Pelapor::create(['nama' => null, 'is_anonim' => true]);
        DeviceToken::create([
            'pelapor_id' => $anonymousPelapor->id,
            'token' => $deviceToken,
            'token_hash' => hash('sha256', $deviceToken),
            'platform' => 'android',
        ]);
        $report = $this->createReport($anonymousPelapor->id, 'DPK-CLAIMED-1', 'Laporan Claimed');
        $report->deviceTokens()->attach(
            DeviceToken::where('token_hash', hash('sha256', $deviceToken))->value('id')
        );

        $wargaA = AkunWarga::create([
            'nama' => 'Warga A',
            'email' => 'claim-a@example.com',
            'no_hp' => '08121111111',
            'password' => Hash::make('password123'),
        ]);
        $loginA = $this->postJson('/api/auth/login', [
            'identifier' => $wargaA->email,
            'password' => 'password123',
            'device_token' => $deviceToken,
        ])->assertOk();

        $tokenA = $loginA->json('data.token');
        $this->withHeader('Authorization', "Bearer $tokenA")
            ->getJson('/api/warga/laporan')
            ->assertOk()
            ->assertJsonCount(0, 'data.data');

        $this->withHeader('Authorization', "Bearer $tokenA")
            ->postJson('/api/device-tokens', [
                'token' => $deviceToken,
                'platform' => 'android',
            ])->assertCreated();

        $this->getJson('/api/laporan/perangkat?device_token=' . $deviceToken)
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.kode_tiket', $report->kode_tiket);
        $this->getJson(
            '/api/laporan/' . $report->kode_tiket . '?device_token=' . $deviceToken,
            ['Authorization' => '']
        )
            ->assertOk();

        [, $pelaporB, $tokenB] = $this->createWarga('claim-b@example.com', 'Warga B');
        $this->withHeader('Authorization', "Bearer $tokenB")
            ->postJson('/api/device-tokens', [
                'token' => $deviceToken,
                'platform' => 'android',
            ])->assertCreated();

        $this->assertDatabaseHas('device_tokens', [
            'token_hash' => hash('sha256', $deviceToken),
            'pelapor_id' => $pelaporB->id,
        ]);
        $this->assertDatabaseHas('laporan', [
            'id' => $report->id,
            'pelapor_id' => $anonymousPelapor->id,
        ]);
        $this->withHeader('Authorization', "Bearer $tokenB")
            ->getJson('/api/warga/laporan')
            ->assertOk()
            ->assertJsonCount(0, 'data.data');
        $this->getJson('/api/laporan/perangkat?device_token=' . $deviceToken)
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.kode_tiket', $report->kode_tiket);
    }

    public function test_logged_out_report_uses_anonymous_pelapor_and_is_not_claimed_by_next_warga(): void
    {
        $deviceToken = 'device-token-new-anonymous-session';
        $claimedPelapor = Pelapor::create(['nama' => null, 'is_anonim' => true]);
        DeviceToken::create([
            'pelapor_id' => $claimedPelapor->id,
            'token' => $deviceToken,
            'token_hash' => hash('sha256', $deviceToken),
            'platform' => 'android',
        ]);
        $this->createReport($claimedPelapor->id, 'DPK-OLD-OWNER', 'Laporan Lama');

        $warga = AkunWarga::create([
            'nama' => 'Warga Lama',
            'email' => 'old-owner@example.com',
            'no_hp' => '08122222222',
            'password' => Hash::make('password123'),
        ]);
        $login = $this->postJson('/api/auth/login', [
            'identifier' => $warga->email,
            'password' => 'password123',
            'device_token' => $deviceToken,
        ])->assertOk();
        $this->withHeader('Authorization', 'Bearer ' . $login->json('data.token'))
            ->postJson('/api/device-tokens', [
                'token' => $deviceToken,
                'platform' => 'android',
            ])->assertCreated();

        $unit = UnitLayanan::firstOrCreate(
            ['kode' => 'UNIT_ANON_SESSION'],
            ['nama' => 'Unit Anonymous Session', 'is_active' => true]
        );
        $category = Kategori::firstOrCreate(['nama' => 'Kategori Anonymous Session']);
        $response = $this->postJson(
            '/api/laporan',
            [
                'unit_layanan_id' => $unit->id,
                'kategori_id' => $category->id,
                'tipe' => 'pengaduan',
                'judul' => 'Laporan Baru Anonim',
                'deskripsi' => 'Dibuat setelah logout.',
                'is_anonim' => true,
                'device_token' => $deviceToken,
            ],
            ['Authorization' => '']
        )->assertCreated();

        $newReport = Laporan::where('kode_tiket', $response->json('data.kode_tiket'))->firstOrFail();
        $this->assertNotSame($claimedPelapor->id, $newReport->pelapor_id);
        $this->assertDatabaseHas('pelapor', [
            'id' => $newReport->pelapor_id,
            'user_account_id' => null,
            'is_anonim' => true,
        ]);
        $this->assertDatabaseHas('laporan', [
            'kode_tiket' => 'DPK-OLD-OWNER',
            'pelapor_id' => $claimedPelapor->id,
        ]);

        [, $pelaporB, $tokenB] = $this->createWarga('next-warga@example.com', 'Warga Berikutnya');
        $this->withHeader('Authorization', "Bearer $tokenB")
            ->postJson('/api/device-tokens', [
                'token' => $deviceToken,
                'platform' => 'android',
            ])->assertCreated();
        $this->withHeader('Authorization', "Bearer $tokenB")
            ->getJson('/api/warga/laporan')
            ->assertOk()
            ->assertJsonCount(0, 'data.data');
        $this->assertDatabaseHas('device_tokens', [
            'token_hash' => hash('sha256', $deviceToken),
            'pelapor_id' => $pelaporB->id,
        ]);
        $this->getJson('/api/laporan/perangkat?device_token=' . $deviceToken)
            ->assertOk()
            ->assertJsonPath('data.data.0.kode_tiket', $newReport->kode_tiket);
    }
}
