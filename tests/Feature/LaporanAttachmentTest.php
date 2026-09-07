<?php

namespace Tests\Feature;

use App\Models\AkunWarga;
use App\Models\Kategori;
use App\Models\Laporan;
use App\Models\Pelapor;
use App\Models\Lampiran;
use App\Models\UnitLayanan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LaporanAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        $unit = UnitLayanan::create(['kode' => 'UNIT-FOTO', 'nama' => 'Unit Foto', 'is_active' => true]);
        $category = Kategori::create(['nama' => 'Kategori Foto']);
        return ['unit_layanan_id' => $unit->id, 'kategori_id' => $category->id, 'tipe' => 'pengaduan', 'judul' => 'Laporan Foto', 'deskripsi' => 'Dengan bukti'];
    }

    private function wargaToken(): string
    {
        $warga = AkunWarga::create(['nama' => 'Warga Foto', 'email' => 'foto@example.com', 'password' => Hash::make('password123')]);
        $pelapor = $warga->pelapor()->create(['nama' => 'Warga Foto', 'is_anonim' => false]);
        return \Tymon\JWTAuth\Facades\JWTAuth::customClaims(['account_type' => 'warga', 'role' => 'warga'])->fromUser($warga);
    }

    public function test_report_without_attachment_still_succeeds(): void
    {
        Storage::fake('public');
        $this->postJson('/api/laporan', $this->payload())->assertCreated();
        $this->assertDatabaseCount('lampiran', 0);
    }

    public function test_all_new_report_types_are_accepted_and_old_type_is_rejected(): void
    {
        $payload = $this->payload();
        foreach (['pengaduan', 'aspirasi', 'permintaan_informasi'] as $type) {
            $response = $this->postJson('/api/laporan', array_merge($payload, [
                'tipe' => $type,
            ]));
            $response->assertCreated()->assertJsonPath('success', true);
        }

        $this->postJson('/api/laporan', array_merge($payload, [
            'tipe' => 'pertanyaan',
        ]))->assertUnprocessable();
    }

    public function test_report_with_three_images_stores_attachments_and_detail_returns_them(): void
    {
        Storage::fake('public');
        $response = $this->post('/api/laporan', array_merge($this->payload(), [
            'is_anonim' => '1',
            'lampiran' => [UploadedFile::fake()->image('one.jpg'), UploadedFile::fake()->image('two.png'), UploadedFile::fake()->image('three.webp')],
        ]), ['Accept' => 'application/json']);
        $response->assertCreated()->assertJsonCount(3, 'data.lampiran');
        $this->assertDatabaseCount('lampiran', 3);
        foreach (Lampiran::pluck('url_file') as $url) {
            Storage::disk('public')->assertExists(
                str_replace('/storage/', '', parse_url($url, PHP_URL_PATH) ?? '')
            );
        }
    }

    public function test_anonymous_report_keeps_reporter_identity_internally(): void
    {
        Storage::fake('public');
        $this->post('/api/laporan', array_merge($this->payload(), [
            'nama' => 'Wenka Salinding',
            'kontak' => '081234567890',
            'is_anonim' => '1',
        ]), ['Accept' => 'application/json'])->assertCreated();

        $this->assertDatabaseHas('pelapor', [
            'nama' => 'Wenka Salinding',
            'kontak' => '081234567890',
            'is_anonim' => true,
        ]);
    }

    public function test_multipart_boolean_false_is_accepted_for_authenticated_style_submission(): void
    {
        Storage::fake('public');
        $this->post('/api/laporan', array_merge($this->payload(), [
            'nama' => 'Wenka Salinding',
            'kontak' => '081234567890',
            'is_anonim' => '0',
        ]), ['Accept' => 'application/json'])->assertCreated();

        $this->assertDatabaseHas('pelapor', [
            'nama' => 'Wenka Salinding',
            'kontak' => '081234567890',
            'is_anonim' => false,
        ]);
    }

    public function test_pdf_and_image_combination_is_stored_with_correct_types(): void
    {
        Storage::fake('public');
        $response = $this->post('/api/laporan', array_merge($this->payload(), [
            'lampiran' => [
                UploadedFile::fake()->image('foto.jpg'),
                UploadedFile::fake()->create('surat.pdf', 10, 'application/pdf'),
            ],
        ]), ['Accept' => 'application/json']);

        $response->assertCreated()->assertJsonCount(2, 'data.lampiran');
        $this->assertDatabaseHas('lampiran', ['nama_file' => 'surat.pdf', 'tipe' => 'dokumen']);
        $this->assertDatabaseHas('lampiran', ['nama_file' => 'foto.jpg', 'tipe' => 'foto']);
    }

    public function test_invalid_and_more_than_three_attachments_are_rejected(): void
    {
        Storage::fake('public');
        $payload = $this->payload();
        $this->post('/api/laporan', array_merge($payload, ['lampiran' => [UploadedFile::fake()->create('bad.exe', 10, 'application/octet-stream')]]), ['Accept' => 'application/json'])->assertUnprocessable();
        $this->post('/api/laporan', array_merge($payload, ['lampiran' => [UploadedFile::fake()->image('1.jpg'), UploadedFile::fake()->image('2.jpg'), UploadedFile::fake()->image('3.jpg'), UploadedFile::fake()->create('4.pdf', 10, 'application/pdf')]]), ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_five_mb_limit_is_enforced_for_images_and_pdfs(): void
    {
        $payload = $this->payload();
        foreach ([['large.jpg', 'image/jpeg'], ['large.pdf', 'application/pdf']] as [$name, $mime]) {
            $this->post('/api/laporan', array_merge($payload, ['lampiran' => [UploadedFile::fake()->create($name, 5121, $mime)]]), ['Accept' => 'application/json'])->assertUnprocessable();
        }
    }
}
