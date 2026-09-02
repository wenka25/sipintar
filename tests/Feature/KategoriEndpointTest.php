<?php

namespace Tests\Feature;

use App\Models\Kategori;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KategoriEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_kategori_endpoint_returns_master_data(): void
    {
        Kategori::create(['nama' => 'Fasilitas']);
        Kategori::create(['nama' => 'Pelayanan']);
        Kategori::create(['nama' => 'Koleksi Buku']);

        $this->getJson('/api/kategori')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.nama', 'Fasilitas')
            ->assertJsonPath('data.1.id', 2)
            ->assertJsonCount(3, 'data');
    }
}
