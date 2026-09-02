<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InstallController;

Route::get('/', function () {
    return view('welcome');
});

// Halaman publik download APK SIPINTAR (pilot testing).
// Tanpa auth/JWT/database — dapat diakses siapa pun.
Route::get('/install', [InstallController::class, 'index'])->name('install');
