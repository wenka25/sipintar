@component('mail::message')
<h1 style="margin-top:0;">Lupa Password — {{ config('app.name', 'SIPINTAR') }}</h1>

<p>Halo,</p>

<p>Kami menerima permintaan reset password untuk akun yang terdaftar dengan email ini
    di aplikasi <strong>{{ config('app.name', 'SIPINTAR') }}</strong>.</p>

<p>Gunakan kode berikut untuk mengatur ulang password Anda:</p>

<p style="text-align:center; margin:24px 0;">
    <span style="display:inline-block; font-size:30px; font-weight:bold; letter-spacing:8px;
                 padding:12px 24px; border:1px solid #dddddd; border-radius:8px;">{{ $code }}</span>
</p>

<p>Kode ini berlaku selama <strong>{{ $expiresInMinutes }} menit</strong>
    dan hanya dapat digunakan satu kali.</p>

<p><strong>Jika Anda tidak merasa meminta reset password, abaikan email ini.</strong>
    Password Anda tidak akan berubah selama kode ini tidak digunakan.
    Jangan bagikan kode ini kepada siapa pun, termasuk pihak yang mengaku sebagai petugas.</p>

<p>Salam,<br>{{ config('app.name', 'SIPINTAR') }}</p>
@endcomponent