<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Coach\CoachService;
use App\Services\Coach\CoachDecisionEngine;
use App\Services\Coach\LanguageService;
use App\Services\Coach\UserContextService;
use App\Services\Coach\GoalAwareResponseBuilder;
use App\Services\Coach\HabitFollowUpLogic;
use App\Services\Coach\TimeAwareCoachingService;
use App\Services\Coach\ResponseFormatterService;
use App\Services\Memory\OnboardingMemoryService;
use App\Services\Memory\MemoryManager;
use App\Services\Memory\MemorySelectorService;
use App\Services\NLP\IntentService;
use App\Services\NLP\EmotionService;
use App\Services\Nutrition\MealPatternAnalyzer;
use App\Services\Nutrition\NutritionInsightService;
use App\Services\Voice\VoiceConversationService;
use App\Services\Voice\GoogleStreamingSTT;
use App\Services\Voice\GoogleStreamingTTS;
use App\Services\Safety\MedicalSafetyService;
use App\Services\Safety\CallTerminationService;

class ServiceBindingProvider extends ServiceProvider
{
    public function register(): void
    {
        // Core Services
        $this->app->singleton(MemoryManager::class);
        $this->app->singleton(MemorySelectorService::class);
        $this->app->singleton(OnboardingMemoryService::class);
        
        // NLP Services
        $this->app->singleton(IntentService::class);
        $this->app->singleton(EmotionService::class);
        
        // Nutrition Services
        $this->app->singleton(MealPatternAnalyzer::class);
        $this->app->singleton(NutritionInsightService::class);
        
        // Coach Services
        $this->app->singleton(LanguageService::class);
        $this->app->singleton(UserContextService::class);
        $this->app->singleton(GoalAwareResponseBuilder::class);
        $this->app->singleton(HabitFollowUpLogic::class);
        $this->app->singleton(TimeAwareCoachingService::class);
        $this->app->singleton(ResponseFormatterService::class);
        $this->app->singleton(CoachDecisionEngine::class);
        $this->app->singleton(CoachService::class);
        
        // Safety Services
        $this->app->singleton(MedicalSafetyService::class);
        $this->app->singleton(CallTerminationService::class);
        
        // Voice Services
        $this->app->bind(GoogleStreamingSTT::class);
        $this->app->bind(GoogleStreamingTTS::class);
        
        $this->app->bind(VoiceConversationService::class, function ($app) {
            return new VoiceConversationService(
                $app->make(GoogleStreamingSTT::class),
                $app->make(GoogleStreamingTTS::class),
                $app->make(CoachService::class),
                $app->make(MedicalSafetyService::class),
                $app->make(CallTerminationService::class),
                $app->make(MemoryManager::class)
            );
        });
    }
}
