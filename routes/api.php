<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\AuthController;
use App\Http\Controllers\User\OnboardingController;
use App\Http\Controllers\User\SubscriptionController;
use App\Http\Controllers\User\MealVisionController;
use App\Http\Controllers\User\ChatController;
use App\Http\Controllers\User\VoiceController;
use App\Http\Controllers\User\PlanController;
use App\Http\Controllers\User\ProgressController;

// ─────────────────────────────────────────
// PUBLIC ROUTES — No auth needed
// ─────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/send-otp',    [AuthController::class, 'sendOtp']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/logout',     [AuthController::class, 'logout']);
});

// Public data for onboarding screens
Route::get('/languages',         [OnboardingController::class, 'languages']);
Route::get('/goals',             [OnboardingController::class, 'goals']);
Route::get('/subscription-plans',[SubscriptionController::class, 'plans']);
Route::get('/faq',               [OnboardingController::class, 'faq']);

// ─────────────────────────────────────────
// PROTECTED ROUTES — Auth required
// ─────────────────────────────────────────
Route::middleware(['user.auth'])->group(function () {

    // Auth
    Route::get('/me',              [AuthController::class, 'me']);
    Route::post('/update-profile', [AuthController::class, 'updateProfile']);
    Route::post('/update-fcm',     [AuthController::class, 'updateFcmToken']);

    // Onboarding — step by step
    Route::prefix('onboarding')->group(function () {
        Route::post('/language',       [OnboardingController::class, 'saveLanguage']);
        Route::post('/basic-info',     [OnboardingController::class, 'saveBasicInfo']);
        Route::post('/dob',            [OnboardingController::class, 'saveDob']);
        Route::post('/weight',         [OnboardingController::class, 'saveWeight']);
        Route::post('/height',         [OnboardingController::class, 'saveHeight']);
        Route::post('/goals',          [OnboardingController::class, 'saveGoals']);
        Route::post('/notifications',  [OnboardingController::class, 'saveNotification']);
        Route::post('/microphone',     [OnboardingController::class, 'saveMicrophone']);
        Route::post('/camera',         [OnboardingController::class, 'saveCamera']);
        Route::post('/complete',       [OnboardingController::class, 'completeOnboarding']);
        Route::get('/status',          [OnboardingController::class, 'status']);
    });

    // Subscription & Payment
    Route::prefix('subscription')->group(function () {
        Route::get('/status',           [SubscriptionController::class, 'status']);
        Route::post('/start-trial',     [SubscriptionController::class, 'startTrial']);
        Route::post('/create-order',    [SubscriptionController::class, 'createOrder']);
        Route::post('/verify-payment',  [SubscriptionController::class, 'verifyPayment']);
        Route::post('/cancel',          [SubscriptionController::class, 'cancel']);
    });

    // Meal Vision
    Route::prefix('meal')->group(function () {
        Route::post('/analyze',        [MealVisionController::class, 'analyze']);
        Route::get('/history',         [MealVisionController::class, 'history']);
        Route::get('/daily-summary',   [MealVisionController::class, 'dailySummary']);
    });

    // Chat
    Route::prefix('chat')->group(function () {
        Route::post('/session/start',         [ChatController::class, 'startSession']);
        Route::post('/session/initiate-call', [ChatController::class, 'initiateConsultationCall']);
        Route::post('/send',                  [ChatController::class, 'sendMessage']);
        Route::get('/history/{sessionId}',    [ChatController::class, 'history']);
        Route::get('/sessions',               [ChatController::class, 'sessions']);
    });

    // Voice
    Route::prefix('voice')->group(function () {
        Route::post('/session/start',  [VoiceController::class, 'startSession']);
        Route::post('/send',           [VoiceController::class, 'sendVoice']);
        Route::post('/session/end',    [VoiceController::class, 'endSession']);
    });

    // Progress
    Route::prefix('progress')->group(function () {
        Route::post('/checkin',        [ProgressController::class, 'checkin']);
        Route::get('/streak',          [ProgressController::class, 'streak']);
        Route::get('/summary',         [ProgressController::class, 'summary']);
        Route::get('/mood-history',    [ProgressController::class, 'moodHistory']);
    });

    // Plans (PDF)
    Route::prefix('plans')->group(function () {
        Route::get('/',                [PlanController::class, 'index']);
        Route::get('/{id}/download',   [PlanController::class, 'download']);
    });
});

// Razorpay Webhook — no auth
Route::post('/webhook/razorpay',
    [App\Http\Controllers\Webhook\RazorpayController::class, 'handle']
);