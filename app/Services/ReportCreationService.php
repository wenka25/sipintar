<?php

namespace App\Services;

use App\Models\AkunWarga;
use App\Models\DeviceToken;
use App\Models\Laporan;
use App\Models\Pelapor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReportCreationService
{
    public function create(Request $request, array $validated, bool $isAnonim, ?AkunWarga $authenticatedWarga, TicketService $ticketService, array &$storedFiles): Laporan
    {
        return DB::transaction(function () use ($request, $validated, $isAnonim, $authenticatedWarga, $ticketService, &$storedFiles): Laporan {
            $deviceToken = $this->findDeviceToken($validated['device_token'] ?? null);
            $pelapor = $this->resolvePelapor($validated, $isAnonim, $authenticatedWarga, $deviceToken);

            $laporan = Laporan::create([
                'kode_tiket' => $ticketService->generate(),
                'pelapor_id' => $pelapor->id,
                'pelapor_nama' => $validated['nama'] ?? $pelapor->nama,
                'pelapor_kontak' => $validated['kontak'] ?? $pelapor->kontak,
                'is_anonim' => $isAnonim,
                'unit_layanan_id' => $validated['unit_layanan_id'],
                'tipe' => $validated['tipe'],
                'kategori_id' => $validated['kategori_id'],
                'judul' => $validated['judul'],
                'deskripsi' => $validated['deskripsi'],
                'status' => 'baru',
                'sumber' => 'app',
                'cabang_perpustakaan' => $validated['cabang_perpustakaan'] ?? null,
                'dibuat_oleh_staf_id' => null,
            ]);

            $deviceToken = $this->persistDeviceToken($validated, $pelapor, $deviceToken);
            if (!$authenticatedWarga && $deviceToken) {
                // Preserve the anonymous device/report relationship even when
                // the same FCM token is later registered by a warga account.
                $laporan->deviceTokens()->syncWithoutDetaching([$deviceToken->id]);
            }
            $this->persistAttachments($request, $laporan, $storedFiles);
            $laporan->statusLogs()->create([
                'status_lama' => null,
                'status_baru' => 'baru',
                'catatan' => 'Laporan dibuat oleh warga melalui aplikasi.',
                'diubah_oleh' => null,
            ]);

            return $laporan;
        });
    }

    private function findDeviceToken(?string $token): ?DeviceToken
    {
        return $token ? DeviceToken::where('token_hash', hash('sha256', $token))->lockForUpdate()->first() : null;
    }

    private function resolvePelapor(array $validated, bool $isAnonim, ?AkunWarga $authenticatedWarga, ?DeviceToken $deviceToken): Pelapor
    {
        $pelapor = $authenticatedWarga?->pelapor()->latest('id')->first();

        // A logged-out client may reuse the FCM token of a previous warga
        // session. That token must not make a new anonymous report belong to
        // the previous account.
        if (!$pelapor && $deviceToken && $deviceToken->pelapor?->user_account_id === null) {
            $pelapor = $deviceToken->pelapor;
        }

        if (!$pelapor) {
            return Pelapor::create(['nama' => $validated['nama'] ?? null, 'kontak' => $validated['kontak'] ?? null, 'is_anonim' => $isAnonim, 'user_account_id' => $authenticatedWarga?->id]);
        }
        // Pelapor tetap menjadi identity/ownership entity. Snapshot historis
        // disimpan pada laporan dan tidak ditulis ulang ke laporan lama.
        $pelapor->update(['user_account_id' => $authenticatedWarga?->id ?? $pelapor->user_account_id]);
        return $pelapor;
    }

    private function persistDeviceToken(array $validated, Pelapor $pelapor, ?DeviceToken $deviceToken): ?DeviceToken
    {
        if (empty($validated['device_token'])) return null;
        $deviceToken ??= DeviceToken::firstOrNew(['token' => $validated['device_token']]);
        $deviceToken->fill(['pelapor_id' => $pelapor->id, 'platform' => $validated['platform'] ?? $deviceToken->platform ?? 'android', 'is_active' => true, 'last_seen_at' => now(), 'token_hash' => hash('sha256', $validated['device_token'])]);
        $deviceToken->save();

        return $deviceToken;
    }

    private function persistAttachments(Request $request, Laporan $laporan, array &$storedFiles): void
    {
        foreach ($request->file('lampiran', []) as $file) {
            $path = $file->store('laporan/' . now()->format('Y/m'), 'public');
            $storedFiles[] = $path;
            $laporan->lampiran()->create(['url_file' => Storage::disk('public')->url($path), 'tipe' => 'foto', 'nama_file' => $file->getClientOriginalName()]);
        }
    }
}
