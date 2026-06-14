<?php

use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Superadmin as Superadmin;
use App\Http\Controllers\Api\Admin as Admin;
use App\Http\Controllers\Api\Akademik as Akademik;
use App\Http\Controllers\Api\Kaprodi as Kaprodi;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Auth
Route::prefix('auth')->group(function () {
    Route::post('login', [LoginController::class, 'login']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [LoginController::class, 'me']);
        Route::post('logout', [LoginController::class, 'logout']);
    });
});

    // Protected Routes
    Route::middleware(['auth:sanctum'])->group(function () {

    // Files
    Route::get('files/excel/{pendaftar}', [\App\Http\Controllers\Api\FileController::class, 'showExcel']);
    Route::get('files/pdf/{pendaftar}', [\App\Http\Controllers\Api\FileController::class, 'showPdf']);

    // Superadmin
    Route::middleware('role:superadmin')->prefix('superadmin')->group(function () {
        Route::get('dashboard', [Superadmin\DashboardController::class, 'index']);
        Route::apiResource('users', Superadmin\UserController::class);
        Route::apiResource('prodi', Superadmin\ProdiController::class);
        Route::apiResource('kamus-sinonim', Superadmin\KamusSinonimController::class)->only(['index', 'store', 'destroy']);
        Route::get('config', [Superadmin\ConfigController::class, 'index']);
        Route::put('config', [Superadmin\ConfigController::class, 'update']);
        Route::get('audit', [Superadmin\AuditController::class, 'index']);
    });

    // Admin
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('dashboard', [Admin\DashboardController::class, 'index']);
        Route::get('template/download', [Admin\TemplateController::class, 'download']);
        Route::get('pendaftar', [Admin\PendaftarController::class, 'index']);
        Route::post('pendaftar', [Admin\PendaftarController::class, 'store']);
        Route::get('pendaftar/{pendaftar}', [Admin\PendaftarController::class, 'show']);
    });

    // Akademik & Kaprodi shared kurikulum access
    Route::middleware('role:akademik,kaprodi')->prefix('akademik')->group(function () {
        Route::apiResource('kurikulum', Akademik\KurikulumController::class);
    });

    // Akademik
    Route::middleware('role:akademik')->prefix('akademik')->group(function () {
        Route::get('dashboard', [Akademik\DashboardController::class, 'index']);
        Route::get('antrean', [Akademik\AntreanController::class, 'index']);
        Route::get('antrean/{pendaftar}', [Akademik\AntreanController::class, 'show']);
        Route::post('antrean/{pendaftar}/proses', [Akademik\AntreanController::class, 'proses']);
        Route::apiResource('kamus-sinonim', Akademik\KamusSinonimController::class)->only(['index', 'store', 'destroy']);
    });

    // Kaprodi
    Route::middleware('role:kaprodi')->prefix('kaprodi')->group(function () {
        Route::get('dashboard', [Kaprodi\DashboardController::class, 'index']);
        Route::get('validasi', [Kaprodi\ValidasiController::class, 'index']);
        Route::get('validasi/{pendaftar}', [Kaprodi\ValidasiController::class, 'show']);
        Route::put('hasil-konversi/{hasilKonversi}', [Kaprodi\ValidasiController::class, 'updateHasil']);
        Route::post('validasi/{pendaftar}/approve', [Kaprodi\ValidasiController::class, 'approve']);
        Route::post('validasi/{pendaftar}/revisi', [Kaprodi\ValidasiController::class, 'revisi']);
        Route::post('validasi/{pendaftar}/reject', [Kaprodi\ValidasiController::class, 'reject']);
        Route::get('laporan', [Kaprodi\LaporanController::class, 'index']);
        Route::post('tanda-tangan', [Kaprodi\TandaTanganController::class, 'store']);
        Route::delete('tanda-tangan', [Kaprodi\TandaTanganController::class, 'destroy']);
    });

});

