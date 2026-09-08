<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Laporan;
use App\Models\AkunWarga;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WargaLaporanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var AkunWarga $warga */
        $warga = $request->user();
        $pelaporIds = $warga->pelapor()->pluck('id');
        $perPage = min(max($request->integer('per_page', 10), 1), 100);

        $laporan = Laporan::with(['kategori', 'unitLayanan'])
            ->whereIn('pelapor_id', $pelaporIds)
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $laporan,
        ]);
    }
}
