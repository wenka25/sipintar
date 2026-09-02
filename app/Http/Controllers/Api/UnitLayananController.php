<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UnitLayanan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UnitLayananController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => UnitLayanan::query()->where('is_active', true)->orderBy('nama')->get(['id', 'kode', 'nama', 'deskripsi', 'is_active'])]);
    }

    public function assignments(int $id): JsonResponse
    {
        $user = \App\Models\User::findOrFail($id);
        return response()->json(['success' => true, 'data' => $user->unitLayanan()->orderBy('nama')->get()]);
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['unit_layanan_id' => ['required', 'integer', Rule::exists('unit_layanan', 'id')->where('is_active', true)]]);
        $user = \App\Models\User::findOrFail($id);
        abort_unless($user->role === 'petugas', 422, 'Assignment hanya untuk petugas.');
        $user->unitLayanan()->syncWithoutDetaching([$data['unit_layanan_id']]);
        return response()->json(['success' => true, 'data' => $user->unitLayanan()->get()]);
    }

    public function unassign(int $id, int $unitId): JsonResponse
    {
        $user = \App\Models\User::findOrFail($id);
        $user->unitLayanan()->detach($unitId);
        return response()->json(['success' => true]);
    }
}
