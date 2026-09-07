<?php

namespace Tests\Feature;

use App\Models\AkunWarga;
use App\Models\DeviceToken;
use App\Models\Kategori;
use App\Models\Laporan;
use App\Models\Pelapor;
use App\Models\UnitLayanan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class AdminDeleteLaporanTest extends TestCase
{
    use RefreshDatabase;

    private function staffToken(string $role): string
    {
        $user = User::create([
            'name' => ucfirst($role),
            'email' => "$role@example.com",
            'password' => Hash::make('password123'),
            'role' => $role,
        ]);

        return JWTAuth::customClaims([
            'account_type' => 'staff',
            'role' => $role,
        ])->fromUser($user);
    }

    private function wargaToken(): string
    {
        $warga = AkunWarga::create([
            'nama' => 'Warga',
            'email' => 'warga@example.com',
            'password' => Hash::make('password123'),
        ]);

        return JWTAuth::customClaims([
            'account_type' => 'warga',
            'role' => 'warga',
        ])->fromUser($warga);
    }

    private function report(bool $anonymous = false): Laporan
    {
        $pelapor = Pelapor::create(['nama' => 'Pelapor', 'is_anonim' => $anonymous]);
        $kategori = Kategori::firstOrCreate(['nama' => 'Umum']);
        $unit = UnitLayanan::firstOrCreate(
            ['kode' => 'UNIT-TEST'],
            ['nama' => 'Unit Test', 'is_active' => true]
        );

        return Laporan::create([
            'kode_tiket' => 'DPK-DEL-' . uniqid(),
            'pelapor_id' => $pelapor->id,
            'pelapor_nama' => 'Pelapor',
            'is_anonim' => $anonymous,
            'unit_layanan_id' => $unit->id,
            'tipe' => 'pengaduan',
            'kategori_id' => $kategori->id,
            'judul' => 'Laporan untuk dihapus',
            'deskripsi' => 'Data pengujian',
            'status' => 'baru',
            'sumber' => 'app',
        ]);
    }

    public function test_admin_deletes_report_relations_pivot_and_attachment_file(): void
    {
        Storage::fake('public');
        $laporan = $this->report(true);
        $path = 'laporan/2026/08/bukti.jpg';
        Storage::disk('public')->put($path, 'file');
        $laporan->lampiran()->create([
            'url_file' => Storage::url($path),
            'tipe' => 'foto',
            'nama_file' => 'bukti.jpg',
        ]);
        $laporan->statusLogs()->create(['status_baru' => 'baru']);
        $staff = User::create(['name' => 'Petugas', 'email' => 'reply@example.com', 'password' => Hash::make('password123'), 'role' => 'petugas']);
        $laporan->balasan()->create(['isi_balasan' => 'Balasan', 'staf_id' => $staff->id]);
        $deviceToken = DeviceToken::create(['token' => 'token-test', 'token_hash' => hash('sha256', 'token-test'), 'platform' => 'android', 'is_active' => true]);
        $laporan->deviceTokens()->attach($deviceToken->id);
        $otherReport = $this->report();

        $this->withHeader('Authorization', 'Bearer ' . $this->staffToken('admin'))
            ->deleteJson("/api/admin/laporan/{$laporan->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Laporan berhasil dihapus.');

        $this->assertDatabaseMissing('laporan', ['id' => $laporan->id]);
        $this->assertDatabaseCount('lampiran', 0);
        $this->assertDatabaseCount('status_logs', 0);
        $this->assertDatabaseCount('balasan', 0);
        $this->assertDatabaseCount('laporan_device_tokens', 0);
        $this->assertDatabaseHas('device_tokens', ['id' => $deviceToken->id]);
        $this->assertDatabaseHas('laporan', ['id' => $otherReport->id]);
        $this->assertFalse(Storage::disk('public')->exists($path));
    }

    public function test_only_admin_can_delete_and_missing_report_returns_not_found(): void
    {
        $laporan = $this->report();
        $url = "/api/admin/laporan/{$laporan->id}";

        $this->deleteJson($url)->assertUnauthorized();
        $this->withHeader('Authorization', 'Bearer ' . $this->staffToken('petugas'))->deleteJson($url)->assertForbidden();
        $this->withHeader('Authorization', 'Bearer ' . $this->wargaToken())->deleteJson($url)->assertForbidden();
        $this->withHeader('Authorization', 'Bearer ' . $this->staffToken('admin'))->deleteJson('/api/admin/laporan/99999')->assertNotFound();
        $this->assertDatabaseHas('laporan', ['id' => $laporan->id]);
    }
}
