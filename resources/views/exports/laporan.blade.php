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

        /* Reset di-scope ke elemen konkret (BUKAN universal '*'):
           universal reset menimpa margin kotak @page Dompdf sehingga
           margin halaman menjadi 0 dan konten menempel ke tepi kertas. */
        body, div, table, tr, td, th, span, p {
            margin: 0;
            padding: 0;
        }

        table, td, th {
            box-sizing: border-box;
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

        /* ===== TABEL UTAMA: satu tabel untuk seluruh laporan ===== */
        .report-table {
            width: 100%;
            margin: 8px 0 0;
            border-collapse: collapse;
            border-spacing: 0;
            table-layout: fixed;
        }

        /* Header tabel berulang di setiap halaman (didukung Dompdf) */
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
            font-size: 8.5px;
            text-align: left;
            border: 1px solid #b8becb;
            padding: 4px 4px;
            vertical-align: top;
        }

        .report-table td {
            border: 1px solid #b8becb;
            padding: 3px 4px;
            vertical-align: top;
            font-size: 8.5px;
            line-height: 1.35;
            overflow-wrap: break-word;
            word-wrap: break-word;
        }

        .report-table tbody tr:nth-child(even) td {
            background: var(--bg-even);
        }

        .col-no { width: 4%; text-align: center; }
        .col-kode { width: 12%; }
        .col-tanggal { width: 8%; }
        .col-unit { width: 12%; }
        .col-tipe { width: 10%; }
        .col-kategori { width: 12%; }
        .col-judul { width: 24%; }
        .col-status { width: 8%; text-align: center; }
        .col-pelapor { width: 10%; }

        .td-no { text-align: center; }
        .td-status { text-align: center; }

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
            margin: 12mm 15mm;
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

    <!-- TABEL UTAMA: semua laporan dalam satu tabel -->
    <table class="report-table" cellspacing="0" cellpadding="0">
        <thead>
            <tr>
                <th class="col-no">No</th>
                <th class="col-kode">Kode Tiket</th>
                <th class="col-tanggal">Tanggal</th>
                <th class="col-unit">Unit</th>
                <th class="col-tipe">Tipe</th>
                <th class="col-kategori">Kategori</th>
                <th class="col-judul">Judul</th>
                <th class="col-status">Status</th>
                <th class="col-pelapor">Pelapor</th>
            </tr>
        </thead>
        <tbody>
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
            'permintaan_informasi' => 'Perm. Informasi',
        ][$row->tipe] ?? $row->tipe;
                @endphp
                <tr>
                    <td class="td-no">{{ $i + 1 }}</td>
                    <td>{{ $row->kode_tiket }}</td>
                    <td>{{ optional($row->created_at)->format('d-m-Y') }}</td>
                    <td>{{ $row->unitLayanan?->nama ?? '-' }}</td>
                    <td>{{ $typeLabel }}</td>
                    <td>{{ $row->kategori?->nama ?? '-' }}</td>
                    <td>{{ $row->judul }}</td>
                    <td class="td-status">
                        <span class="status-badge {{ $statusClass }}">{{ $row->status }}</span>
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