<?php

namespace App\Services\AI;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\User;
use App\Services\Coach\CoachRouter;
use App\Services\NLP\LanguageDetector;
use App\Services\Safety\MedicalBoundaryChecker;
use App\Services\Safety\SafetyLayer;
use Illuminate\Support\Facades\Log;

/**
 * UnifiedInputProcessor — single brain for voice and chat.
 *
 * Both modes normalize their input to plain text, then call process().
 * This ensures one memory, one context, one personality regardless of mode.
 */
class UnifiedInputProcessor
{
    public function __construct(
        private CoachRouter $coachRouter,
        private SafetyLayer $safety,
        private MedicalBoundaryChecker $boundary,
        private LanguageDetector $languageDetector,
        private WelcomeConsultationService $welcomeService,
        private MemoryExtractorService $memoryExtractor,
    ) {}

    /**
     * Process a normalized text input (from chat or voice STT).
     *
     * @param  User        $user
     * @param  string      $text          Normalized plain-text input
     * @param  ChatSession $session       The active session (voice or chat)
     * @param  string      $inputMode     'chat' | 'voice'
     * @return array{response: string, consultation_complete: bool, generate_plans: bool}
     */
    public function process(User $user, string $text, ChatSession $session, string $inputMode = 'chat'): array
    {
        // ── 1. Detect & sync language across the session chain ────────────────
        $detectedLang   = $this->languageDetector->detect($text);
        $detectedScript = $this->languageDetector->detectScript($text);

        $langChanged = $detectedLang !== 'en' && $detectedLang !== ($session->detected_language ?? 'en');
        $scriptChanged = $detectedScript !== ($session->detected_script ?? 'latin');

        if ($langChanged || $scriptChanged) {
            $updates = [];
            if ($langChanged)   $updates['detected_language'] = $detectedLang;
            if ($scriptChanged) $updates['detected_script']   = $detectedScript;

            $session->update($updates);

            // Sync language to parent chat session so chat UI stays consistent
            if ($session->parent_chat_session_id) {
                ChatSession::where('id', $session->parent_chat_session_id)
                    ->where('user_id', $user->id)
                    ->update($updates);
            }
        }

        $lang = $session->detected_language ?? 'en';

        // ── 2. Safety check — same rules for voice and chat ───────────────────
        $safetyResult = $this->safety->check($text);
        if (!$safetyResult['is_safe']) {
            return ['response' => $safetyResult['response'], 'consultation_complete' => false, 'generate_plans' => false];
        }

        if ($this->boundary->check($text)) {
            return ['response' => $this->boundary->getBoundaryResponse($text), 'consultation_complete' => false, 'generate_plans' => false];
        }

        // ── 3. Extract and store memory — always, for both modes ─────────────
        try {
            $this->memoryExtractor->extractAndStore($user, $text);
        } catch (\Throwable $e) {
            Log::warning('UnifiedProcessor memory extraction skipped: ' . $e->getMessage());
        }

        // ── 4. Route to consultation or regular coaching ──────────────────────
        if ($session->is_first_consultation && $user->isInConsultation()) {
            return $this->handleConsultation($user, $text, $session, $lang, $inputMode);
        }

        return $this->handleCoaching($user, $text, $session, $inputMode);
    }

    // ─── Consultation (shared for voice + chat) ───────────────────────────────

    private function handleConsultation(User $user, string $text, ChatSession $session, string $lang, string $inputMode = 'chat'): array
    {
        $user->refresh();

        // Already generating — just report status, don't re-trigger
        if ($user->isPlanGenerating()) {
            return [
                'response'              => $this->welcomeService->getPlanStatusMessage($user, $lang),
                'consultation_complete' => true,
                'generate_plans'        => false,
            ];
        }

        // Plans already completed — report status
        if ($user->isPlanCompleted()) {
            return [
                'response'              => $this->welcomeService->getPlanStatusMessage($user, $lang),
                'consultation_complete' => true,
                'generate_plans'        => false,
            ];
        }

        // Previous attempt failed — auto-retry once
        if ($user->isPlanFailed()) {
            Log::info('Plan auto-retry triggered', ['user_id' => $user->id]);
            $user->setPlanState('generating');
            $user->update(['consultation_state' => 'generating_plans']);
            try {
                $this->welcomeService->generateAllPlans($user, $session->id, $lang);
            } catch (\Throwable $e) {
                Log::error('Plan auto-retry failed: ' . $e->getMessage());
                $user->setPlanState('failed');
            }
            return [
                'response'              => $this->welcomeService->getPlanStatusMessage($user->refresh(), $lang),
                'consultation_complete' => false,
                'generate_plans'        => false,
            ];
        }

        try {
            $response = $this->welcomeService->getConsultationResponse(
                session:     $session,
                user:        $user,
                userMessage: $text,
                inputMode:   $inputMode,
            );
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('UnifiedProcessor consultation LLM timeout: ' . $e->getMessage());
            try {
                $response = $this->welcomeService->getConsultationResponse($session, $user, $text, $inputMode);
            } catch (\Exception $retryEx) {
                Log::error('Consultation retry failed: ' . $retryEx->getMessage());
                return ['response' => $this->timeoutFallback($user->first_name ?? '', $inputMode), 'consultation_complete' => false, 'generate_plans' => false];
            }
        } catch (\Exception $e) {
            Log::error('UnifiedProcessor consultation error: ' . $e->getMessage());
            return ['response' => $this->timeoutFallback($user->first_name ?? '', $inputMode), 'consultation_complete' => false, 'generate_plans' => false];
        }

        // ── Safety valve: force completion if LLM forgot [GENERATE_PLANS] ────
        $unified = $this->getUnifiedUserTurns($session);
        $user->loadMissing(['goals']);
        $goal    = strtolower($user->goals->pluck('name')->first() ?? 'general');
        $missing = $this->welcomeService->getMissingFields(
            $this->getUnifiedHistory($session),
            $goal
        );

        if (
            !str_contains($response, '[GENERATE_PLANS]') &&
            $unified >= WelcomeConsultationService::MIN_USER_TURNS &&
            empty($missing)
        ) {
            $response = $this->welcomeService->getCompletionMessage($user->first_name ?? '', $lang) . "\n[GENERATE_PLANS]";
        }

        if (str_contains($response, '[GENERATE_PLANS]')) {
            if (!empty($missing)) {
                Log::info('[GENERATE_PLANS] suppressed — missing fields', [
                    'user_id' => $user->id,
                    'missing' => $missing,
                ]);
                return ['response' => trim(str_replace('[GENERATE_PLANS]', '', $response)), 'consultation_complete' => false, 'generate_plans' => false];
            }

            $user->refresh();
            if ($user->isPlanGenerating() || $user->isPlanCompleted()) {
                Log::info('[GENERATE_PLANS] suppressed — already in state: ' . $user->plan_generation_state, ['user_id' => $user->id]);
                $clean = trim(str_replace('[GENERATE_PLANS]', '', $response));
                return ['response' => $clean ?: $this->welcomeService->getPlanStatusMessage($user, $lang), 'consultation_complete' => true, 'generate_plans' => false];
            }

            Log::info('[GENERATE_PLANS] trigger confirmed — dispatching plan generation', [
                'user_id'    => $user->id,
                'session_id' => $session->id,
                'lang'       => $lang,
                'input_mode' => $inputMode,
            ]);

            $completion = $this->welcomeService->getCompletionMessage($user->first_name ?? '', $lang);
            $user->update(['first_consultation_complete' => true]);
            $session->update(['is_first_consultation' => false]);

            // Sync completion to parent chat session
            if ($session->parent_chat_session_id) {
                ChatSession::where('id', $session->parent_chat_session_id)
                    ->where('user_id', $user->id)
                    ->update(['is_first_consultation' => false]);
            }

            try {
                $this->welcomeService->generateAllPlans($user, $session->id, $lang);
                Log::info('generateAllPlans dispatched successfully', ['user_id' => $user->id]);
            } catch (\Throwable $e) {
                Log::error('Plan generation dispatch failed: ' . $e->getMessage(), ['user_id' => $user->id]);
                $user->setPlanState('failed');
            }

            return ['response' => $completion, 'consultation_complete' => true, 'generate_plans' => true];
        }

        return ['response' => $response, 'consultation_complete' => false, 'generate_plans' => false];
    }

    // ─── Regular coaching (shared for voice + chat) ───────────────────────────

    private function handleCoaching(User $user, string $text, ChatSession $session, string $inputMode = 'chat'): array
    {
        $coach        = $this->coachRouter->resolveCoach($user, $text);
        $coachService = $this->coachRouter->resolveCoachService($coach->slug);

        try {
            $response = $coachService->respond($user, $text, $session->id, $inputMode);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('UnifiedProcessor coaching LLM timeout, retrying: ' . $e->getMessage());
            try {
                $response = $coachService->respond($user, $text, $session->id, $inputMode);
            } catch (\Exception $retryEx) {
                Log::error('Coaching retry failed: ' . $retryEx->getMessage());
                $response = $this->timeoutFallback($user->first_name ?? '', $inputMode);
            }
        } catch (\Exception $e) {
            Log::error('UnifiedProcessor coaching error: ' . $e->getMessage());
            $response = $this->timeoutFallback($user->first_name ?? '', $inputMode);
        }

        return ['response' => $response, 'consultation_complete' => false, 'generate_plans' => false];
    }

    // ─── Unified history helpers ──────────────────────────────────────────────

    /**
     * Returns merged history from both the current session and its parent chat session.
     * This is the single source of truth for consultation completeness checks.
     */
    public function getUnifiedHistory(ChatSession $session): array
    {
        $sessionIds = [$session->id];
        if ($session->parent_chat_session_id) {
            $sessionIds[] = $session->parent_chat_session_id;
        }

        // Also include any sibling voice sessions linked to the same parent
        if ($session->parent_chat_session_id) {
            $siblingIds = ChatSession::where('parent_chat_session_id', $session->parent_chat_session_id)
                ->where('user_id', $session->user_id)
                ->pluck('id')
                ->toArray();
            $sessionIds = array_unique(array_merge($sessionIds, $siblingIds));
        }

        return ChatMessage::whereIn('session_id', $sessionIds)
            ->where('user_id', $session->user_id)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($m) => ['role' => $m->role, 'message' => $m->message])
            ->toArray();
    }

    /**
     * Count total user turns across the entire session chain (voice + chat).
     */
    public function getUnifiedUserTurns(ChatSession $session): int
    {
        return collect($this->getUnifiedHistory($session))
            ->where('role', 'user')
            ->count();
    }

    private function timeoutFallback(string $firstName = '', string $inputMode = 'chat'): string
    {
        $name = $firstName ? ", {$firstName}" : '';
        if ($inputMode === 'voice') {
            return "Sorry{$name}, having a bit of trouble right now. Try again in a second.";
        }
        return "Hey{$name}, I'm having a little trouble connecting right now. Give me a moment and try again — I'm here for you!";
    }
}
