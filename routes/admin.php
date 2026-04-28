<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ApiManagerController;
use App\Http\Controllers\Admin\CoachController;
use App\Http\Controllers\Admin\PromptController;
use App\Http\Controllers\Admin\RulesController;
use App\Http\Controllers\Admin\GoalController;
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Admin\SubscriptionPlanController;
use App\Http\Controllers\Admin\KnowledgeBaseController;
use App\Http\Controllers\Admin\UserManagerController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\ProgressController;
use App\Http\Controllers\Admin\JobMonitorController;

// Public
Route::prefix('admin')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    // Protected
    Route::middleware(['admin.auth'])->group(function () {

        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index']);

        // API Manager
        Route::get('/api-manager',                       [ApiManagerController::class, 'index']);
        Route::post('/api-manager',                      [ApiManagerController::class, 'store']);
        Route::put('/api-manager/{apiService}/toggle',   [ApiManagerController::class, 'toggle']);
        Route::put('/api-manager/{apiService}/update',   [ApiManagerController::class, 'update']);
        Route::put('/api-manager/{apiService}',          [ApiManagerController::class, 'update']);
        Route::post('/api-manager/{apiService}/test',    [ApiManagerController::class, 'test']);
        Route::delete('/api-manager/{apiService}',       [ApiManagerController::class, 'destroy']);
        // Legacy toggle route (old frontend)
        Route::put('/api-manager/{id}/toggle',           [ApiManagerController::class, 'toggleById']);

        // LLM Config
        Route::get('/llm-configs',              [ApiManagerController::class, 'llmList']);
        Route::post('/llm-configs',             [ApiManagerController::class, 'llmStore']);
        Route::put('/llm-configs/{id}',         [ApiManagerController::class, 'llmUpdate']);
        Route::put('/llm-configs/{id}/activate',[ApiManagerController::class, 'llmActivate']);

        // Coaches
        Route::get('/coaches',                    [CoachController::class, 'index']);
        Route::post('/coaches',                   [CoachController::class, 'store']);
        Route::get('/coaches/{id}',               [CoachController::class, 'show']);
        Route::put('/coaches/{id}',               [CoachController::class, 'update']);
        Route::put('/coaches/{id}/toggle',        [CoachController::class, 'toggle']);
        Route::put('/coaches/{id}/toggle-launch', [CoachController::class, 'toggleLaunch']);
        Route::post('/coaches/reorder',           [CoachController::class, 'reorder']);
        Route::delete('/coaches/{id}',            [CoachController::class, 'destroy']);

        // Prompt Templates
        Route::get('/prompts',              [PromptController::class, 'index']);
        Route::post('/prompts',             [PromptController::class, 'store']);
        Route::put('/prompts/{id}',         [PromptController::class, 'update']);
        Route::put('/prompts/{id}/toggle',  [PromptController::class, 'toggle']);
        Route::delete('/prompts/{id}',      [PromptController::class, 'destroy']);

        // Rakhi Rules
        Route::get('/rules',                [RulesController::class, 'index']);
        Route::post('/rules',               [RulesController::class, 'store']);
        Route::put('/rules/{id}',           [RulesController::class, 'update']);
        Route::put('/rules/{id}/toggle',    [RulesController::class, 'toggle']);
        Route::delete('/rules/{id}',        [RulesController::class, 'destroy']);

        // Goals
        Route::get('/goals',                [GoalController::class, 'index']);
        Route::post('/goals',               [GoalController::class, 'store']);
        Route::post('/goals/reorder',       [GoalController::class, 'reorder']);
        Route::get('/goals/{id}',           [GoalController::class, 'show']);
        Route::put('/goals/{id}',           [GoalController::class, 'update']);
        Route::put('/goals/{id}/toggle',    [GoalController::class, 'toggle']);
        Route::delete('/goals/{id}',        [GoalController::class, 'destroy']);

        // Languages
        Route::get('/languages',            [LanguageController::class, 'index']);
        Route::post('/languages',           [LanguageController::class, 'store']);
        Route::put('/languages/{id}',       [LanguageController::class, 'update']);
        Route::put('/languages/{id}/toggle',[LanguageController::class, 'toggle']);

        // Subscription Plans
        Route::get('/plans',                [SubscriptionPlanController::class, 'index']);
        Route::post('/plans',               [SubscriptionPlanController::class, 'store']);
        Route::put('/plans/{id}',           [SubscriptionPlanController::class, 'update']);
        Route::put('/plans/{id}/toggle',    [SubscriptionPlanController::class, 'toggle']);

        // Knowledge Base
        Route::get('/knowledge',                [KnowledgeBaseController::class, 'index']);
        Route::post('/knowledge',               [KnowledgeBaseController::class, 'store']);
        Route::post('/knowledge/sync-all',      [KnowledgeBaseController::class, 'syncAll']);
        Route::put('/knowledge/{id}',           [KnowledgeBaseController::class, 'update']);
        Route::put('/knowledge/{id}/toggle',    [KnowledgeBaseController::class, 'toggle']);
        Route::post('/knowledge/{id}/sync',     [KnowledgeBaseController::class, 'syncToVector']);
        Route::delete('/knowledge/{id}',        [KnowledgeBaseController::class, 'destroy']);

        // User Manager
        Route::get('/users',                           [UserManagerController::class, 'index']);
        Route::get('/users/{id}',                      [UserManagerController::class, 'show']);
        Route::put('/users/{id}/ban',                  [UserManagerController::class, 'ban']);
        Route::put('/users/{id}/unban',                [UserManagerController::class, 'unban']);
        Route::get('/users/{id}/chats',                [UserManagerController::class, 'chats']);
        Route::get('/users/{id}/plans',                [UserManagerController::class, 'plans']);
        Route::get('/users/{id}/meal-logs',            [UserManagerController::class, 'mealLogs']);
        Route::post('/users/{id}/regenerate-plans',    [UserManagerController::class, 'regeneratePlans']);

        // All user plans (global listing)
        Route::get('/user-plans',                      [UserManagerController::class, 'allPlans']);

        // Finance
        Route::get('/finance', [FinanceController::class, 'index']);

        // Job Monitor
        Route::get('/jobs',                      [JobMonitorController::class, 'index']);
        Route::post('/jobs/retry-failed',        [JobMonitorController::class, 'retryFailed']);
        Route::post('/jobs/retry-all-failed',    [JobMonitorController::class, 'retryAllFailed']);
        Route::post('/jobs/reset-stuck-plan',    [JobMonitorController::class, 'resetStuckPlan']);
        Route::delete('/jobs/failed/{id}',       [JobMonitorController::class, 'deleteFailed']);
        Route::delete('/jobs/clear-failed',      [JobMonitorController::class, 'clearAllFailed']);
        Route::delete('/jobs/clear-stale',       [JobMonitorController::class, 'clearStaleJobs']);
        Route::delete('/jobs/clear-all-failed',  [JobMonitorController::class, 'clearAllFailed']);

        // Progress
        Route::prefix('progress')->group(function () {
            Route::get('/overview',      [ProgressController::class, 'overview']);
            Route::get('/streaks',       [ProgressController::class, 'streaks']);
            Route::get('/summary',       [ProgressController::class, 'summary']);
            Route::get('/chat-activity', [ProgressController::class, 'chatActivity']);
            Route::get('/stuck-plans',   [ProgressController::class, 'stuckPlans']);
        });
    });
});