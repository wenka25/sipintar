<?php

use App\Http\Controllers\Api\LaporanController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AdminLaporanController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\TestNotificationController;
use App\Http\Controllers\Api\WargaLaporanController;
use App\Http\Controllers\Api\UnitLayananController;
use App\Http\Controllers\Api\AdminExportController;
use App\Http\Controllers\Api\KategoriController;
use App\Http\Controllers\Api\PasswordResetRequestController;

// Public routes
Route::get('/ping', fn () => response()->json([
    'success' => true,
    'message' => 'Laravel API berhasil',
]));

Route::get('/db-check', fn () => response()->json([
    'database' => DB::connection()->getDatabaseName(),
    'unit_layanan_count' => DB::table('unit_layanan')->count(),
]));

Route::get('/unit-layanan', [UnitLayananController::class, 'index']);
Route::get('/kategori', [KategoriController::class, 'index']);
Route::post('/laporan', [
    LaporanController::class,
    'store',
])->middleware('optional.unified.auth');

Route::get('/laporan/perangkat', [
    LaporanController::class,
    'anonymousIndex',
]);

// TASK B: Pengajuan permintaan reset password oleh user (tanpa login).
// Terpisah total dari laporan; tidak menyentuh tabel/statistik laporan.
Route::post('/password-reset-requests', [
    PasswordResetRequestController::class,
    'store',
]);

Route::get('/laporan/{kodeTiket}', [
    LaporanController::class,
    'show',
])->middleware('optional.unified.auth');

Route::post('/device-tokens', [
    DeviceTokenController::class,
    'store',
])->middleware('optional.unified.auth');

Route::get('/warga/laporan', [
    WargaLaporanController::class,
    'index',
])->middleware(['unified.auth', 'role:warga']);

// Authentication routes
Route::prefix('auth')->group(function () {

    Route::post('/login', [
        AuthController::class,
        'login',
    ]);

    Route::post('/register', [
        AuthController::class,
        'register',
    ]);

    // TASK A: "Lupa Password" tidak lagi menggunakan OTP/email.
    // User menghubungi Admin; Admin mereset password via endpoint
    // /admin/users/{id}/reset-password. User wajib mengganti password
    // setelah login dengan password sementara (must_change_password).

    Route::middleware('unified.auth')->group(function () {

        Route::get('/me', [
            AuthController::class,
            'me',
        ]);

        Route::post('/logout', [
            AuthController::class,
            'logout',
        ]);

        // Ganti password oleh user yang login (wajib setelah reset oleh Admin).
        Route::post('/change-password', [
            AuthController::class,
            'changePassword',
        ]);
    });
});

//admin routes
Route::prefix('admin')
    ->middleware(['unified.auth', 'role:admin,petugas'])
    ->group(function () {

        Route::get('/laporan', [
            AdminLaporanController::class,
            'index',
        ]);

        Route::get('/laporan/export/excel', [AdminExportController::class, 'excel']);
        Route::get('/laporan/export/pdf', [AdminExportController::class, 'pdf']);

        Route::get('/laporan/{id}', [
            AdminLaporanController::class,
            'show',
        ]);

        Route::delete('/laporan/{id}', [
            AdminLaporanController::class,
            'destroy',
        ])->middleware('role:admin');

        Route::put('/laporan/{id}/status', [
            AdminLaporanController::class,
            'updateStatus',
        ]);

        Route::post('/laporan/{id}/balasan', [
            AdminLaporanController::class,
            'storeBalasan',
        ]);

        Route::middleware('role:admin')->prefix('users/{id}/unit-layanan')->group(function () {
            Route::get('/', [UnitLayananController::class, 'assignments']);
            Route::post('/', [UnitLayananController::class, 'assign']);
            Route::delete('/{unitId}', [UnitLayananController::class, 'unassign']);
        });

        // TASK A: Manajemen Pengguna (khusus admin, bukan petugas).
        Route::middleware('role:admin')->prefix('users')->group(function () {
            Route::get('/', [AdminUserController::class, 'index']);
            Route::post('/{id}/reset-password', [AdminUserController::class, 'resetPassword']);
        });

        // TASK B: Admin memproses permintaan reset password (admin saja).
        Route::middleware('role:admin')->prefix('password-reset-requests')->group(function () {
            Route::get('/', [PasswordResetRequestController::class, 'index']);
            Route::post('/{id}/verify', [PasswordResetRequestController::class, 'verify']);
            Route::post('/{id}/reject', [PasswordResetRequestController::class, 'reject']);
            Route::post('/{id}/reset', [PasswordResetRequestController::class, 'reset']);
        });
    });

Route::post('/test-notification', [TestNotificationController::class, 'send'])
    ->middleware(['unified.auth', 'role:admin']);
