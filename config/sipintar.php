<?php

/*
|--------------------------------------------------------------------------
| SIPINTAR Public Install Page Configuration
|--------------------------------------------------------------------------
|
| Configuration untuk halaman publik /install (pilot testing).
| Tidak ada business logic, database, atau auth di sini.
|
| APK URL belum tersedia secara publik — sementara dikosongkan.
| Setelah APK di-upload ke hosting, isi:
|   SIPINTAR_APK_URL=https://.../app-release.apk
|
*/

return [
    'apk_url' => env('SIPINTAR_APK_URL', ''),
    'apk_size' => '52.5 MB',
];
