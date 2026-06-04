<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Import Controllers
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Akademik\AntreanController;
use App\Http\Controllers\Api\Akademik\ScannerController;
use App\Http\Controllers\Api\Kaprodi\DashboardController as KaprodiDashboard;
use App\Http\Controllers\Api\Kaprodi\ValidasiController;
use App\Http\Controllers\Api\Kaprodi\MahasiswaController;
use App\Http\Controllers\Api\Kaprodi\PemetaanController;
use App\Http\Controllers\Api\Kaprodi\LaporanController as KaprodiLaporan;
use App\Http\Controllers\Api\Kaprodi\PengaturanController as KaprodiPengaturan;
use App\Http\Controllers\Api\AdminPt\DashboardController as AdminPtDashboard;
use App\Http\Controllers\Api\AdminPt\UserController as AdminPtUser;
use App\Http\Controllers\Api\AdminPt\ProdiController as AdminPtProdi;
use App\Http\Controllers\Api\AdminPt\ConfigController as AdminPtConfig;
use App\Http\Controllers\Api\Superadmin\DashboardController as SuperadminDashboard;
use App\Http\Controllers\Api\Superadmin\MitraController;
use App\Http\Controllers\Api\Superadmin\NotifikasiController;
use App\Http\Controllers\Api\Superadmin\AuditController;
use App\Http\Controllers\Api\Superadmin\ConfigController as SuperadminConfig;

Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Akademik Module
Route::middleware(['auth:sanctum'])->prefix('akademik')->group(function () {
    Route::get('/antrean', [AntreanController::class, 'index']);
    Route::get('/antrean/{id}', [AntreanController::class, 'show']);
    Route::post('/scan/auto-match', [ScannerController::class, 'autoMatch']);
    Route::post('/scan/save', [ScannerController::class, 'saveScan']);
});

// Kaprodi Module
Route::middleware(['auth:sanctum'])->prefix('kaprodi')->group(function () {
    Route::get('/dashboard', [KaprodiDashboard::class, 'index']);
    Route::get('/validasi', [ValidasiController::class, 'index']);
    Route::get('/validasi/{id}', [ValidasiController::class, 'show']);
    Route::get('/validasi/{id}/print-data', [ValidasiController::class, 'printData']);
    Route::get('/validasi/{id}/download-pdf', [ValidasiController::class, 'downloadPdf']);
    Route::post('/validasi/{id}/process', [ValidasiController::class, 'process']);
    Route::post('/validasi/bulk-process', [ValidasiController::class, 'bulkProcess']);
    Route::get('/mahasiswa', [MahasiswaController::class, 'index']);
    Route::get('/pemetaan', [PemetaanController::class, 'index']);
    Route::get('/laporan', [KaprodiLaporan::class, 'index']);
    Route::get('/pengaturan', [KaprodiPengaturan::class, 'index']);
    Route::put('/pengaturan/prodi/{id}', [KaprodiPengaturan::class, 'updateProdi']);
});

// Admin PT Module
Route::middleware(['auth:sanctum'])->prefix('admin-pt')->group(function () {
    Route::get('/dashboard', [AdminPtDashboard::class, 'index']);
    Route::apiResource('users', AdminPtUser::class);
    Route::apiResource('prodi', AdminPtProdi::class);
    Route::get('/config', [AdminPtConfig::class, 'index']);
    Route::put('/config', [AdminPtConfig::class, 'update']);
});

// Superadmin Module
Route::middleware(['auth:sanctum'])->prefix('superadmin')->group(function () {
    Route::get('/dashboard', [SuperadminDashboard::class, 'index']);
    Route::apiResource('mitra', MitraController::class);
    Route::get('/notifikasi', [NotifikasiController::class, 'index']);
    Route::put('/notifikasi/{id}', [NotifikasiController::class, 'update']);
    Route::get('/audit', [AuditController::class, 'index']);
    Route::get('/config', [SuperadminConfig::class, 'index']);
    Route::put('/config', [SuperadminConfig::class, 'update']);
});
