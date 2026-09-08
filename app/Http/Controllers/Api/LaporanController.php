<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\Laporan;
use App\Models\Pelapor;
use App\Models\AkunWarga;
use App\Services\FirebaseService;
use App\Services\TicketService;
use App\Services\ReportCreationService;
use App\Services\StatusTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Models\UnitLayanan;
use Illuminate\Support\Facades\Storage;
use Throwable;

class LaporanController extends Controller
{
    public function store(
        Request $request,
        TicketService $ticketService
    ): JsonResponse {
        // Multipart membawa field primitive sebagai string. Normalisasi hanya
        // representasi boolean yang dikenal; nilai lain tetap ditolak oleh rule.
        if ($request->has('is_anonim')) {
            $rawIsAnonim = $request->input('is_anonim');
            if ($rawIsAnonim === true || $rawIsAnonim === false) {
                $request->merge(['is_anonim' => $rawIsAnonim]);
            } elseif ($rawIsAnonim === '1' || $rawIsAnonim === 1 || $rawIsAnonim === 'true') {
                $request->merge(['is_anonim' => true]);
            } elseif ($rawIsAnonim === '0' || $rawIsAnonim === 0 || $rawIsAnonim === 'false') {
                $request->merge(['is_anonim' => false]);
            }
        }

        $validated = $request->validate([
            'unit_layanan_id' => [
            'required',
            'integer',
            Rule::exists('unit_layanan', 'id')->where('is_active', true),
        ],
            'tipe' => [
                'required',
                Rule::in([
                    'pengaduan',
                    'aspirasi',
                    'permintaan_informasi',
                ]),
            ],

            'kategori_id' => [
                'required',
                'integer',
                'exists:kategori,id',
            ],

            'judul' => [
                'required',
                'string',
                'max:255',
            ],

            'deskripsi' => [
                'required',
                'string',
            ],

            'cabang_perpustakaan' => [
                'nullable',
                'string',
                'max:255',
            ],

            'nama' => [
                'nullable',
                'string',
                'max:255',
            ],

            'kontak' => [
                'nullable',
                'string',
                'max:100',
            ],

            'is_anonim' => [
                'boolean',
            ],

            'device_token' => [
                'nullable',
                'string',
                'max:512',
            ],

                'platform' => [
                    'nullable',
                    Rule::in(['android', 'ios']),
                ],

                'lampiran' => [
                    'nullable',
                    'array',
                    'max:3',
                ],

                'lampiran.*' => [
                    'file',
                    'mimes:jpg,jpeg,png,webp,pdf',
                    'mimetypes:image/jpeg,image/png,image/webp,application/pdf',
                    'max:5120',
                ],
            ]);

        $isAnonim = $validated['is_anonim'] ?? true;
        $authenticatedWarga = $request->user() instanceof AkunWarga
            ? $request->user()
            : null;

        $storedFiles = [];

        try {
            $laporan = app(ReportCreationService::class)->create(
                $request,
                $validated,
                $isAnonim,
                $authenticatedWarga,
                $ticketService,
                $storedFiles
            );
        } catch (Throwable $exception) {
            foreach ($storedFiles as $path) {
                Storage::disk('public')->delete($path);
            }

            throw $exception;
        }

        return response()->json([
            'success' => true,
            'message' => 'Laporan berhasil dikirim.',
            'data' => [
                'id' => $laporan->id,
                'kode_tiket' => $laporan->kode_tiket,
                'status' => $laporan->status,
                'created_at' => $laporan->created_at,
                'lampiran' => $laporan->load('lampiran')->lampiran->map(fn ($item) => [
                    'id' => $item->id,
                    'url' => $this->attachmentUrl($item->url_file, $request),
                    'nama_file' => $item->nama_file,
                    'tipe' => $item->tipe,
                ])->values(),
            ],
        ], 201);
    }

    public function anonymousIndex(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_token' => ['required', 'string', 'max:512'],
        ]);

        $deviceToken = DeviceToken::where(
            'token_hash',
            hash('sha256', $validated['device_token'])
        )->where('is_active', true)->first();

        $perPage = min(max($request->integer('per_page', 10), 1), 100);

        $laporan = Laporan::with(['kategori', 'unitLayanan', 'lampiran'])
            ->when($deviceToken, fn ($query) => $query->where(function ($query) use ($deviceToken) {
                $query->whereHas('deviceTokens', fn ($deviceTokens) =>
                    $deviceTokens->whereKey($deviceToken->id)
                );

                // Compatibility for reports created before the pivot was
                // introduced. New anonymous reports always use the relation
                // above, so a later token reassignment cannot expose warga
                // reports here.
                if ($deviceToken->pelapor_id) {
                    $query->orWhere(function ($legacyQuery) use ($deviceToken) {
                        $legacyQuery->where('pelapor_id', $deviceToken->pelapor_id)
                            ->whereHas('pelapor', fn ($pelapor) => $pelapor->whereNull('user_account_id'));
                    });
                }
            }))
            ->when(!$deviceToken, fn ($query) =>
                $query->whereRaw('1 = 0')
            )
            ->latest()
            ->paginate($perPage);

        $laporan->getCollection()->transform(
            fn (Laporan $item) => $item->makeHidden(['pelapor_nama', 'pelapor_kontak', 'is_anonim'])
        );

        return response()->json(['success' => true, 'data' => $laporan]);
    }

    private function attachmentUrl(string $storedUrl, Request $request): string
    {
        $path = parse_url($storedUrl, PHP_URL_PATH) ?: $storedUrl;
        $path = '/' . ltrim(preg_replace('#^/storage/#', '', $path), '/');

        return rtrim($request->getSchemeAndHttpHost(), '/') . '/storage' . $path;
    }

    public function show(Request $request, string $kodeTiket): JsonResponse
    {
        $laporan = Laporan::where('kode_tiket', $kodeTiket)->first();

        if (!$laporan) {
            return response()->json([
                'success' => false,
                'message' => 'Laporan dengan kode tiket tersebut tidak ditemukan.',
            ], 404);
        }

        $principal = $request->user();
        $authorized = false;
        if ($principal instanceof AkunWarga) {
            $authorized = $laporan->pelapor()
                ->where('user_account_id', $principal->getKey())
                ->exists();
        } elseif ($principal instanceof \App\Models\User) {
            $authorized = $principal->role === 'admin'
                || ($principal->role === 'petugas'
                    && $principal->unitLayanan()->whereKey($laporan->unit_layanan_id)->exists());
        }

        $deviceToken = $request->input('device_token');
        if (!$authorized && is_string($deviceToken) && $deviceToken !== '') {
            $registeredDeviceToken = DeviceToken::where('token_hash', hash('sha256', $deviceToken))
                ->where('is_active', true)
                ->first();

            $authorized = $registeredDeviceToken && (
                $laporan->deviceTokens()->whereKey($registeredDeviceToken->id)->exists()
                || ($registeredDeviceToken->pelapor_id === $laporan->pelapor_id
                    && $laporan->pelapor?->user_account_id === null)
            );
        }

        // A ticket is an identifier, not a private credential. Unauthenticated
        // requests receive only tracking-safe fields; supplied credentials that
        // do not own the report are rejected without returning private data.
        if (!$authorized && ($principal || $request->filled('device_token'))) {
            abort(403, 'Anda tidak memiliki akses ke laporan ini.');
        }

        if (!$authorized) {
            $laporan->load(['kategori', 'unitLayanan']);
            return response()->json(['success' => true, 'data' => [
                'kode_tiket' => $laporan->kode_tiket,
                'status' => $laporan->status,
                'kategori' => $laporan->kategori?->nama,
                'unit_layanan' => $laporan->unitLayanan?->nama,
                'created_at' => $laporan->created_at,
            ]]);
        }

        $laporan->load([
            'kategori', 'unitLayanan', 'lampiran',
            'statusLogs' => fn ($query) => $query->orderBy('created_at', 'asc'),
            'balasan' => fn ($query) => $query->orderBy('created_at', 'asc'),
        ]);

        return response()->json(['success' => true, 'data' => [
            'kode_tiket' => $laporan->kode_tiket,
            'tipe' => $laporan->tipe,
            'judul' => $laporan->judul,
            'deskripsi' => $laporan->deskripsi,
            'status' => $laporan->status,
            'kategori' => $laporan->kategori?->nama,
            'unit_layanan' => $laporan->unitLayanan?->only(['id', 'kode', 'nama']),
            'cabang_perpustakaan' => $laporan->cabang_perpustakaan,
            'created_at' => $laporan->created_at,
            'pelapor' => [
                'nama' => $laporan->is_anonim ? 'Anonymous' : ($laporan->pelapor_nama ?? '-'),
                'kontak' => $laporan->is_anonim ? null : $laporan->pelapor_kontak,
                'is_anonim' => (bool) $laporan->is_anonim,
            ],
            'lampiran' => $laporan->lampiran->map(fn ($item) => [
                'id' => $item->id, 'url' => $this->attachmentUrl($item->url_file, $request),
                'nama_file' => $item->nama_file, 'tipe' => $item->tipe,
            ])->values(),
            'status_timeline' => $laporan->statusLogs->map(fn ($log) => [
                'status_lama' => $log->status_lama, 'status_baru' => $log->status_baru,
                'catatan' => $log->catatan, 'created_at' => $log->created_at,
            ]),
            'balasan' => $laporan->balasan->map(fn ($balasan) => [
                'isi_balasan' => $balasan->isi_balasan, 'created_at' => $balasan->created_at,
            ]),
        ]]);
    }

    public function update(
        Request $request,
        Laporan $laporan,
        FirebaseService $firebaseService,
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
            ],
        ]);

        $statusLama = $laporan->status;
        $statusBaru = $validated['status'];

        if ($statusLama !== $statusBaru) {
            $statusTransitionService->transition(
                $laporan,
                $statusBaru,
                $validated['catatan'] ?? null,
                auth('api')->id()
            );
            $this->sendStatusNotification($laporan, $firebaseService);
        }

        return response()->json([
            'success' => true,
            'message' => 'Laporan berhasil diperbarui.',
            'data' => $laporan->load([
                'kategori',
                'pelapor',
            ]),
        ]);
    }

    private function sendStatusNotification(
        Laporan $laporan,
        FirebaseService $firebaseService
    ): void {
        $pelapor = $laporan->pelapor;

        if (!$pelapor) {
            return;
        }

        $tokens = $laporan->deviceTokens()
            ->where('is_active', true)
            ->get()
            ->merge($pelapor->deviceTokens()->where('is_active', true)->get())
            ->unique('id');

        foreach ($tokens as $deviceToken) {
            try {
                $firebaseService->sendNotification(
                    $deviceToken->token,
                    'Status Laporan DPK Berubah',
                    "Laporan {$laporan->kode_tiket} sekarang berstatus {$laporan->status}."
                );
            } catch (\Throwable $e) {
                // Token bermasalah jangan sampai menggagalkan update laporan
                $deviceToken->update([
                    'is_active' => false,
                ]);
            }
        }
    }
}
