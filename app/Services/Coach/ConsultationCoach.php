<?php

namespace App\Services\Coach;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\User;
use App\Services\AI\WelcomeConsultationService;
use App\Services\NLP\LanguageDetector;
use App\Services\NLP\MoodAnalyzer;
use App\Services\NLP\SentimentAnalyzer;
use Illuminate\Support\Facades\Log;

class ConsultationCoach extends BaseCoach
{
    public function __construct(
        // BaseCoach dependencies
        \App\Services\AI\LLMRouter $llm,
        \App\Services\AI\PromptEngine $promptEngine,
        \App\Services\AI\ContextBuilder $contextBuilder,
        \App\Services\Vector\UserMemoryService $memory,
        \App\Services\AI\MemoryExtractorService $memoryExtractor,
        \App\Services\NLP\IntentDetector $intent,
        SentimentAnalyzer $sentiment,
        \App\Services\NLP\EntityExtractor $entities,

        // Consultation-specific
        private WelcomeConsultationService $welcomeService,
        private LanguageDetector $languageDetector,
        private MoodAnalyzer $moodAnalyzer,
    ) {
        parent::__construct($llm, $promptEngine, $contextBuilder, $memory, $memoryExtractor, $intent, $sentiment, $entities);
    }

    /**
     * Override respond() — consultation has its own flow.
     * Does NOT use BaseCoach's generic respond() pipeline.
     */
    public function respond(User $user, string $message, int $sessionId): string
    {
        $user->loadMissing(['goals', 'language', 'coaches']);

        $session = ChatSession::find($sessionId);

        // Detect language and persist to session
        $detectedLang = $this->languageDetector->detect($message);
        if ($detectedLang !== 'en') {
            $session->update(['detected_language' => $detectedLang]);
        }

        $sessionLang = $session->detected_language ?? 'en';

        try {
            $response = $this->welcomeService->getConsultationResponse(
                session:     $session,
                user:        $user,
                userMessage: $message,
            );
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('ConsultationCoach LLM timeout: ' . $e->getMessage());
            return $this->timeoutFallback($user->first_name ?? '', $sessionLang);
        } catch (\Exception $e) {
            Log::error('ConsultationCoach error: ' . $e->getMessage());
            return $this->timeoutFallback($user->first_name ?? '', $sessionLang);
        }

        return $response;
    }

    /**
     * Check if consultation is complete and plans should be generated.
     */
    public function isReadyForPlans(User $user, int $sessionId): bool
    {
        $user->loadMissing(['goals']);
        $goal = strtolower($user->goals->pluck('name')->first() ?? 'general');

        $history = ChatMessage::where('session_id', $sessionId)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($m) => ['role' => $m->role, 'message' => $m->message])
            ->toArray();

        $userMsgCount = collect($history)->where('role', 'user')->count();
        $missing      = $this->welcomeService->getMissingFields($history, $goal);

        return $userMsgCount >= 5 && empty($missing);
    }

    /**
     * Trigger plan generation after consultation completes.
     */
    public function completConsultation(User $user, int $sessionId): void
    {
        $this->welcomeService->generateAllPlans($user, $sessionId);
    }

    // ─────────────────────────────────────────────
    // TIMEOUT FALLBACK (MULTILINGUAL)
    // ─────────────────────────────────────────────

    private function timeoutFallback(string $firstName, string $lang): string
    {
        $name = $firstName ? ", {$firstName}" : '';

        return match(true) {
            str_starts_with($lang, 'hi'), $lang === 'hi-roman' =>
                "Hey{$name}, abhi thodi connectivity problem aa rahi hai 🌸 Ek second mein try karein — main yahan hoon!",

            $lang === 'ta' =>
                "Hey{$name}, konjam connection problem irukku 🌸 Oru nimisham kazhichu try pannunga!",

            default =>
                "Hey{$name}, I'm having a little trouble connecting right now 🌸 Give me a moment and try again — I'm here for you!",
        };
    }
}
