<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Laporan Aspirasi dan Pengaduan</title>
    <style>
        :root {
            --primary: #0D1F8F;
            --border: #444;
            --text-dark: #111;
            --text-muted: #555;
            --bg-header: #dbe4f5;
            --bg-even: #f8f9fc;
            --bg-badge-baru: #e3f2fd;
            --color-badge-baru: #1565c0;
            --bg-badge-diproses: #fff3e0;
            --color-badge-diproses: #e65100;
            --bg-badge-selesai: #e8f5e9;
            --color-badge-selesai: #2e7d32;
            --bg-badge-ditolak: #ffebee;
            --color-badge-ditolak: #c62828;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9px;
            color: var(--text-dark);
            padding: 0;
            margin: 0;
            background: #fff;
        }

        .header {
            text-align: center;
            margin-bottom: 14px;
            border-bottom: 2px solid var(--primary);
            padding-bottom: 7px;
        }

        .header .institution {
            font-size: 15px;
            font-weight: bold;
            color: var(--primary);
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .header .sub-institution {
            font-size: 11px;
            font-weight: bold;
            color: #333;
            margin-bottom: 6px;
        }

        .header .title {
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin: 3px 0;
        }

        /* ===== Ringkasan: TABEL HTML ASLI (bukan CSS display:table) =====
           DomPDF tidak konsisten mendukung "display: table / table-cell"
           di elemen div/span biasa — terutama <span> yang aslinya inline
           (lihat .summary-divider di versi lama). Solusinya pakai <table>
           sungguhan, sama seperti .report-table yang sudah terbukti
           render rapi di PDF. */
        .summary {
            margin: 13px 0 15px;
            padding: 13px 14px;
            background: var(--bg-even);
            border: 1px solid var(--border);
            border-radius: 4px;
            page-break-inside: avoid;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }

        .summary-table td {
            border: none;
            padding: 4px 6px;
            vertical-align: baseline;
            white-space: nowrap;
        }

        .summary-item {
            text-align: center;
        }

        .summary-label {
            font-weight: normal;
            color: var(--text-muted);
            font-size: 8.5px;
        }

        .summary-value {
            font-weight: bold;
            color: var(--primary);
            font-size: 12px;
        }

        .summary-divider {
            width: 14px;
            color: #ccc;
            font-weight: 300;
            text-align: center;
        }

        .summary .print-date {
            text-align: right;
            font-size: 8px;
            color: var(--text-muted);
            margin-top: 6px;
            border-top: 1px dashed #ddd;
            padding-top: 4px;
        }

        /* ===== Filter info ===== */
        .filter-info {
            margin: 10px 0;
            color: var(--text-muted);
            font-size: 9px;
            line-height: 1.5;
            overflow-wrap: break-word;
            page-break-inside: avoid;
        }

        /* ===== Daftar laporan: satu blok vertikal per laporan ===== */
        .reports {
            margin-top: 8px;
        }

        .report-card {
            width: 100%;
            margin: 0 0 10px;
            border: 1px solid var(--border);
            page-break-inside: avoid;
        }

        .report-card table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .report-card td {
            padding: 6px 7px;
            border: 1px solid #b8becb;
            vertical-align: top;
            font-size: 9px;
            line-height: 1.35;
            overflow-wrap: break-word;
            word-wrap: break-word;
        }

        .report-card .key-row td {
            background: var(--bg-header);
            color: #1a237e;
            font-weight: bold;
        }

        .report-card .number-cell {
            width: 8%;
            text-align: center;
        }

        .report-card .ticket-cell {
            width: 42%;
            white-space: nowrap;
            font-size: 9.5px;
        }

        .report-card .date-cell {
            width: 22%;
            white-space: nowrap;
        }

        .report-card .status-cell {
            width: 28%;
            text-align: center;
        }

        .report-card .label-cell {
            width: 16%;
            background: #f1f3f8;
            color: var(--text-muted);
            font-weight: bold;
        }

        .report-card .value-cell {
            width: 34%;
        }

        .report-card .title-label {
            width: 16%;
            background: #f1f3f8;
            color: var(--text-muted);
            font-weight: bold;
        }

        .report-card .title-value {
            width: 84%;
            white-space: normal;
        }

        .text-center {
            text-align: center;
        }

        /* ===== Status badge ===== */
        .status-badge {
            display: inline-block;
            padding: 3px 5px;
            border-radius: 8px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-badge.baru {
            background: var(--bg-badge-baru);
            color: var(--color-badge-baru);
        }

        .status-badge.diproses {
            background: var(--bg-badge-diproses);
            color: var(--color-badge-diproses);
        }

        .status-badge.selesai {
            background: var(--bg-badge-selesai);
            color: var(--color-badge-selesai);
        }

        .status-badge.ditolak {
            background: var(--bg-badge-ditolak);
            color: var(--color-badge-ditolak);
        }

        .status-badge.default {
            background: #eceff1;
            color: #333;
        }

        .footer {
            margin-top: 12px;
            padding-top: 6px;
            border-top: 1px solid #ddd;
            text-align: right;
            font-size: 7.5px;
            color: var(--text-muted);
            page-break-inside: avoid;
        }

        @page {
            size: A4 portrait;
            margin: 12mm;
        }
    </style>
</head>

<body>
    <!-- Kop Laporan -->
    <div class="header">
        <div class="institution">DINAS PERPUSTAKAAN DAN KEARSIPAN</div>
        <div class="sub-institution">LAYAN ASPIRASI DAN PENGADUAN ONLINE</div>
        <div class="title">LAPORAN ASPIRASI DAN PENGADUAN</div>
    </div>

    <!-- ===== RINGKASAN (pakai <table> asli agar konsisten di DomPDF) ===== -->
    <div class="summary">
        <table class="summary-table" cellspacing="0" cellpadding="0">
            <tr>
                <td class="summary-item">
                    <span class="summary-label">Total Laporan</span>
                    <span class="summary-value">{{ $rows->count() }}</span>
                </td>
                <td class="summary-divider">|</td>
                <td class="summary-item">
                    <span class="summary-label">Baru</span>
                    <span class="summary-value">{{ $summary['baru'] ?? 0 }}</span>
                </td>
                <td class="summary-divider">|</td>
                <td class="summary-item">
                    <span class="summary-label">Diproses</span>
                    <span class="summary-value">{{ $summary['diproses'] ?? 0 }}</span>
                </td>
                <td class="summary-divider">|</td>
                <td class="summary-item">
                    <span class="summary-label">Selesai</span>
                    <span class="summary-value">{{ $summary['selesai'] ?? 0 }}</span>
                </td>
                <td class="summary-divider">|</td>
                <td class="summary-item">
                    <span class="summary-label">Ditolak</span>
                    <span class="summary-value">{{ $summary['ditolak'] ?? 0 }}</span>
                </td>
            </tr>
        </table>

        <div class="print-date">Dicetak: {{ now()->format('d-m-Y H:i') }}</div>
    </div>

    <!-- Filter Info -->
    <div class="filter-info">
        <strong>Unit Layanan:</strong>
        {{ $request->filled('unit_layanan_id') ? ($rows->first()?->unitLayanan?->nama ?? $request->input('unit_layanan_id')) : 'Semua Unit' }}
        &nbsp; | &nbsp;
        <strong>Status:</strong> {{ $request->input('status') ?: 'Semua Status' }}
        &nbsp; | &nbsp;
        <strong>Kategori:</strong>
        {{ $rows->first()?->kategori?->nama ?? ($request->filled('kategori_id') ? $request->input('kategori_id') : 'Semua Kategori') }}
        @if($request->filled('tanggal_mulai') || $request->filled('tanggal_akhir'))
            &nbsp; | &nbsp; <strong>Periode:</strong>
            {{ $request->input('tanggal_mulai') ?: 'awal' }} s/d {{ $request->input('tanggal_akhir') ?: 'akhir' }}
        @endif
    </div>

    <!-- Daftar Laporan: setiap laporan adalah satu blok yang tidak dipotong -->
    <div class="reports">
        @forelse($rows as $i => $row)
            @php
                $statusClass = match ($row->status) {
                    'baru' => 'baru',
                    'diproses' => 'diproses',
                    'selesai' => 'selesai',
                    'ditolak' => 'ditolak',
                    default => 'default',
                };
                $typeLabel = [
                    'pengaduan' => 'Pengaduan',
                    'aspirasi' => 'Aspirasi',
                    'permintaan_informasi' => 'Permintaan Informasi',
                ][$row->tipe] ?? $row->tipe;
            @endphp
            <div class="report-card">
                <table>
                    <tr class="key-row">
                        <td class="number-cell">No<br><span>{{ $i + 1 }}</span></td>
                        <td class="ticket-cell">Kode Tiket<br><span>{{ $row->kode_tiket }}</span></td>
                        <td class="date-cell">Tanggal<br><span>{{ optional($row->created_at)->format('d-m-Y') }}</span></td>
                        <td class="status-cell">Status<br><span
                                class="status-badge {{ $statusClass }}">{{ $row->status }}</span></td>
                    </tr>
                    <tr>
                        <td class="label-cell">Unit</td>
                        <td class="value-cell">{{ $row->unitLayanan?->nama ?? '-' }}</td>
                        <td class="label-cell">Tipe</td>
                        <td class="value-cell">{{ $typeLabel }}</td>
                    </tr>
                    <tr>
                        <td class="label-cell">Kategori</td>
                        <td class="value-cell">{{ $row->kategori?->nama ?? '-' }}</td>
                        <td class="label-cell">Pelapor</td>
                        <td class="value-cell">
                            @if($row->is_anonim)
                                Anonymous
                            @else
                                {{ $row->pelapor_nama ?? '-' }}
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="title-label">Judul</td>
                        <td colspan="3" class="title-value">{{ $row->judul }}</td>
                    </tr>
                </table>
            </div>
        @empty
            <table class="report-card">
                <tr>
                    <td class="text-center">Tidak ada data laporan.</td>
                </tr>
            </table>
        @endforelse
    </div>

    <div class="footer">
        Dokumen ini dicetak secara otomatis dari sistem DPK Mobile.
    </div>
</body>

</html>