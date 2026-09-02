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

        $laporan = Laporan::with(['kategori', 'unitLayanan'])
            ->whereIn('pelapor_id', $pelaporIds)
            ->latest()
            ->paginate($request->integer('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => $laporan,
        ]);
    }
}
