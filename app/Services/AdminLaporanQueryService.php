<?php

namespace App\Services;

use App\Models\Laporan;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AdminLaporanQueryService
{
    public function build(Request $request, ?User $user): Builder
    {
        // Snapshot fields are stored on laporan; Pelapor hanya diperlukan untuk
        // ownership/authorization, bukan untuk historical display atau export.
        $query = Laporan::query()->with(['kategori', 'unitLayanan'])->latest();
        if ($user?->role === 'petugas') {
            $query->whereIn('unit_layanan_id', $user->unitLayanan()->pluck('unit_layanan.id'));
        }
        if ($request->filled('unit_layanan_id')) {
            $id = $request->integer('unit_layanan_id');
            if ($user?->role === 'petugas' && !$user->unitLayanan()->whereKey($id)->exists()) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('unit_layanan_id', $id);
            }
        }
        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('kode_tiket', 'like', "%{$search}%")
                    ->orWhere('judul', 'like', "%{$search}%");
            });
        }
        foreach (['status', 'tipe', 'kategori_id', 'sumber'] as $field) {
            if ($request->filled($field)) $query->where($field, $request->input($field));
        }
        if ($request->filled('kode_tiket')) $query->where('kode_tiket', 'like', '%' . $request->input('kode_tiket') . '%');
        if ($request->filled('tanggal_mulai')) $query->whereDate('created_at', '>=', $request->input('tanggal_mulai'));
        if ($request->filled('tanggal_akhir')) $query->whereDate('created_at', '<=', $request->input('tanggal_akhir'));
        return $query;
    }
}
