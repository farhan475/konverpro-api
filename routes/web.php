<?php

use App\Http\Controllers\VerifikasiPublikController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/verify/{nomor}', [VerifikasiPublikController::class, 'show'])
    ->name('verify.dokumen');
