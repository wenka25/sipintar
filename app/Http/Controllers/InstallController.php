<?php

namespace App\Http\Controllers;

class InstallController extends Controller
{
    /**
     * Halaman publik download APK SIPINTAR (pilot testing).
     * Tanpa database, tanpa auth, tanpa business logic.
     */
    public function index()
    {
        $apkUrl = trim((string) config('sipintar.apk_url'));

        // URL dianggap valid hanya jika http(s) yang benar-benar bisa dipakai.
        $apkValid = $apkUrl !== ''
            && $apkUrl !== '#'
            && filter_var($apkUrl, FILTER_VALIDATE_URL) !== false
            && preg_match('#^https?://#i', $apkUrl) === 1;

        return view('install', [
            'apkUrl'    => $apkValid ? $apkUrl : null,
            'apkSize'   => config('sipintar.apk_size', '52.5 MB'),
            'appVersion'   => '1.0.0',
            'buildNumber'  => '1',
        ]);
    }
}
