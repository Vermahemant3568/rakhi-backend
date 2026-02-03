<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\AdminViewController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Admin\GoalController;
use App\Http\Controllers\Admin\SystemSettingController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\RakhiRuleController;
use App\Http\Controllers\Admin\PromptTemplateController;
use App\Http\Controllers\Admin\AiModelController;
use App\Http\Controllers\Admin\MemoryPolicyController;
use App\Http\Controllers\Admin\UserController;

Route::get('/', function () {
    return view('welcome');
});

// Admin Web Routes
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', function () {
        return view('admin.auth.login');
    })->name('login');
    
    Route::post('/login', [AuthController::class, 'webLogin'])
        ->middleware('throttle:5,1')
        ->name('login.post');
    
    Route::middleware(['admin.auth', 'admin.audit'])->group(function () {
        Route::get('/dashboard', function () {
            return view('admin.dashboard');
        })->name('dashboard');
        
        // 2FA Routes
        Route::get('/security', [AuthController::class, 'securitySettings'])->name('security');
        Route::post('/2fa/enable', [AuthController::class, 'enable2FA'])->name('2fa.enable');
        Route::post('/2fa/confirm', [AuthController::class, 'confirm2FA'])->name('2fa.confirm');
        Route::post('/2fa/disable', [AuthController::class, 'disable2FA'])->name('2fa.disable');
        
        Route::get('/languages', [LanguageController::class, 'webIndex'])->name('languages.index');
        Route::post('/languages', [LanguageController::class, 'webStore'])->name('languages.store');
        Route::put('/languages/{id}', [LanguageController::class, 'webUpdate'])->name('languages.update');
        Route::delete('/languages/{id}', [LanguageController::class, 'webDestroy'])->name('languages.destroy');
        
        Route::get('/goals', [GoalController::class, 'webIndex'])->name('goals.index');
        Route::post('/goals', [GoalController::class, 'webStore'])->name('goals.store');
        Route::put('/goals/{id}', [GoalController::class, 'webUpdate'])->name('goals.update');
        Route::delete('/goals/{id}', [GoalController::class, 'webDestroy'])->name('goals.destroy');
        
        Route::get('/settings', [SystemSettingController::class, 'index'])->name('settings.index');
        Route::get('/settings/data', [SystemSettingController::class, 'data'])->name('settings.data');
        Route::post('/settings', [SystemSettingController::class, 'update'])->name('settings.update');
        
        Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/data', [PaymentController::class, 'transactionsData'])->name('payments.data');
        Route::get('/payments/stats', [PaymentController::class, 'transactionsStats'])->name('payments.stats');
        Route::get('/payments/revenue-data', [PaymentController::class, 'revenueData'])->name('revenue.data');
        
        // AI Control Panel Routes
        Route::resource('rakhi-rules', RakhiRuleController::class);
        Route::post('rakhi-rules/{rakhiRule}/toggle', [RakhiRuleController::class, 'toggle'])->name('rakhi-rules.toggle');
        
        Route::resource('prompt-templates', PromptTemplateController::class);
        Route::post('prompt-templates/{promptTemplate}/toggle', [PromptTemplateController::class, 'toggle'])->name('prompt-templates.toggle');
        
        Route::resource('ai-models', AiModelController::class);
        Route::post('ai-models/{aiModel}/activate', [AiModelController::class, 'activate'])->name('ai-models.activate');
        
        Route::resource('memory-policies', MemoryPolicyController::class);
        Route::post('memory-policies/{memoryPolicy}/toggle', [MemoryPolicyController::class, 'toggle'])->name('memory-policies.toggle');
        Route::post('memory-policies/{memoryPolicy}/toggle-storage', [MemoryPolicyController::class, 'toggleStorage'])->name('memory-policies.toggle-storage');
        Route::post('memory-policies/initialize-defaults', [MemoryPolicyController::class, 'initializeDefaults'])->name('memory-policies.initialize');
        Route::get('memory-policies-stats', [MemoryPolicyController::class, 'stats'])->name('memory-policies.stats');
        
        // User Management Routes
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/data', [UserController::class, 'data'])->name('users.data');
        Route::post('/users/{id}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
        Route::get('/users/{id}/activity-logs', [UserController::class, 'activityLogs'])->name('users.activity-logs');
        
        // Analytics Routes
        Route::get('/analytics/dashboard-data', [AnalyticsController::class, 'dashboardData'])->name('analytics.dashboard-data');
        Route::get('/analytics/user-engagement', [AnalyticsController::class, 'userEngagement'])->name('analytics.user-engagement');
        Route::get('/analytics/daily-active-users', [AnalyticsController::class, 'dailyActiveUsers'])->name('analytics.daily-active-users');
        Route::get('/analytics/mood-trend', [AnalyticsController::class, 'moodTrend'])->name('analytics.mood-trend');
        Route::get('/analytics/safety-alerts', [AnalyticsController::class, 'safetyAlerts'])->name('analytics.safety-alerts');
        
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    });
});
