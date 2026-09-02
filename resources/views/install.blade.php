<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SIPINTAR - Instal Aplikasi</title>
  <meta name="description"
    content="SIPINTAR - Sistem Informasi Permintaan Informasi Terpadu dan Responsif. Download aplikasi Android SIPINTAR untuk pilot testing.">
  <link rel="icon" href="{{ asset('favicon.ico') }}">
  <style>
    :root {
      --deep-blue: #0d3b66;
      --blue: #1467a8;
      --turquoise: #1fb6a6;
      --cyan: #3ec6d8;
      --yellow: #f9c931;
      --white: #ffffff;
      --text: #1c2b3a;
      --muted: #5b6b7b;
      --bg: #f4f8fb;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box
    }

    html {
      -webkit-text-size-adjust: 100%
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      background: var(--bg);
      color: var(--text);
      line-height: 1.6;
    }

    .container {
      max-width: 560px;
      margin: 0 auto;
      padding: 0 20px
    }

    /* HERO */
    .hero {
      background: linear-gradient(160deg, var(--deep-blue) 0%, var(--blue) 60%, #1a86c9 100%);
      color: var(--white);
      padding: 48px 0 56px;
      text-align: center;
    }

    .hero .logo {
      width: 104px;
      height: 104px;
      border-radius: 24px;
      background: var(--white);
      padding: 10px;
      display: block;
      margin: 0 auto 18px;
      box-shadow: 0 6px 20px rgba(0, 0, 0, .18);
    }

    .hero h1 {
      font-size: 2rem;
      font-weight: 800;
      letter-spacing: .04em;
    }

    .hero .subtitle {
      font-size: .95rem;
      opacity: .9;
      max-width: 320px;
      margin: 6px auto 4px;
    }

    .badge {
      display: inline-block;
      margin-top: 14px;
      background: rgba(249, 201, 49, .16);
      color: var(--yellow);
      border: 1px solid rgba(249, 201, 49, .5);
      font-size: .78rem;
      font-weight: 700;
      letter-spacing: .08em;
      text-transform: uppercase;
      padding: 6px 14px;
      border-radius: 999px;
    }

    /* CARD / CTA */
    .card {
      background: var(--white);
      border-radius: 16px;
      padding: 26px 22px;
      margin-top: -34px;
      box-shadow: 0 8px 24px rgba(13, 59, 102, .10);
    }

    .app-meta {
      display: flex;
      justify-content: center;
      gap: 0;
      flex-wrap: wrap;
      margin-bottom: 20px
    }

    .app-meta div {
      flex: 1 1 40%;
      text-align: center;
      padding: 6px 4px
    }

    .app-meta .label {
      font-size: .72rem;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: .06em
    }

    .app-meta .value {
      font-size: .95rem;
      font-weight: 700;
      color: var(--deep-blue)
    }

    .btn-download {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      width: 100%;
      min-height: 56px;
      background: linear-gradient(135deg, var(--blue), var(--turquoise));
      color: var(--white);
      font-size: 1.02rem;
      font-weight: 700;
      letter-spacing: .02em;
      text-decoration: none;
      border: none;
      border-radius: 14px;
      padding: 14px 18px;
      cursor: pointer;
    }

    .btn-download:active {
      transform: scale(.99)
    }

    .btn-download svg {
      width: 22px;
      height: 22px;
      flex-shrink: 0
    }

    .btn-download.disabled {
      background: #c9d4de;
      color: #5b6b7b;
      cursor: not-allowed;
    }

    .download-note {
      margin-top: 10px;
      font-size: .82rem;
      color: var(--muted);
      text-align: center
    }

    /* SECTIONS */
    section {
      padding: 34px 0 0
    }

    .section-title {
      font-size: 1.12rem;
      font-weight: 800;
      color: var(--deep-blue);
      margin-bottom: 16px;
    }

    .steps {
      list-style: none;
      counter-reset: step
    }

    .steps li {
      counter-increment: step;
      display: flex;
      gap: 14px;
      align-items: flex-start;
      background: var(--white);
      border-radius: 12px;
      padding: 14px 16px;
      margin-bottom: 10px;
    }

    .steps li::before {
      content: "0" counter(step);
      font-weight: 800;
      font-size: 1rem;
      color: var(--turquoise);
      min-width: 32px;
      padding-top: 2px;
    }

    .steps li span {
      font-size: .93rem
    }

    .notice {
      background: #fff8e1;
      border-left: 4px solid var(--yellow);
      border-radius: 0 12px 12px 0;
      padding: 14px 16px;
      font-size: .9rem;
    }

    .notice strong {
      color: var(--deep-blue)
    }

    .req {
      list-style: none
    }

    .req li {
      display: flex;
      justify-content: space-between;
      background: var(--white);
      border-radius: 10px;
      padding: 10px 16px;
      margin-bottom: 8px;
      font-size: .9rem;
    }

    .req li b {
      color: var(--deep-blue)
    }

    /* FOOTER */
    footer {
      margin-top: 40px;
      background: var(--deep-blue);
      color: var(--white);
      text-align: center;
      padding: 28px 20px;
    }

    footer .f-name {
      font-weight: 800;
      letter-spacing: .05em
    }

    footer .f-sub {
      font-size: .82rem;
      opacity: .75;
      margin-top: 4px
    }

    @media (min-width:480px) {
      .app-meta div {
        flex: 1 1 20%
      }
    }
  </style>
</head>

<body>

  <header class="hero">
    <div class="container">
      <img class="logo" src="{{ asset('logo_sipintar.png') }}" alt="Logo SIPINTAR">
      <h1>SIPINTAR</h1>
      <p class="subtitle">Sistem Informasi Permintaan Informasi Terpadu dan Responsif</p>
      <span class="badge">Aplikasi Android &middot; Pilot Testing</span>
    </div>
  </header>

  <main class="container">

    <div class="card">
      <div class="app-meta">
        <div>
          <div class="label">Versi</div>
          <div class="value">{{ $appVersion }}</div>
        </div>
        <div>
          <div class="label">Build</div>
          <div class="value">{{ $buildNumber }}</div>
        </div>
        <div>
          <div class="label">Tipe</div>
          <div class="value">Release APK</div>
        </div>
        <div>
          <div class="label">Ukuran</div>
          <div class="value">{{ $apkSize }}</div>
        </div>
      </div>

      @if($apkUrl)
        <a class="btn-download" href="{{ $apkUrl }}" target="_blank" rel="noopener noreferrer"
          aria-label="Download Aplikasi SIPINTAR">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"
            stroke-linejoin="round" aria-hidden="true">
            <path d="M12 3v12" />
            <path d="M6 11l6 6 6-6" />
            <path d="M5 21h14" />
          </svg>
          DOWNLOAD APLIKASI SIPINTAR
        </a>
      @else
        <button class="btn-download disabled" disabled aria-disabled="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"
            stroke-linejoin="round" aria-hidden="true">
            <path d="M12 3v12" />
            <path d="M6 11l6 6 6-6" />
            <path d="M5 21h14" />
          </svg>
          Download APK segera tersedia
        </button>
        <p class="download-note">Tautan unduhan sedang disiapkan. Silakan cek kembali nanti.</p>
      @endif
    </div>

    <section aria-labelledby="guide-title">
      <h2 class="section-title" id="guide-title">Cara Install SIPINTAR</h2>
      <ol class="steps">
        <li><span>Download APK SIPINTAR dengan tombol di atas.</span></li>
        <li><span>Tunggu file APK selesai diunduh.</span></li>
        <li><span>Buka file APK yang telah selesai diunduh.</span></li>
        <li><span>Izinkan pemasangan dari sumber ini jika Android memintanya ("Install unknown apps").</span></li>
        <li><span>Tekan Install, lalu buka aplikasi SIPINTAR.</span></li>
      </ol>
    </section>

    <section aria-labelledby="pilot-title">
      <h2 class="section-title" id="pilot-title">Tentang Pilot Testing</h2>
      <div class="notice">
        <strong>Versi Pilot Testing</strong><br>
        Versi ini digunakan untuk keperluan pengujian.<br>
        Gunakan aplikasi sesuai instruksi pengujian yang diberikan.
      </div>
    </section>

    <section aria-labelledby="req-title">
      <h2 class="section-title" id="req-title">Persyaratan Sistem</h2>
      <ul class="req">
        <li><b>Platform</b><span>Android</span></li>
        <li><b>Format</b><span>APK</span></li>
        <li><b>Build</b><span>Release</span></li>
        <li><b>Android minimum</b><span>Android 7.0 (Nougat)</span></li>
      </ul>
    </section>

  </main>

  <footer>
    <div class="f-name">SIPINTAR</div>
    <div class="f-sub">Sistem Informasi Permintaan Informasi Terpadu dan Responsif</div>
  </footer>

</body>

</html>