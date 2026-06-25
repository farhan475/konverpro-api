<?php

use App\Http\Controllers\Api\Admin;
use App\Http\Controllers\Api\Akademik;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\Kaprodi;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PublicPortalController;
use App\Http\Controllers\Api\Superadmin;
use App\Http\Controllers\Api\Superadmin\ProdiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Auth
Route::prefix('auth')->group(function () {
    Route::post('login', [LoginController::class, 'login'])->middleware('throttle:login');
    Route::middleware('auth.cookie')->group(function () {
        Route::get('me', [LoginController::class, 'me']);
        Route::post('logout', [LoginController::class, 'logout']);
    });
});

Route::prefix('public')->middleware('throttle:30,1')->group(function () {
    Route::get('verify/{document}', [PublicPortalController::class, 'verify']);
    Route::get('portal/{token}', [PublicPortalController::class, 'portal']);
    Route::post('portal/{token}/appeals', [PublicPortalController::class, 'submitAppeal']);
    Route::get('portal/{token}/download-ba', [PublicPortalController::class, 'downloadBa']);
});

// Protected Routes
Route::middleware(['auth.cookie'])->group(function () {

    // Referensi (Shared)
    Route::get('referensi/prodi', [ProdiController::class, 'reference']);

    // Files
    Route::get('files/excel/{pendaftar}', [FileController::class, 'showExcel']);
    Route::get('files/pdf/{pendaftar}', [FileController::class, 'showPdf']);
    Route::get('files/tanda-tangan/{userId}', [FileController::class, 'showTandaTangan']);
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead']);

    // Superadmin
    Route::middleware('role:superadmin')->prefix('superadmin')->group(function () {
        Route::get('dashboard', [Superadmin\DashboardController::class, 'index']);
        Route::apiResource('users', Superadmin\UserController::class);
        Route::get('prodi/{prodi}/settings', [ProdiController::class, 'getSettings']);
        Route::put('prodi/{prodi}/settings', [ProdiController::class, 'updateSettings']);
        Route::apiResource('prodi', ProdiController::class);
        Route::apiResource('kamus-sinonim', Superadmin\KamusSinonimController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('config', [Superadmin\ConfigController::class, 'index']);
        Route::put('config', [Superadmin\ConfigController::class, 'update']);
        Route::get('audit', [Superadmin\AuditController::class, 'index']);
        Route::get('laporan', [Superadmin\LaporanController::class, 'index']);
        Route::get('equivalencies', [Superadmin\CourseEquivalencyController::class, 'index']);
        Route::put('equivalencies/{equivalency}', [Superadmin\CourseEquivalencyController::class, 'update']);
    });

    // Admin
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('dashboard', [Admin\DashboardController::class, 'index']);
        Route::get('template-excel', [Admin\TemplateController::class, 'download']);
        Route::get('pendaftar', [Admin\PendaftarController::class, 'index']);
        Route::post('pendaftar', [Admin\PendaftarController::class, 'store']);
        Route::get('pendaftar/{pendaftar}', [Admin\PendaftarController::class, 'show']);
    });

    // Kaprodi needs read access for manual mapping overrides.
    Route::middleware('role:akademik,kaprodi')->prefix('akademik')->group(function () {
        Route::get('kurikulum', [Akademik\KurikulumController::class, 'index'])->name('kurikulum.index');
        Route::get('kurikulum/{kurikulum}', [Akademik\KurikulumController::class, 'show'])->name('kurikulum.show');
    });

    // Akademik
    Route::middleware('role:akademik')->prefix('akademik')->group(function () {
        Route::get('dashboard', [Akademik\DashboardController::class, 'index']);
        Route::get('antrean', [Akademik\AntreanController::class, 'index']);
        Route::get('antrean/{pendaftar}', [Akademik\AntreanController::class, 'show']);
        Route::put('antrean/{pendaftar}', [Akademik\AntreanController::class, 'update']);
        Route::post('antrean/{pendaftar}/proses', [Akademik\AntreanController::class, 'proses']);
        Route::post('antrean/{pendaftar}/confirm', [Akademik\AntreanController::class, 'confirmToKaprodi']);
        Route::post('kurikulum', [Akademik\KurikulumController::class, 'store'])->name('kurikulum.store');
        Route::match(['put', 'patch'], 'kurikulum/{kurikulum}', [Akademik\KurikulumController::class, 'update'])->name('kurikulum.update');
        Route::delete('kurikulum/{kurikulum}', [Akademik\KurikulumController::class, 'destroy'])->name('kurikulum.destroy');
        Route::apiResource('kamus-sinonim', Akademik\KamusSinonimController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('appeals', [Akademik\AppealController::class, 'index']);
        Route::put('appeals/{appeal}', [Akademik\AppealController::class, 'resolve']);
    });

    // Kaprodi
    Route::middleware('role:kaprodi')->prefix('kaprodi')->group(function () {
        Route::get('dashboard', [Kaprodi\DashboardController::class, 'index']);
        Route::get('validasi', [Kaprodi\ValidasiController::class, 'index']);
        Route::post('validasi/bulk-approve', [Kaprodi\ValidasiController::class, 'bulkApprove']);
        Route::get('validasi/{pendaftar}', [Kaprodi\ValidasiController::class, 'show']);
        Route::put('validasi/{hasilKonversi}', [Kaprodi\ValidasiController::class, 'updateHasil']);
        Route::post('validasi/{pendaftar}/approve', [Kaprodi\ValidasiController::class, 'approve']);
        Route::post('validasi/{pendaftar}/revisi', [Kaprodi\ValidasiController::class, 'revisi']);
        Route::post('validasi/{pendaftar}/reject', [Kaprodi\ValidasiController::class, 'reject']);
        Route::get('validasi/{pendaftar}/download-ba', [Kaprodi\DocumentController::class, 'downloadBa']);
        Route::post('validasi/{pendaftar}/send-ba-whatsapp', [Kaprodi\DocumentController::class, 'sendBaWhatsapp'])
            ->middleware('throttle:3,1');
        Route::post('validasi/{pendaftar}/revoke-ba', [Kaprodi\DocumentController::class, 'revokeBa']);
        Route::post('validasi/{pendaftar}/replace-ba', [Kaprodi\DocumentController::class, 'replaceBa']);
        Route::get('laporan', [Kaprodi\LaporanController::class, 'index']);
        Route::get('tanda-tangan', [Kaprodi\TandaTanganController::class, 'index']);
        Route::post('tanda-tangan', [Kaprodi\TandaTanganController::class, 'store']);
        Route::delete('tanda-tangan', [Kaprodi\TandaTanganController::class, 'destroy']);
    });

});
