<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\PaymentController;

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

});

Route::middleware('auth:sanctum')->post(
    '/onboarding/submit',
    [OnboardingController::class, 'submit']
);