<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // AI Services
        $this->app->singleton(\App\Services\AI\GeminiService::class);
        $this->app->singleton(\App\Services\AI\ChatGPTService::class);
        $this->app->singleton(\App\Services\AI\LLMRouter::class);
        $this->app->singleton(\App\Services\AI\EmbeddingService::class);
        $this->app->singleton(\App\Services\AI\PromptEngine::class);
        $this->app->singleton(\App\Services\AI\ContextBuilder::class);

        // Vector Services
        $this->app->singleton(\App\Services\Vector\PineconeService::class);
        $this->app->singleton(\App\Services\Vector\UserMemoryService::class);

        // NLP Services
        $this->app->singleton(\App\Services\NLP\IntentDetector::class);
        $this->app->singleton(\App\Services\NLP\SentimentAnalyzer::class);
        $this->app->singleton(\App\Services\NLP\MoodAnalyzer::class);
        $this->app->singleton(\App\Services\NLP\EntityExtractor::class);
        $this->app->singleton(\App\Services\NLP\LanguageDetector::class);

        // Safety Services
        $this->app->singleton(\App\Services\Safety\SafetyLayer::class);
        $this->app->singleton(\App\Services\Safety\MedicalBoundaryChecker::class);
        $this->app->singleton(\App\Services\Safety\EscalationHandler::class);

        // Coach Services
        $this->app->singleton(\App\Services\Coach\CoachRouter::class);
        $this->app->singleton(\App\Services\Coach\DiabetesCoach::class);
        $this->app->singleton(\App\Services\Coach\DietNutritionCoach::class);
        $this->app->singleton(\App\Services\Coach\FitnessCoach::class);
        $this->app->singleton(\App\Services\Coach\PCOSThyroidCoach::class);
        $this->app->singleton(\App\Services\Coach\MentalWellnessCoach::class);
        $this->app->singleton(\App\Services\Coach\SleepCoach::class);
        $this->app->singleton(\App\Services\Coach\WeightLossCoach::class);
        $this->app->singleton(\App\Services\Coach\PregnancyCoach::class);
        $this->app->singleton(\App\Services\Coach\PostpartumCoach::class);
        $this->app->singleton(\App\Services\Coach\EnergyCoach::class);
        $this->app->singleton(\App\Services\Coach\StressCoach::class);
        $this->app->singleton(\App\Services\Coach\HabitCoach::class);

        // Vision
        $this->app->singleton(\App\Services\Vision\MealVisionService::class);

        // Payment
        $this->app->singleton(\App\Services\Payment\RazorpayService::class);
    }
}
