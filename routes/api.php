<?php

use Illuminate\Support\Facades\Route;

Route::get('public/campuses', 'App\Http\Controllers\Api\PublicMarketplace\HomeController@campuses');

Route::prefix('auth')->group(function () {
    Route::get('login', 'App\Http\Controllers\Api\Auth\LoginController@index');
    Route::post('login', 'App\Http\Controllers\Api\Auth\LoginController@process');
    Route::get('me', 'App\Http\Controllers\Api\Auth\LoginController@me');
    Route::match(['post', 'delete'], 'logout', 'App\Http\Controllers\Api\Auth\LoginController@logout');
});

Route::middleware('role:admin_pt')->prefix('admin-pt')->name('admin-pt.')->group(function () {
    Route::get('dashboard', 'App\Http\Controllers\Api\AdminPt\DashboardController@index')->name('dashboard');
    Route::apiResource('prodi', 'App\Http\Controllers\Api\AdminPt\ProdiController')->except(['show'])->parameters(['prodi' => 'id_prodi']);
    Route::apiResource('kaprodi', 'App\Http\Controllers\Api\AdminPt\KaprodiController')->except(['show'])->parameters(['kaprodi' => 'id_kaprodi']);
    Route::apiResource('users', 'App\Http\Controllers\Api\AdminPt\UserController')->except(['show'])->parameters(['users' => 'id_user']);
    Route::get('validasi', 'App\Http\Controllers\Api\AdminPt\ValidasiController@index');
    Route::match(['put', 'patch'], 'validasi/{id_pendaftar}', 'App\Http\Controllers\Api\AdminPt\ValidasiController@updateStatus');
    Route::get('billing', 'App\Http\Controllers\Api\AdminPt\BillingController@index');
    Route::post('billing/topup-requests', 'App\Http\Controllers\Api\AdminPt\BillingController@requestTopup');
    Route::get('pengaturan', 'App\Http\Controllers\Api\AdminPt\PengaturanController@index');
    Route::match(['put', 'patch'], 'pengaturan', 'App\Http\Controllers\Api\AdminPt\PengaturanController@update');
    Route::get('laporan', 'App\Http\Controllers\Api\AdminPt\LaporanController@index');
    Route::get('audit-logs', 'App\Http\Controllers\Api\AdminPt\LaporanController@auditLog');
});

Route::middleware('role:akademik')->prefix('akademik')->name('akademik.')->group(function () {
    Route::get('dashboard', 'App\Http\Controllers\Api\Akademik\DashboardController@index')->name('dashboard');
    Route::get('antrean', 'App\Http\Controllers\Api\Akademik\AntreanController@index');
    Route::get('scanner', 'App\Http\Controllers\Api\Akademik\ScannerController@index');
    Route::post('scanner', 'App\Http\Controllers\Api\Akademik\ScannerController@saveScan');
    Route::post('scanner/auto-match', 'App\Http\Controllers\Api\Akademik\ScannerController@autoMatch');
});

Route::middleware('role:kaprodi')->prefix('kaprodi')->name('kaprodi.')->group(function () {
    Route::get('dashboard', 'App\Http\Controllers\Api\Kaprodi\DashboardController@index')->name('dashboard');
    Route::get('mahasiswa', 'App\Http\Controllers\Api\Kaprodi\MahasiswaController@index');
    Route::get('pemetaan', 'App\Http\Controllers\Api\Kaprodi\PemetaanController@index');
    Route::post('pemetaan', 'App\Http\Controllers\Api\Kaprodi\PemetaanController@store');
    Route::post('pemetaan/import', 'App\Http\Controllers\Api\Kaprodi\PemetaanController@import');
    Route::match(['put', 'patch'], 'pemetaan/{id_mk}', 'App\Http\Controllers\Api\Kaprodi\PemetaanController@update');
    Route::delete('pemetaan/{id_mk}', 'App\Http\Controllers\Api\Kaprodi\PemetaanController@delete');
    Route::get('pengaturan', 'App\Http\Controllers\Api\Kaprodi\PengaturanController@index');
    Route::get('pengaturan/{id_prodi}', 'App\Http\Controllers\Api\Kaprodi\PengaturanController@index');
    Route::match(['put', 'patch'], 'pengaturan/{id_prodi}', 'App\Http\Controllers\Api\Kaprodi\PengaturanController@update');
    Route::get('laporan', 'App\Http\Controllers\Api\Kaprodi\LaporanController@index');
    Route::get('validasi', 'App\Http\Controllers\Api\Kaprodi\ValidasiController@index');
    Route::get('validasi/{id_pendaftar}', 'App\Http\Controllers\Api\Kaprodi\ValidasiController@show');
    Route::match(['put', 'patch'], 'validasi/{id_pendaftar}', 'App\Http\Controllers\Api\Kaprodi\ValidasiController@process');

    // Tanda Tangan Kaprodi
    Route::get('tanda-tangan', 'App\Http\Controllers\Api\Kaprodi\TandaTanganController@show');
    Route::post('tanda-tangan', 'App\Http\Controllers\Api\Kaprodi\TandaTanganController@upload');
    Route::delete('tanda-tangan', 'App\Http\Controllers\Api\Kaprodi\TandaTanganController@destroy');
});

Route::middleware('role:kurikulum')->prefix('kurikulum')->name('kurikulum.')->group(function () {
    Route::apiResource('mata-kuliah', 'App\Http\Controllers\Api\Kurikulum\KurikulumController');
});

Route::middleware('role:superadmin')->prefix('superadmin')->name('superadmin.')->group(function () {
    Route::get('dashboard', 'App\Http\Controllers\Api\Superadmin\DashboardController@index')->name('dashboard');
    Route::apiResource('users', 'App\Http\Controllers\Api\Superadmin\UserController')->except(['show'])->parameters(['users' => 'id_user']);
    Route::apiResource('mitra', 'App\Http\Controllers\Api\Superadmin\MitraController')->except(['show'])->parameters(['mitra' => 'id_kampus']);
    Route::patch('mitra/{id_kampus}/status', 'App\Http\Controllers\Api\Superadmin\MitraController@updateStatus');
    Route::get('finance', 'App\Http\Controllers\Api\Superadmin\FinanceController@index');
    Route::patch('finance/topup-requests/{id_transaksi}/approve', 'App\Http\Controllers\Api\Superadmin\FinanceController@approve');
    Route::patch('finance/topup-requests/{id_transaksi}/reject', 'App\Http\Controllers\Api\Superadmin\FinanceController@reject');
    Route::post('finance/manual-topups', 'App\Http\Controllers\Api\Superadmin\FinanceController@manualTopup');
    Route::get('config', 'App\Http\Controllers\Api\Superadmin\ConfigController@index');
    Route::match(['put', 'patch'], 'config', 'App\Http\Controllers\Api\Superadmin\ConfigController@save');
    Route::get('notif', 'App\Http\Controllers\Api\Superadmin\NotifController@index');
    Route::match(['put', 'patch'], 'notif/{id_template}', 'App\Http\Controllers\Api\Superadmin\NotifController@update');
    Route::get('konversi', 'App\Http\Controllers\Api\Superadmin\KonversiController@index');
    Route::get('report', 'App\Http\Controllers\Api\Superadmin\ReportController@index');
    Route::get('audit', 'App\Http\Controllers\Api\Superadmin\AuditController@index');
    Route::get('backups', 'App\Http\Controllers\Api\Superadmin\BackupController@index');
    Route::get('backups/download', 'App\Http\Controllers\Api\Superadmin\BackupController@download');
});
