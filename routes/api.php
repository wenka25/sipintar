<?php

use App\Http\Controllers\Api\LaporanController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminLaporanController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\TestNotificationController;
use App\Http\Controllers\Api\WargaLaporanController;
use App\Http\Controllers\Api\UnitLayananController;
use App\Http\Controllers\Api\AdminExportController;
use App\Http\Controllers\Api\KategoriController;

// Public routes
Route::get('/ping', fn () => response()->json([
    'success' => true,
    'message' => 'Laravel API berhasil',
]));

Route::get('/db-check', fn () => response()->json([
    'database' => DB::connection()->getDatabaseName(),
    'unit_layanan_count' => DB::table('unit_layanan')->count(),
]));

Route::get('/db-check', function () {
    $connection = DB::connection();

    return response()->json([
        'database' => $connection->getDatabaseName(),
        'host' => config('database.connections.mysql.host'),
        'port' => config('database.connections.mysql.port'),
        'username' => config('database.connections.mysql.username'),
        'unit_layanan_count' => DB::table('unit_layanan')->count(),
    ]);
});

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

    Route::middleware('unified.auth')->group(function () {

        Route::get('/me', [
            AuthController::class,
            'me',
        ]);

        Route::post('/logout', [
            AuthController::class,
            'logout',
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
    });

Route::post('/test-notification', [TestNotificationController::class, 'send'])
    ->middleware(['unified.auth', 'role:admin']);
