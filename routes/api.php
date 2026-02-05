<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\VoiceCallController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\MemoryTestController;

Route::post('/send-otp', [AuthController::class, 'sendOtp']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);

// Public routes for onboarding data
Route::get('/languages', [OnboardingController::class, 'getLanguages']);
Route::get('/goals', [OnboardingController::class, 'getGoals']);

Route::middleware('auth:sanctum')->group(function () {

    // App entry decision API
    Route::get('/app/status', [SubscriptionController::class, 'appStatus']);

    // Start trial after onboarding + payment
    Route::post('/subscription/start-trial', [SubscriptionController::class, 'startTrial']);

    // Trial
    Route::post('/payment/trial/order', [PaymentController::class, 'createTrialOrder']);
    Route::post('/payment/trial/verify', [PaymentController::class, 'verifyTrialPayment']);

    // Monthly subscription
    Route::post('/payment/subscription/create', [PaymentController::class, 'createMonthlySubscription']);
    Route::post('/payment/subscription/verify', [PaymentController::class, 'verifyMonthlySubscription']);

    // Chat (production & testing)
    Route::post('/chat/send', [ChatController::class, 'send']);
    Route::post('/chat/test', [ChatController::class, 'testChat']);

    // Voice calls
    Route::post('/voice/start', [VoiceCallController::class, 'start']);
    Route::post('/voice/process', [VoiceCallController::class, 'processAudio']);
    Route::post('/voice/end/{id}', [VoiceCallController::class, 'end']);
    
    // Reports
    Route::post('/reports/generate', [ReportController::class, 'generate']);
    Route::get('/reports', [ReportController::class, 'index']);
    Route::get('/reports/{id}/download', [ReportController::class, 'download']);

    // Memory testing endpoints (for development)
    Route::post('/memory/test-search', [MemoryTestController::class, 'testMemorySearch']);
    Route::post('/memory/store-test', [MemoryTestController::class, 'storeTestMemory']);
    Route::get('/memory/stats', [MemoryTestController::class, 'getUserMemoryStats']);

});

Route::middleware('auth:sanctum')->post(
    '/onboarding/submit',
    [OnboardingController::class, 'submit']
);