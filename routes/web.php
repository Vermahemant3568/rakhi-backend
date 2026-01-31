<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminViewController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Admin\GoalController;
use App\Http\Controllers\Admin\SystemSettingController;
use App\Http\Controllers\Admin\PaymentController;

Route::get('/', function () {
    return view('welcome');
});

// Admin Web Routes
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', function () {
        return view('admin.auth.login');
    })->name('login');
    
    Route::post('/login', [AuthController::class, 'webLogin'])->name('login.post');
    
    Route::middleware('admin.auth')->group(function () {
        Route::get('/dashboard', function () {
            return view('admin.dashboard');
        })->name('dashboard');
        
        Route::get('/languages', [LanguageController::class, 'webIndex'])->name('languages.index');
        Route::post('/languages', [LanguageController::class, 'webStore'])->name('languages.store');
        Route::put('/languages/{id}', [LanguageController::class, 'webUpdate'])->name('languages.update');
        Route::delete('/languages/{id}', [LanguageController::class, 'webDestroy'])->name('languages.destroy');
        
        Route::get('/goals', [GoalController::class, 'webIndex'])->name('goals.index');
        Route::post('/goals', [GoalController::class, 'webStore'])->name('goals.store');
        Route::put('/goals/{id}', [GoalController::class, 'webUpdate'])->name('goals.update');
        Route::delete('/goals/{id}', [GoalController::class, 'webDestroy'])->name('goals.destroy');
        
        Route::get('/settings', [SystemSettingController::class, 'index'])->name('settings.index');
        Route::post('/settings', [SystemSettingController::class, 'update'])->name('settings.update');
        
        Route::get('/payments', [PaymentController::class, 'transactions'])->name('payments.index');
        Route::get('/revenue', [PaymentController::class, 'revenueSummary'])->name('revenue.summary');
        Route::get('/subscribers/active', [PaymentController::class, 'activeSubscribers'])->name('subscribers.active');
        Route::get('/subscribers/trial', [PaymentController::class, 'trialUsers'])->name('subscribers.trial');
        
        Route::post('/logout', function () {
            session()->forget(['admin_id', 'admin_email']);
            return redirect()->route('admin.login');
        })->name('logout');
    });
});
