<?php

namespace App\Providers;

use App\Services\ApiConfigService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Boot: dynamically inject DB-managed API configs into Laravel config
     * so services like Pusher broadcasting pick them up at runtime.
     */
    public function boot(): void
    {
        // Only run after DB is available (skip during migrations/artisan setup)
        // Skip during artisan setup commands, but allow queue workers (they need Pusher config)
        $isSetupCommand = $this->app->runningInConsole()
            && !$this->app->runningUnitTests()
            && !$this->isQueueWorker();

        if ($isSetupCommand) {
            return;
        }

        try {
            $this->configurePusherFromDb();
        } catch (\Throwable $e) {
            // Non-fatal — DB may not be ready yet (fresh install)
        }
    }

    /**
     * Read Pusher config from DB and inject into Laravel broadcasting config.
     * This ensures the DB values override the .env placeholders at runtime.
     */
    private function isQueueWorker(): bool
    {
        $argv = $_SERVER['argv'] ?? [];
        foreach ($argv as $arg) {
            if (str_contains($arg, 'queue:work') || str_contains($arg, 'queue:listen')) {
                return true;
            }
        }
        return false;
    }

    private function configurePusherFromDb(): void
    {
        $appId     = ApiConfigService::get('pusher', 'app_id');
        $appKey    = ApiConfigService::get('pusher', 'app_key');
        $appSecret = ApiConfigService::get('pusher', 'app_secret');
        $cluster   = ApiConfigService::get('pusher', 'cluster', 'ap2');

        // Only override if real values are set (not placeholders)
        if ($appKey && $appKey !== 'your_pusher_app_key') {
            Config::set('broadcasting.connections.pusher.key', $appKey);
            Config::set('broadcasting.connections.pusher.secret', $appSecret);
            Config::set('broadcasting.connections.pusher.app_id', $appId);
            Config::set('broadcasting.connections.pusher.options.cluster', $cluster);

            // Also sync back to .env-style keys used by Pusher SDK internally
            Config::set('pusher.app_id', $appId);
            Config::set('pusher.app_key', $appKey);
            Config::set('pusher.app_secret', $appSecret);
            Config::set('pusher.cluster', $cluster);
        }
    }

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
