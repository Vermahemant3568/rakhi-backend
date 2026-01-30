<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Admin\GoalController;

Route::prefix('admin')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('admin.auth')->group(function () {
        // Language Manager API Routes
        Route::get('/languages', [LanguageController::class, 'index']);
        Route::post('/languages', [LanguageController::class, 'store']);
        Route::put('/languages/{id}', [LanguageController::class, 'update']);
        Route::delete('/languages/{id}', [LanguageController::class, 'destroy']);
        
        // Goal Manager API Routes
        Route::get('/goals', [GoalController::class, 'index']);
        Route::post('/goals', [GoalController::class, 'store']);
        Route::put('/goals/{id}', [GoalController::class, 'update']);
        Route::delete('/goals/{id}', [GoalController::class, 'destroy']);
    });
});