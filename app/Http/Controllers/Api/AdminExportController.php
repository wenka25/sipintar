<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AdminLaporanQueryService;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminExportController extends Controller
{
    public function excel(Request $request, AdminLaporanQueryService $reports): StreamedResponse
    {
        $this->validateFilters($request);
        $this->ensurePetugasUnitAccess($request);
        $rows = $reports->build($request, $request->user())->get();
        abort_if($rows->isEmpty(), 422, 'Tidak ada laporan sesuai filter.');
        return response()->streamDownload(function () use ($rows, $request) {
            // Spreadsheet formula-injection mitigation: a leading `=`, `+`,
            // `-` or `@` would be interpreted as a formula by Excel when the
            // exported .xls is opened. Prefixing with a single quote neutralizes
            // the value while preserving the intended text for the viewer.
            $cell = function ($value) {
                $value = (string) ($value ?? '-');

                if ($value !== '' && str_contains('=+-@', $value[0])) {
                    $value = "'" . $value;
                }

                return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            };
            echo '<table border="1"><tr><th colspan="12">Laporan DPK Mobile</th></tr>';
            echo '<tr><td colspan="12">Periode: ' . $cell(($request->input('tanggal_mulai') ?: 'Semua waktu') . ($request->filled('tanggal_akhir') ? ' s/d ' . $request->input('tanggal_akhir') : '')) . '</td></tr>';
            echo '<tr><td colspan="12">Status: ' . $cell($request->input('status') ?: 'Semua Status') . ' | Kategori ID: ' . $cell($request->input('kategori_id') ?: 'Semua Kategori') . '</td></tr>';
            echo '<tr><td colspan="12">Total Laporan: ' . $rows->count() . '</td></tr>';
            echo '<thead><tr><th>No</th><th>Kode Tiket</th><th>Tanggal</th><th>Unit Layanan</th><th>Tipe</th><th>Kategori</th><th>Judul</th><th>Deskripsi</th><th>Status</th><th>Nama Pelapor</th><th>Kontak</th><th>Sumber</th></tr></thead><tbody>';
            foreach ($rows as $i => $row) {
                $reporter = $row->is_anonim ? 'Anonymous' : ($row->pelapor_nama ?? '-');
                $contact = $row->is_anonim ? null : $row->pelapor_kontak;
                $typeLabels = [
                    'pengaduan' => 'Pengaduan',
                    'aspirasi' => 'Aspirasi',
                    'permintaan_informasi' => 'Permintaan Informasi',
                ];
                echo '<tr><td>' . ($i + 1) . '</td><td>' . $cell($row->kode_tiket) . '</td><td>' . $cell(optional($row->created_at)->format('Y-m-d H:i')) . '</td><td>' . $cell($row->unitLayanan?->nama) . '</td><td>' . $cell($typeLabels[$row->tipe] ?? $row->tipe) . '</td><td>' . $cell($row->kategori?->nama) . '</td><td>' . $cell($row->judul) . '</td><td>' . $cell($row->deskripsi) . '</td><td>' . $cell($row->status) . '</td><td>' . $cell($reporter) . '</td><td>' . $cell($contact) . '</td><td>' . $cell($row->sumber) . '</td></tr>';
            }
            echo '</tbody></table>';
        }, 'laporan-dpk-' . now()->format('Y-m-d') . '.xls', ['Content-Type' => 'application/vnd.ms-excel; charset=UTF-8']);
    }

    public function pdf(Request $request, AdminLaporanQueryService $reports)
    {
        $this->validateFilters($request);
        $this->ensurePetugasUnitAccess($request);
        $rows = $reports->build($request, $request->user())->get();
        abort_if($rows->isEmpty(), 422, 'Tidak ada laporan sesuai filter.');
        $summary = $rows->countBy('status');
        $html = view('exports.laporan', compact('rows', 'summary', 'request'))->render();
        $pdf = new Dompdf();
        $pdf->loadHtml($html);
        $pdf->setPaper('a4', 'landscape');
        $pdf->render();
        return response()->streamDownload(fn() => print($pdf->output()), 'laporan-dpk-' . now()->format('Y-m-d') . '.pdf', ['Content-Type' => 'application/pdf']);
    }

    private function validateFilters(Request $request): void
    {
        $request->validate([
            'unit_layanan_id' => ['nullable', 'integer', 'exists:unit_layanan,id'],
            'status' => ['nullable', 'string', 'in:baru,diproses,selesai,ditolak'],
            'kategori_id' => ['nullable', 'integer', 'exists:kategori,id'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_akhir' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'search' => ['nullable', 'string', 'max:255'],
            'kode_tiket' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function ensurePetugasUnitAccess(Request $request): void
    {
        $user = $request->user();
        if ($user?->role === 'petugas' && $request->filled('unit_layanan_id')) {
            abort_unless(
                $user->unitLayanan()->whereKey($request->integer('unit_layanan_id'))->exists(),
                403,
                'Anda tidak memiliki akses ke unit laporan ini.'
            );
        }
    }
}
