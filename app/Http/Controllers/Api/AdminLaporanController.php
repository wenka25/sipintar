<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Laporan;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use App\Services\NotificationSender;
use App\Services\StatusTransitionService;
use Illuminate\Support\Facades\Storage;
use App\Services\AdminLaporanQueryService;

class AdminLaporanController extends Controller
{
    public function index(Request $request, AdminLaporanQueryService $reports): JsonResponse
    {
        $request->validate([
            'unit_layanan_id' => ['nullable', 'integer', 'exists:unit_layanan,id'],
            'status' => ['nullable', 'string', 'in:baru,diproses,selesai,ditolak'],
            'kategori_id' => ['nullable', 'integer', 'exists:kategori,id'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_akhir' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ]);
        $query = $reports->build($request, $request->user());

        $summary = (clone $query)
            ->reorder()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $laporan = $query->paginate(
            $request->integer('per_page', 10)
        );

        return response()->json([
            'success' => true,
            'data' => $laporan,
            'summary' => [
                'total' => $summary->sum(),
                'baru' => (int) ($summary['baru'] ?? 0),
                'diproses' => (int) ($summary['diproses'] ?? 0),
                'selesai' => (int) ($summary['selesai'] ?? 0),
                'ditolak' => (int) ($summary['ditolak'] ?? 0),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $laporan = Laporan::with([
            'kategori',
            'unitLayanan',
            'pelapor',
            'lampiran',
            'statusLogs',
            'balasan',
        ])->find($id);

        if (!$laporan) {
            return response()->json([
                'success' => false,
                'message' => 'Laporan tidak ditemukan.',
            ], 404);
        }
        $this->ensureCanAccess($laporan, $request->user());

        // Pertahankan bentuk response lama untuk Flutter, tetapi isi relasi
        // display dengan snapshot laporan, bukan identity Pelapor saat ini.
        if ($laporan->pelapor) {
            $laporan->pelapor->setAttribute('nama', $laporan->pelapor_nama);
            $laporan->pelapor->setAttribute('kontak', $laporan->pelapor_kontak);
            $laporan->pelapor->setAttribute('is_anonim', (bool) $laporan->is_anonim);
        }

        $laporan->setAttribute('lampiran', $laporan->lampiran->map(function ($item) use ($request) {
            return [
                'id' => $item->id,
                'url' => $this->attachmentUrl($item->url_file, $request),
                'nama_file' => $item->nama_file,
                'tipe' => $item->tipe,
            ];
        })->values());

        return response()->json([
            'success' => true,
            'data' => $laporan,
        ]);
    }

    private function attachmentUrl(string $storedUrl, Request $request): string
    {
        $path = parse_url($storedUrl, PHP_URL_PATH) ?: $storedUrl;
        $path = '/' . ltrim(preg_replace('#^/storage/#', '', $path), '/');

        return rtrim($request->getSchemeAndHttpHost(), '/') . '/storage' . $path;
    }

public function updateStatus(
    Request $request,
    int $id,
    NotificationSender $notificationSender,
    StatusTransitionService $statusTransitionService
): JsonResponse {
    $validated = $request->validate([
        'status' => [
            'required',
            Rule::in([
                'baru',
                'diproses',
                'selesai',
                'ditolak',
            ]),
        ],
        'catatan' => [
            'nullable',
            'string',
            'max:1000',
        ],
    ]);

    $laporan = Laporan::with(['pelapor.deviceTokens', 'deviceTokens'])->find($id);

    if (!$laporan) {
        return response()->json([
            'success' => false,
            'message' => 'Laporan tidak ditemukan.',
        ], 404);
    }
    $this->ensureCanAccess($laporan, $request->user());

    $statusLama = $laporan->status;
    $statusBaru = $validated['status'];

    // Kalau status tidak berubah
    if ($statusLama === $statusBaru) {
        return response()->json([
            'success' => false,
            'message' => 'Status laporan sudah berada pada status tersebut.',
        ], 422);
    }

    [$statusLama, $statusLog] = $statusTransitionService->transition(
        $laporan,
        $statusBaru,
        $validated['catatan'] ?? null,
        auth('api')->id()
    );

    // ==========================================
    // KIRIM NOTIFIKASI FCM
    // ==========================================

    $notificationSender->sendToLaporan(
        $laporan,
        'Status Laporan DPK Berubah',
        "Laporan {$laporan->kode_tiket} sekarang berstatus {$statusBaru}.",
        [
            'type' => 'laporan_status',
            'laporan_id' => (string) $laporan->id,
            'kode_tiket' => $laporan->kode_tiket,
            'status' => $statusBaru,
        ]
    );

    return response()->json([
        'success' => true,
        'message' => 'Status laporan berhasil diperbarui.',
        'data' => [
            'laporan' => [
                'id' => $laporan->id,
                'kode_tiket' => $laporan->kode_tiket,
                'status' => $laporan->status,
                'updated_at' => $laporan->updated_at,
            ],
            'status_log' => [
                'id' => $statusLog->id,
                'status_lama' => $statusLog->status_lama,
                'status_baru' => $statusLog->status_baru,
                'catatan' => $statusLog->catatan,
                'diubah_oleh' => $statusLog->diubah_oleh,
                'created_at' => $statusLog->created_at,
            ],
        ],
    ], 200);
}

    public function destroy(int $id): JsonResponse
    {
        $attachmentPaths = DB::transaction(function () use ($id): array {
            $laporan = Laporan::with('lampiran')->find($id);

            if (!$laporan) {
                abort(404, 'Laporan tidak ditemukan.');
            }

            // url_file menyimpan URL disk public (mis. /storage/laporan/...).
            // Normalisasi kembali ke path disk sebelum menghapus file fisik.
            $attachmentPaths = $laporan->lampiran
                ->pluck('url_file')
                ->map(function (string $storedUrl): string {
                    $path = parse_url($storedUrl, PHP_URL_PATH) ?: $storedUrl;
                    return ltrim(preg_replace('#^/storage/#', '', $path), '/');
                })
                ->filter()
                ->values()
                ->all();

            // Foreign key existing menghapus lampiran, status log, balasan,
            // dan pivot laporan_device_tokens secara cascade. Device token
            // global tidak disentuh.
            $laporan->delete();

            return $attachmentPaths;
        });

        foreach ($attachmentPaths as $path) {
            Storage::disk('public')->delete($path);
        }

        return response()->json([
            'success' => true,
            'message' => 'Laporan berhasil dihapus.',
        ], 200);
    }

    public function storeBalasan(
        Request $request,
        int $id,
        NotificationSender $notificationSender
    ): JsonResponse
    {
        $validated = $request->validate([
            'isi_balasan' => [
                'required',
                'string',
                'max:5000',
            ],
        ]);

        $laporan = Laporan::with(['pelapor', 'deviceTokens'])->find($id);

        if (!$laporan) {
            return response()->json([
                'success' => false,
                'message' => 'Laporan tidak ditemukan.',
            ], 404);
        }
        $this->ensureCanAccess($laporan, $request->user());

        $balasan = $laporan->balasan()->create([
            'isi_balasan' => $validated['isi_balasan'],
            'staf_id' => auth('api')->id(),
        ]);

        $notificationSender->sendToLaporan(
            $laporan,
            'Balasan Baru dari DPK',
            "Staf telah memberikan balasan untuk laporan {$laporan->kode_tiket}.",
            [
                'type' => 'laporan_balasan',
                'laporan_id' => (string) $laporan->id,
                'kode_tiket' => $laporan->kode_tiket,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Balasan berhasil dikirim.',
            'data' => [
                'id' => $balasan->id,
                'laporan_id' => $balasan->laporan_id,
                'isi_balasan' => $balasan->isi_balasan,
                'staf_id' => $balasan->staf_id,
                'created_at' => $balasan->created_at,
            ],
        ], 201);
    }

    private function restrictToAssignedUnits(Builder $query, ?User $user): void
    {
        if ($user?->role === 'petugas') {
            $assignedUnitIds = $user->unitLayanan()->pluck('unit_layanan.id');
            $query->whereIn('unit_layanan_id', $assignedUnitIds);
        }
    }

    private function ensureCanAccess(Laporan $laporan, ?User $user): void
    {
        if ($user?->role === 'admin') {
            return;
        }

        if ($user?->role === 'petugas' && $laporan->unit_layanan_id !== null) {
            $isAssigned = $user->unitLayanan()->where('unit_layanan.id', $laporan->unit_layanan_id)->exists();
            if ($isAssigned) {
                return;
            }
        }

        abort(403, 'Anda tidak memiliki akses ke unit laporan ini.');
    }
}
