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
            margin-bottom: 12px;
            border-bottom: 2px solid var(--primary);
            padding-bottom: 8px;
        }

        .header .institution {
            font-size: 14px;
            font-weight: bold;
            color: var(--primary);
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .header .sub-institution {
            font-size: 10px;
            font-weight: bold;
            color: #333;
            margin-bottom: 7px;
        }

        .header .title {
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin: 3px 0;
        }

        /* ===== Ringkasan: table layout kompatibel dengan DomPDF ===== */
        .summary {
            margin: 10px 0;
            padding: 7px 10px;
            background: var(--bg-even);
            border: 1px solid var(--border);
            border-radius: 4px;
            page-break-inside: avoid;
        }

        .summary-row {
            display: table;
            width: 100%;
            table-layout: fixed;
        }

        .summary-item,
        .summary-divider {
            display: table-cell;
            vertical-align: baseline;
            white-space: nowrap;
        }

        .summary-item {
            text-align: center;
        }

        .summary-label {
            font-weight: normal;
            color: var(--text-muted);
            font-size: 9px;
        }

        .summary-value {
            font-weight: bold;
            color: var(--primary);
            font-size: 10px;
        }

        .summary-divider {
            width: 2%;
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
            margin: 8px 0;
            color: var(--text-muted);
            font-size: 8px;
            line-height: 1.4;
            overflow-wrap: break-word;
            page-break-inside: avoid;
        }

        /* ===== Tabel utama ===== */
        .report-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            margin-top: 8px;
            page-break-inside: auto;
        }

        .report-table thead {
            display: table-header-group;
        }

        .report-table tr {
            page-break-inside: avoid;
        }

        .report-table th {
            background: var(--bg-header);
            color: #1a237e;
            font-weight: bold;
            text-align: left;
            padding: 6px 4px;
            border: 1px solid var(--border);
            text-transform: uppercase;
            font-size: 8.5px;
            letter-spacing: 0.2px;
            word-wrap: break-word;
        }

        .report-table td {
            padding: 5px 4px;
            border: 1px solid var(--border);
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .report-table td:nth-child(2) {
            white-space: nowrap;
            font-size: 8px;
        }

        .report-table tbody tr:nth-child(even) {
            background: var(--bg-even);
        }

        .text-center {
            text-align: center;
        }

        /* ===== Status badge ===== */
        .status-badge {
            display: inline-block;
            padding: 2px 4px;
            border-radius: 8px;
            font-size: 7px;
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
            margin-top: 10px;
            padding-top: 5px;
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

    <!-- ===== RINGKASAN (diperbaiki dengan Flexbox) ===== -->
    <div class="summary">
        <div class="summary-row">
            <!-- Total Laporan -->
            <div class="summary-item">
                <span class="summary-label">Total Laporan</span>
                <span class="summary-value">{{ $rows->count() }}</span>
            </div>
            <span class="summary-divider">|</span>

            <!-- Baru -->
            <div class="summary-item">
                <span class="summary-label">Baru</span>
                <span class="summary-value">{{ $summary['baru'] ?? 0 }}</span>
            </div>
            <span class="summary-divider">|</span>

            <!-- Diproses -->
            <div class="summary-item">
                <span class="summary-label">Diproses</span>
                <span class="summary-value">{{ $summary['diproses'] ?? 0 }}</span>
            </div>
            <span class="summary-divider">|</span>

            <!-- Selesai -->
            <div class="summary-item">
                <span class="summary-label">Selesai</span>
                <span class="summary-value">{{ $summary['selesai'] ?? 0 }}</span>
            </div>
            <span class="summary-divider">|</span>

            <!-- Ditolak -->
            <div class="summary-item">
                <span class="summary-label">Ditolak</span>
                <span class="summary-value">{{ $summary['ditolak'] ?? 0 }}</span>
            </div>
        </div>

        <div class="print-date">Dicetak: {{ now()->format('d-m-Y H:i') }}</div>
    </div>

    <!-- Filter Info -->
    <div class="filter-info">
        <strong>Unit Layanan:</strong>
        {{ $request->filled('unit_layanan_id') ? ($rows->first()?->unitLayanan?->nama ?? $request->input('unit_layanan_id')) : 'Semua Unit' }}
        &nbsp; | &nbsp;
        <strong>Status:</strong> {{ $request->input('status') ?: 'Semua Status' }}
        &nbsp; | &nbsp;
        <strong>Kategori:</strong> {{ $rows->first()?->kategori?->nama ?? ($request->filled('kategori_id') ? $request->input('kategori_id') : 'Semua Kategori') }}
        @if($request->filled('tanggal_mulai') || $request->filled('tanggal_akhir'))
            &nbsp; | &nbsp; <strong>Periode:</strong>
            {{ $request->input('tanggal_mulai') ?: 'awal' }} s/d {{ $request->input('tanggal_akhir') ?: 'akhir' }}
        @endif
    </div>

    <!-- Tabel Laporan -->
    <table class="report-table">
        <thead>
            <tr>
                <th style="width:4%">No</th>
                <th style="width:17%">Kode Tiket</th>
                <th style="width:9%">Tanggal</th>
                <th style="width:13%">Unit</th>
                <th style="width:9%">Tipe</th>
                <th style="width:9%">Kategori</th>
                <th style="width:22%">Judul</th>
                <th style="width:8%">Status</th>
                <th style="width:9%">Pelapor</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $i => $row)
                <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td>{{ $row->kode_tiket }}</td>
                    <td>{{ optional($row->created_at)->format('d-m-Y') }}</td>
                    <td>{{ $row->unitLayanan?->nama ?? '-' }}</td>
                    <td>{{ [
                        'pengaduan' => 'Pengaduan',
                        'aspirasi' => 'Aspirasi',
                        'permintaan_informasi' => 'Permintaan Informasi',
                    ][$row->tipe] ?? $row->tipe }}</td>
                    <td>{{ $row->kategori?->nama ?? '-' }}</td>
                    <td>{{ $row->judul }}</td>
                    <td>
                        @php
                            $statusClass = match ($row->status) {
                                'baru' => 'baru',
                                'diproses' => 'diproses',
                                'selesai' => 'selesai',
                                'ditolak' => 'ditolak',
                                default => 'default',
                            };
                        @endphp
                        <span class="status-badge {{ $statusClass }}">
                            {{ $row->status }}
                        </span>
                    </td>
                    <td>
                        @if($row->is_anonim)
                            Anonymous
                        @else
                            {{ $row->pelapor_nama ?? '-' }}
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center">Tidak ada data laporan.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Dokumen ini dicetak secara otomatis dari sistem DPK Mobile.
    </div>
</body>
</html>