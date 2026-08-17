<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RecommendationController;
use App\Http\Middleware\CheckItgApiToken;

Route::middleware([CheckItgApiToken::class])->group(function () {
    // Endpoint 1: Untuk trigger komputasi AI & simpan ke DB
    Route::post('/process', [RecommendationController::class, 'process']);
    
    // Endpoint 2: Untuk LMS mengambil data video yang sudah siap dari DB
    Route::get('/rekomendasi', [RecommendationController::class, 'getRecommendation']);
});
