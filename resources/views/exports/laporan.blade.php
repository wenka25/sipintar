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
            margin-bottom: 20px;
            border-bottom: 3px solid var(--primary);
            padding-bottom: 12px;
        }

        .header .institution {
            font-size: 14px;
            font-weight: bold;
            color: var(--primary);
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .header .sub-institution {
            font-size: 11px;
            font-weight: bold;
            color: #333;
            margin-bottom: 12px;
        }

        .header .title {
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 4px 0;
        }

        .summary {
            margin: 16px 0;
            padding: 12px;
            background: var(--bg-even);
            border: 1px solid var(--border);
            border-radius: 4px;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            background: transparent;
        }

        .summary-table td {
            padding: 6px 10px;
            border: none;
            vertical-align: middle;
            font-weight: bold;
        }

        .summary-table td:first-child {
            text-align: left;
            color: var(--text-muted);
        }

        .summary-table td:last-child {
            text-align: right;
            font-size: 12px;
            color: var(--primary);
        }

        .summary .print-date {
            text-align: right;
            font-size: 8px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        .report-table thead {
            display: table-header-group;
        }

        .report-table tr {
            page-break-inside: avoid;
        }

        .filter-info {
            margin: 12px 0;
            color: var(--text-muted);
        }

        .report-table th {
            background: var(--bg-header);
            color: #1a237e;
            font-weight: bold;
            text-align: left;
            padding: 8px 6px;
            border: 1px solid var(--border);
            text-transform: uppercase;
            font-size: 8.5px;
            letter-spacing: 0.5px;
        }

        .report-table td {
            padding: 7px 6px;
            border: 1px solid var(--border);
            vertical-align: top;
        }

        .report-table tbody tr:nth-child(even) {
            background: var(--bg-even);
        }

        .text-center {
            text-align: center;
        }

        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 10px;
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
            margin-top: 20px;
            text-align: right;
            font-size: 8px;
            color: var(--text-muted);
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

    <!-- Ringkasan -->
    <div class="summary">
        <table class="summary-table">
            <tr>
                <td>Total Laporan</td>
                <td>{{ $rows->count() }}</td>
                <td>Baru</td>
                <td>{{ $summary['baru'] ?? 0 }}</td>
                <td>Diproses</td>
                <td>{{ $summary['diproses'] ?? 0 }}</td>
                <td>Selesai</td>
                <td>{{ $summary['selesai'] ?? 0 }}</td>
                <td>Ditolak</td>
                <td>{{ $summary['ditolak'] ?? 0 }}</td>
            </tr>
        </table>
        <div class="print-date">Dicetak: {{ now()->format('d-m-Y H:i') }}</div>
    </div>

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
                <th style="width:15%">Kode Tiket</th>
                <th style="width:9%">Tanggal</th>
                <th style="width:12%">Unit</th>
                <th style="width:10%">Tipe</th>
                <th style="width:10%">Kategori</th>
                <th style="width:23%">Judul</th>
                <th style="width:10%">Status</th>
                <th style="width:12%">Pelapor</th>
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
                    <td colspan="8" class="text-center">Tidak ada data laporan.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Dokumen ini dicetak secara otomatis dari sistem DPK Mobile.
    </div>
</body>
</html>