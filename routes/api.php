<?php

use App\Http\Controllers\Api\LaporanController;
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

Route::get('/ping', fn () => response()->json([
    'success' => true,
    'message' => 'Laravel API berhasil',
]));

Route::get('/unit-layanan', [UnitLayananController::class, 'index']);
Route::get('/kategori', [KategoriController::class, 'index']);
Route::post('/laporan', [
    LaporanController::class,
    'store',
])->middleware(['throttle:10,1', 'optional.unified.auth', 'password.changed']);

Route::get('/laporan/perangkat', [
    LaporanController::class,
    'anonymousIndex',
])->middleware('password.changed');

Route::post('/password-reset-requests', [
    PasswordResetRequestController::class,
    'store',
])->middleware('throttle:3,1');

Route::get('/laporan/{kodeTiket}', [
    LaporanController::class,
    'show',
])->middleware('optional.unified.auth');

Route::post('/device-tokens', [
    DeviceTokenController::class,
    'store',
])->middleware(['throttle:10,1', 'optional.unified.auth', 'password.changed']);

Route::get('/warga/laporan', [
    WargaLaporanController::class,
    'index',
])->middleware(['unified.auth', 'password.changed', 'role:warga']);

Route::prefix('auth')->group(function () {

    Route::post('/login', [
        AuthController::class,
        'login',
    ])->middleware('throttle:5,1');

    Route::post('/register', [
        AuthController::class,
        'register',
    ])->middleware('throttle:5,1');

    Route::middleware('unified.auth')->group(function () {

        Route::get('/me', [
            AuthController::class,
            'me',
        ]);

        Route::post('/logout', [
            AuthController::class,
            'logout',
        ]);

        Route::post('/change-password', [
            AuthController::class,
            'changePassword',
        ]);
    });
});

Route::prefix('admin')
    ->middleware(['unified.auth', 'password.changed', 'role:admin,petugas'])
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

        Route::middleware('role:admin')->prefix('users')->group(function () {
            Route::get('/', [AdminUserController::class, 'index']);
            Route::post('/{id}/reset-password', [AdminUserController::class, 'resetPassword']);
        });

        Route::middleware('role:admin')->prefix('password-reset-requests')->group(function () {
            Route::get('/', [PasswordResetRequestController::class, 'index']);
            Route::post('/{id}/verify', [PasswordResetRequestController::class, 'verify']);
            Route::post('/{id}/reject', [PasswordResetRequestController::class, 'reject']);
            Route::post('/{id}/reset', [PasswordResetRequestController::class, 'reset']);
            Route::delete('/{id}', [PasswordResetRequestController::class, 'destroy']);
        });
    });

Route::post('/test-notification', [TestNotificationController::class, 'send'])
    ->middleware(['unified.auth', 'role:admin']);
