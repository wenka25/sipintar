<?php

namespace Tests\Feature;

use App\Models\AkunWarga;
use App\Models\Kategori;
use App\Models\Laporan;
use App\Models\Pelapor;
use App\Models\UnitLayanan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class MultiUnitLayananTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): array
    {
        $admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);
        $token = Auth::guard('api')->login($admin);
        return [$admin, $token];
    }

    private function createPetugas(string $email = 'petugas@test.com'): array
    {
        $petugas = User::create([
            'name' => 'Petugas Test',
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => 'petugas',
        ]);
        $token = Auth::guard('api')->login($petugas);
        return [$petugas, $token];
    }

    private function createWarga(): array
    {
        $warga = AkunWarga::create([
            'nama' => 'Warga Test',
            'email' => 'warga@test.com',
            'no_hp' => '081299999999',
            'password' => Hash::make('password123'),
        ]);
        $pelapor = $warga->pelapor()->create([
            'nama' => $warga->nama,
            'kontak' => $warga->no_hp,
            'is_anonim' => false,
        ]);
        $token = JWTAuth::customClaims([
            'account_type' => 'warga',
            'role' => 'warga',
            'pelapor_id' => $pelapor->id,
        ])->fromUser($warga);
        return [$warga, $token];
    }

    public function test_public_unit_endpoint_returns_only_active_units(): void
    {
        UnitLayanan::create([
            'kode' => 'PERPUS_UM',
            'nama' => 'Perpustakaan',
            'is_active' => true,
        ]);
        UnitLayanan::create([
            'kode' => 'ARSIP_NONAKTIF',
            'nama' => 'Arsip Nonaktif',
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/unit-layanan');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.kode', 'PERPUS_UM');
    }

    public function test_unit_names_and_codes_are_preserved_as_expected(): void
    {
        $perpustakaan = UnitLayanan::create(['kode' => 'PERPUS_UM', 'nama' => 'Perpustakaan', 'is_active' => true]);
        $kearsipan = UnitLayanan::create(['kode' => 'ARSIP_UM', 'nama' => 'Kearsipan', 'is_active' => true]);
        $um = UnitLayanan::create(['kode' => 'LAYAN_PERPUS', 'nama' => 'Umum', 'is_active' => true]);

        $this->getJson('/api/unit-layanan')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonFragment(['id' => $perpustakaan->id, 'kode' => 'PERPUS_UM', 'nama' => 'Perpustakaan'])
            ->assertJsonFragment(['id' => $kearsipan->id, 'kode' => 'ARSIP_UM', 'nama' => 'Kearsipan'])
            ->assertJsonFragment(['id' => $um->id, 'kode' => 'LAYAN_PERPUS', 'nama' => 'Umum']);
    }

    public function test_create_laporan_with_valid_active_unit_succeeds(): void
    {
        $kategori = Kategori::create(['nama' => 'Fasilitas']);
        $unit = UnitLayanan::create([
            'kode' => 'PERPUS_UM',
            'nama' => 'Perpustakaan',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/laporan', [
            'unit_layanan_id' => $unit->id,
            'tipe' => 'pengaduan',
            'kategori_id' => $kategori->id,
            'judul' => 'AC Ruang Baca Mati',
            'deskripsi' => 'Suhu ruangan terlalu panas',
            'nama' => 'Budi',
            'kontak' => '081234567890',
            'is_anonim' => true,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('laporan', [
            'unit_layanan_id' => $unit->id,
            'judul' => 'AC Ruang Baca Mati',
        ]);
    }

    public function test_create_laporan_with_inactive_or_missing_unit_is_rejected(): void
    {
        $kategori = Kategori::create(['nama' => 'Fasilitas']);
        $inactiveUnit = UnitLayanan::create([
            'kode' => 'INACTIVE_UNIT',
            'nama' => 'Unit Nonaktif',
            'is_active' => false,
        ]);

        $responseInactive = $this->postJson('/api/laporan', [
            'unit_layanan_id' => $inactiveUnit->id,
            'tipe' => 'pengaduan',
            'kategori_id' => $kategori->id,
            'judul' => 'Test Inactive',
            'deskripsi' => 'Test Inactive Desc',
        ]);
        $responseInactive->assertUnprocessable();

        $responseMissing = $this->postJson('/api/laporan', [
            'unit_layanan_id' => 99999,
            'tipe' => 'pengaduan',
            'kategori_id' => $kategori->id,
            'judul' => 'Test Missing',
            'deskripsi' => 'Test Missing Desc',
        ]);
        $responseMissing->assertUnprocessable();
    }

    public function test_admin_can_manage_petugas_unit_assignments(): void
    {
        [$admin, $adminToken] = $this->createAdmin();
        [$petugas, $petugasToken] = $this->createPetugas();
        $unit = UnitLayanan::create([
            'kode' => 'PERPUS_UM',
            'nama' => 'Perpustakaan',
            'is_active' => true,
        ]);

        $assignResponse = $this->withHeader('Authorization', "Bearer $adminToken")
            ->postJson("/api/admin/users/{$petugas->id}/unit-layanan", [
                'unit_layanan_id' => $unit->id,
            ]);
        $assignResponse->assertOk();
        $this->assertDatabaseHas('user_unit_layanan', [
            'user_id' => $petugas->id,
            'unit_layanan_id' => $unit->id,
        ]);

        // Duplicate assignment is safely idempotent
        $duplicateResponse = $this->withHeader('Authorization', "Bearer $adminToken")
            ->postJson("/api/admin/users/{$petugas->id}/unit-layanan", [
                'unit_layanan_id' => $unit->id,
            ]);
        $duplicateResponse->assertOk();
        $this->assertDatabaseCount('user_unit_layanan', 1);

        // Remove assignment
        $deleteResponse = $this->withHeader('Authorization', "Bearer $adminToken")
            ->deleteJson("/api/admin/users/{$petugas->id}/unit-layanan/{$unit->id}");
        $deleteResponse->assertOk();
        $this->assertDatabaseCount('user_unit_layanan', 0);
    }

    public function test_petugas_and_warga_cannot_assign_units(): void
    {
        [$petugas, $petugasToken] = $this->createPetugas();
        [$warga, $wargaToken] = $this->createWarga();
        $unit = UnitLayanan::create([
            'kode' => 'PERPUS_UM',
            'nama' => 'Perpustakaan',
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', "Bearer $petugasToken")
            ->postJson("/api/admin/users/{$petugas->id}/unit-layanan", [
                'unit_layanan_id' => $unit->id,
            ])
            ->assertForbidden();

        $this->withHeader('Authorization', "Bearer $wargaToken")
            ->postJson("/api/admin/users/{$petugas->id}/unit-layanan", [
                'unit_layanan_id' => $unit->id,
            ])
            ->assertForbidden();
    }

    public function test_petugas_is_isolated_to_assigned_unit_reports(): void
    {
        $kategori = Kategori::create(['nama' => 'Umum']);
        $unitA = UnitLayanan::create(['kode' => 'UNIT_A', 'nama' => 'Unit Layanan A', 'is_active' => true]);
        $unitB = UnitLayanan::create(['kode' => 'UNIT_B', 'nama' => 'Unit Layanan B', 'is_active' => true]);

        [$petugasA, $tokenPetugasA] = $this->createPetugas('petugasa@test.com');
        $petugasA->unitLayanan()->attach($unitA->id);

        $pelapor = Pelapor::create(['nama' => 'Pelapor A', 'is_anonim' => false]);
        $laporanA = Laporan::create([
            'kode_tiket' => 'DPK-TEST-A1',
            'pelapor_id' => $pelapor->id,
            'unit_layanan_id' => $unitA->id,
            'tipe' => 'pengaduan',
            'kategori_id' => $kategori->id,
            'judul' => 'Laporan Unit A',
            'deskripsi' => 'Deskripsi A',
            'status' => 'baru',
            'sumber' => 'app',
        ]);

        $laporanB = Laporan::create([
            'kode_tiket' => 'DPK-TEST-B1',
            'pelapor_id' => $pelapor->id,
            'unit_layanan_id' => $unitB->id,
            'tipe' => 'pengaduan',
            'kategori_id' => $kategori->id,
            'judul' => 'Laporan Unit B',
            'deskripsi' => 'Deskripsi B',
            'status' => 'baru',
            'sumber' => 'app',
        ]);

        // Index filter: only unit A appears
        $indexResponse = $this->withHeader('Authorization', "Bearer $tokenPetugasA")
            ->getJson('/api/admin/laporan');
        $indexResponse->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.kode_tiket', 'DPK-TEST-A1');

        // Attempting to bypass by specifying unit_layanan_id=UnitB
        $bypassResponse = $this->withHeader('Authorization', "Bearer $tokenPetugasA")
            ->getJson("/api/admin/laporan?unit_layanan_id={$unitB->id}");
        $bypassResponse->assertOk()
            ->assertJsonCount(0, 'data.data');

        // Show detail
        $this->withHeader('Authorization', "Bearer $tokenPetugasA")
            ->getJson("/api/admin/laporan/{$laporanA->id}")
            ->assertOk();

        $this->withHeader('Authorization', "Bearer $tokenPetugasA")
            ->getJson("/api/admin/laporan/{$laporanB->id}")
            ->assertForbidden();

        // Update status
        $this->withHeader('Authorization', "Bearer $tokenPetugasA")
            ->putJson("/api/admin/laporan/{$laporanA->id}/status", [
                'status' => 'diproses',
                'catatan' => 'Diproses petugas A',
            ])
            ->assertOk();

        $this->withHeader('Authorization', "Bearer $tokenPetugasA")
            ->putJson("/api/admin/laporan/{$laporanB->id}/status", [
                'status' => 'diproses',
                'catatan' => 'Coba ubah unit B',
            ])
            ->assertForbidden();

        // Store reply
        $this->withHeader('Authorization', "Bearer $tokenPetugasA")
            ->postJson("/api/admin/laporan/{$laporanA->id}/balasan", [
                'isi_balasan' => 'Balasan resmi dari Unit A',
            ])
            ->assertCreated();

        $this->withHeader('Authorization', "Bearer $tokenPetugasA")
            ->postJson("/api/admin/laporan/{$laporanB->id}/balasan", [
                'isi_balasan' => 'Balasan ilegal ke Unit B',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_access_and_filter_all_units(): void
    {
        $kategori = Kategori::create(['nama' => 'Umum']);
        $unitA = UnitLayanan::create(['kode' => 'UNIT_A', 'nama' => 'Unit Layanan A', 'is_active' => true]);
        $unitB = UnitLayanan::create(['kode' => 'UNIT_B', 'nama' => 'Unit Layanan B', 'is_active' => true]);

        [$admin, $adminToken] = $this->createAdmin();

        $pelapor = Pelapor::create(['nama' => 'Pelapor Admin', 'is_anonim' => false]);
        $laporanA = Laporan::create([
            'kode_tiket' => 'DPK-ADM-A',
            'pelapor_id' => $pelapor->id,
            'unit_layanan_id' => $unitA->id,
            'tipe' => 'pengaduan',
            'kategori_id' => $kategori->id,
            'judul' => 'Laporan Unit A',
            'deskripsi' => 'Deskripsi A',
            'status' => 'baru',
            'sumber' => 'app',
        ]);

        $laporanB = Laporan::create([
            'kode_tiket' => 'DPK-ADM-B',
            'pelapor_id' => $pelapor->id,
            'unit_layanan_id' => $unitB->id,
            'tipe' => 'pengaduan',
            'kategori_id' => $kategori->id,
            'judul' => 'Laporan Unit B',
            'deskripsi' => 'Deskripsi B',
            'status' => 'baru',
            'sumber' => 'app',
        ]);

        // Admin index sees both
        $this->withHeader('Authorization', "Bearer $adminToken")
            ->getJson('/api/admin/laporan')
            ->assertOk()
            ->assertJsonCount(2, 'data.data');

        // Admin filters unit B
        $this->withHeader('Authorization', "Bearer $adminToken")
            ->getJson("/api/admin/laporan?unit_layanan_id={$unitB->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.kode_tiket', 'DPK-ADM-B');

        // Admin can access both details and update both
        $this->withHeader('Authorization', "Bearer $adminToken")
            ->getJson("/api/admin/laporan/{$laporanB->id}")
            ->assertOk();

        $this->withHeader('Authorization', "Bearer $adminToken")
            ->putJson("/api/admin/laporan/{$laporanB->id}/status", [
                'status' => 'diproses',
            ])
            ->assertOk();

        $this->withHeader('Authorization', "Bearer $adminToken")
            ->postJson("/api/admin/laporan/{$laporanA->id}/balasan", [
                'isi_balasan' => 'Balasan Admin untuk Unit A',
            ])
            ->assertCreated();
    }

    public function test_admin_ticket_search_trims_is_case_insensitive_and_keeps_pagination(): void
    {
        $kategori = Kategori::create(['nama' => 'Umum']);
        $unit = UnitLayanan::create(['kode' => 'UNIT_SEARCH', 'nama' => 'Unit Search', 'is_active' => true]);
        [, $adminToken] = $this->createAdmin();
        $pelapor = Pelapor::create(['nama' => 'Pelapor Search', 'is_anonim' => false]);

        foreach (['DPK-20260908-AB12CD', 'DPK-20260908-AB12EF', 'DPK-20260909-ZZ9999'] as $index => $kode) {
            Laporan::create([
                'kode_tiket' => $kode,
                'pelapor_id' => $pelapor->id,
                'unit_layanan_id' => $unit->id,
                'tipe' => 'pengaduan',
                'kategori_id' => $kategori->id,
                'judul' => "Laporan Search {$index}",
                'deskripsi' => 'Deskripsi Search',
                'status' => 'baru',
                'sumber' => 'app',
            ]);
        }

        $this->withHeader('Authorization', "Bearer $adminToken")
            ->getJson('/api/admin/laporan?kode_tiket=%20dpk-20260908-ab12%20&per_page=1')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.per_page', 1)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['kode_tiket' => 'DPK-20260908-AB12EF']);

        $this->withHeader('Authorization', "Bearer $adminToken")
            ->getJson('/api/admin/laporan?kode_tiket=DPK-NOT-FOUND')
            ->assertOk()
            ->assertJsonCount(0, 'data.data');

        $this->withHeader('Authorization', "Bearer $adminToken")
            ->getJson('/api/admin/laporan?kode_tiket=%20%20')
            ->assertOk()
            ->assertJsonPath('data.total', 3);
    }

    public function test_petugas_ticket_search_cannot_escape_unit_isolation(): void
    {
        $kategori = Kategori::create(['nama' => 'Umum']);
        $unitA = UnitLayanan::create(['kode' => 'UNIT_SEARCH_A', 'nama' => 'Unit A', 'is_active' => true]);
        $unitB = UnitLayanan::create(['kode' => 'UNIT_SEARCH_B', 'nama' => 'Unit B', 'is_active' => true]);
        [, $petugasToken] = $this->createPetugas('search-petugas@test.com');
        $petugas = User::where('email', 'search-petugas@test.com')->firstOrFail();
        $petugas->unitLayanan()->attach($unitA->id);
        $pelapor = Pelapor::create(['nama' => 'Pelapor Isolation', 'is_anonim' => false]);

        Laporan::create([
            'kode_tiket' => 'DPK-SEARCH-A', 'pelapor_id' => $pelapor->id, 'unit_layanan_id' => $unitA->id,
            'tipe' => 'pengaduan', 'kategori_id' => $kategori->id, 'judul' => 'A', 'deskripsi' => 'A',
            'status' => 'baru', 'sumber' => 'app',
        ]);
        Laporan::create([
            'kode_tiket' => 'DPK-SEARCH-B', 'pelapor_id' => $pelapor->id, 'unit_layanan_id' => $unitB->id,
            'tipe' => 'pengaduan', 'kategori_id' => $kategori->id, 'judul' => 'B', 'deskripsi' => 'B',
            'status' => 'baru', 'sumber' => 'app',
        ]);

        $this->withHeader('Authorization', "Bearer $petugasToken")
            ->getJson('/api/admin/laporan?kode_tiket=DPK-SEARCH-B')
            ->assertOk()
            ->assertJsonCount(0, 'data.data');
    }
}
