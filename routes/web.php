<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RecommendationController;

// Rute untuk menampilkan halaman utama UI Demo
Route::get('/', [RecommendationController::class, 'index'])->name('demo.index');

// Rute untuk memproses form pengiriman (submit materi baru via web)
Route::post('/process-recommendation', [RecommendationController::class, 'process'])->name('demo.process');
