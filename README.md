# SIPINTAR API

Backend Laravel untuk SIPINTAR (Layanan Aspirasi DPK). API ini melayani autentikasi JWT untuk staf dan warga, laporan publik maupun terautentikasi, lampiran, reset password berbantuan admin, serta notifikasi FCM.

## Kebutuhan

- PHP 8.3+
- Composer
- Database yang dikonfigurasi melalui `.env`

## Menjalankan lokal

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Isi konfigurasi database, JWT, Firebase, dan storage pada `.env` sesuai environment. Buat symbolic link storage bila lampiran akan disajikan secara lokal:

```bash
php artisan storage:link
```

## Pengujian

```bash
php artisan test
```

## Struktur singkat

- `app/Http/Controllers/Api`: endpoint API.
- `app/Services`: aturan laporan, status, ekspor, dan notifikasi.
- `app/Models`: model Eloquent.
- `app/Http/Middleware`: autentikasi JWT gabungan dan otorisasi role.
- `routes/api.php`: definisi route API.
- `tests`: feature dan unit test.

Migration yang sudah ada adalah bagian dari riwayat database dan tidak boleh dihapus.
