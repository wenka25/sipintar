<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\Kategori;
use App\Models\Laporan;
use App\Models\UnitLayanan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReporterSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function payload(string $name, string $contact, string $token): array
    {
        $unit = UnitLayanan::firstOrCreate(['kode' => 'UNIT-SNAPSHOT'], ['nama' => 'Unit Snapshot', 'is_active' => true]);
        $category = Kategori::firstOrCreate(['nama' => 'Kategori Snapshot']);
        return [
            'unit_layanan_id' => $unit->id,
            'kategori_id' => $category->id,
            'tipe' => 'pengaduan',
            'judul' => 'Laporan ' . $name,
            'deskripsi' => 'Deskripsi laporan ' . $name,
            'nama' => $name,
            'kontak' => $contact,
            'is_anonim' => '0',
            'device_token' => $token,
        ];
    }

    public function test_reports_keep_independent_reporter_snapshots_when_device_identity_is_shared(): void
    {
        $token = 'shared-device-token';
        $first = $this->postJson('/api/laporan', $this->payload('Budi', '0811', $token))->assertCreated();
        $second = $this->postJson('/api/laporan', $this->payload('Siti', '0822', $token))->assertCreated();

        $firstCode = $first->json('data.kode_tiket');
        $secondCode = $second->json('data.kode_tiket');
        $this->assertNotSame($firstCode, $secondCode);

        $this->assertDatabaseHas('laporan', ['kode_tiket' => $firstCode, 'pelapor_nama' => 'Budi', 'pelapor_kontak' => '0811', 'is_anonim' => false]);
        $this->assertDatabaseHas('laporan', ['kode_tiket' => $secondCode, 'pelapor_nama' => 'Siti', 'pelapor_kontak' => '0822', 'is_anonim' => false]);

        $this->getJson('/api/laporan/' . $firstCode . '?device_token=' . $token)->assertOk()->assertJsonPath('data.pelapor.nama', 'Budi')->assertJsonPath('data.pelapor.kontak', '0811');
        $this->getJson('/api/laporan/' . $secondCode . '?device_token=' . $token)->assertOk()->assertJsonPath('data.pelapor.nama', 'Siti')->assertJsonPath('data.pelapor.kontak', '0822');
    }
}
