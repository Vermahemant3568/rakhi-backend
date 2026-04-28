<?php

namespace App\Services\Coach;

use App\Models\Coach;
use App\Models\User;
use App\Services\AI\ContextBuilder;
use App\Services\AI\LLMRouter;
use App\Services\AI\MemoryExtractorService;
use App\Services\AI\PromptEngine;
use App\Services\NLP\EntityExtractor;
use App\Services\NLP\IntentDetector;
use App\Services\NLP\SentimentAnalyzer;
use App\Services\Vector\UserMemoryService;
use Illuminate\Support\Facades\Log;

abstract class BaseCoach
{
    public function __construct(
        protected LLMRouter $llm,
        protected PromptEngine $promptEngine,
        protected ContextBuilder $contextBuilder,
        protected UserMemoryService $memory,
        protected MemoryExtractorService $memoryExtractor,
        protected IntentDetector $intent,
        protected SentimentAnalyzer $sentiment,
        protected EntityExtractor $entities,
    ) {}

    protected function getCoachModel(int $sessionId): Coach
    {
        $session = \App\Models\ChatSession::find($sessionId);
        return Coach::findOrFail($session->coach_id);
    }

    public function respond(User $user, string $message, int $sessionId, string $inputMode = 'chat'): string
    {
        $coach = $this->getCoachModel($sessionId);

        $user->loadMissing(['goals', 'language', 'coaches']);

        $session = \App\Models\ChatSession::find($sessionId);
        $parentChatSessionId = $session?->parent_chat_session_id;

        // Self-intro intercept — bypass LLM entirely
        if ($this->intent->isSelfIntro($message)) {
            $lang      = $session?->detected_language ?? 'en';
            $condition = $user->goals->first()?->name ?? '';
            $intro     = $this->promptEngine->buildSelfIntroResponse($lang, $user->first_name ?? '', $condition);
            // Voice: strip to one short sentence
            if ($inputMode === 'voice') {
                $intro = $this->toVoiceLength($intro);
            }
            return $intro;
        }

        try {
            $context = $this->contextBuilder->build(
                user: $user,
                currentMessage: $message,
                sessionId: $sessionId,
                coachNamespace: $coach->pinecone_namespace,
                parentChatSessionId: $parentChatSessionId,
                coach: $coach
            );
        } catch (\Exception $e) {
            Log::warning('ContextBuilder failed (non-fatal): ' . $e->getMessage());
            $context = [
                'msg_type'           => 'simple',
                'coach_profile'      => [],
                'emotional_context'  => [],
                'recent_history'     => $this->contextBuilder->buildRecentHistoryOnly($sessionId, $parentChatSessionId),
                'cross_session_msgs' => [],
                'memories'           => [],
                'knowledge'          => [],
                'structured_memory'  => [],
                'checkin'            => null,
                'meals_today'        => [],
                'existing_plans'     => [],
                'consultation_notes' => [],
            ];
        }

        $lastAiResponse = $this->contextBuilder->getLastAiResponse($sessionId, $parentChatSessionId);
        $detectedScript = $session->detected_script ?? 'latin';
        $sessionLang    = $session->detected_language ?? 'en';

        if ($inputMode === 'voice') {
            // Voice gets its own focused system prompt — shorter, spoken-style
            $systemPrompt = $this->promptEngine->buildVoiceSystemPrompt(
                user:            $user,
                coach:           $coach,
                userMessage:     $message,
                sessionLanguage: $sessionLang,
                sessionScript:   $detectedScript,
                lastAiResponse:  $lastAiResponse,
                coachProfile:    $context['coach_profile'] ?? [],
                emotionalContext:$context['emotional_context'] ?? []
            );
        } else {
            $systemPrompt = $this->promptEngine->buildSystemPrompt(
                user:                   $user,
                coach:                  $coach,
                userMessage:            $message,
                sessionLanguage:        $sessionLang,
                sessionScript:          $detectedScript,
                isReturning:            false,
                lastInteractionSummary: '',
                lastAiResponse:         $lastAiResponse,
                coachProfile:           $context['coach_profile'] ?? [],
                emotionalContext:       $context['emotional_context'] ?? []
            );
        }

        $userPrompt = $this->promptEngine->buildUserPrompt(
            userMessage:         $message,
            memories:            $context['memories'],
            knowledgeContext:    $context['knowledge'],
            checkin:             $context['checkin'],
            mealsToday:          $context['meals_today'],
            crossSessionHistory: $context['cross_session_msgs'] ?? [],
            consultationNotes:   $context['consultation_notes'] ?? [],
            existingPlans:       $context['existing_plans'] ?? [],
            structuredMemory:    $context['structured_memory'] ?? [],
            lastAiResponse:      $lastAiResponse,
            msgType:             $context['msg_type'] ?? 'simple',
            emotionalContext:    $context['emotional_context'] ?? [],
            inputMode:           $inputMode,
        );

        $fullPrompt = $systemPrompt . "\n\n" . $userPrompt;
        $response   = $this->llm->chat($fullPrompt, $context['recent_history']);
        $response   = $inputMode === 'voice'
            ? $this->optimizeVoiceResponse($response)
            : $this->optimizeChatResponse($response);

        // Store to Pinecone (non-blocking)
        try {
            $this->memory->store($user, $message, 'user', [
                'session_id' => $sessionId,
                'type'       => 'short_term',
            ]);
        } catch (\Exception $e) {
            Log::warning('Pinecone store skipped: ' . $e->getMessage());
        }

        // Extract structured memory facts (non-blocking)
        try {
            $this->memoryExtractor->extractAndStore($user, $message);
        } catch (\Exception $e) {
            Log::warning('MemoryExtractor skipped: ' . $e->getMessage());
        }

        return $response;
    }

    /**
     * Clean chat responses: strip filler openers, markdown, enforce 320-char limit.
     */
    private function optimizeChatResponse(string $response): string
    {
        $response = $this->stripFillerOpeners($response);
        $response = $this->stripMarkdown($response);
        $response = trim($response);

        if (strlen($response) <= 320) return $response;

        $paragraphs = explode("\n\n", $response);
        if (count($paragraphs) >= 2 && strlen($paragraphs[0]) >= 60) {
            return trim($paragraphs[0]) . "\n\n" . trim($paragraphs[1]);
        }

        $cut        = substr($response, 0, 320);
        $lastPeriod = max(
            strrpos($cut, '. '),
            strrpos($cut, '? '),
            strrpos($cut, '! '),
            strrpos($cut, "\n")
        );

        return ($lastPeriod !== false && $lastPeriod > 80)
            ? trim(substr($response, 0, $lastPeriod + 1))
            : trim($cut);
    }

    /**
     * Clean voice responses: strip filler openers, markdown, emojis.
     * Hard limit: 2 spoken sentences (~160 chars). No lists, no structure.
     */
    private function optimizeVoiceResponse(string $response): string
    {
        $response = $this->stripFillerOpeners($response);
        $response = $this->stripMarkdown($response);

        // Strip emojis — TTS reads them as "emoji" or skips awkwardly
        $response = preg_replace('/[\x{1F300}-\x{1FFFF}]/u', '', $response);
        $response = preg_replace('/[\x{2600}-\x{27BF}]/u', '', $response);

        // Collapse whitespace
        $response = preg_replace('/\s+/', ' ', $response);
        $response = trim($response);

        return $this->toVoiceLength($response);
    }

    /**
     * Trim to at most 2 spoken sentences, max 160 chars.
     */
    private function toVoiceLength(string $text): string
    {
        if (strlen($text) <= 160) return $text;

        // Try to cut at second sentence boundary
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (count($sentences) >= 2) {
            $two = $sentences[0] . ' ' . $sentences[1];
            if (strlen($two) <= 200) return trim($two);
            return trim($sentences[0]);
        }

        // Fall back to hard cut at sentence boundary within 160 chars
        $cut        = substr($text, 0, 160);
        $lastPeriod = max(
            strrpos($cut, '. '),
            strrpos($cut, '? '),
            strrpos($cut, '! ')
        );

        return ($lastPeriod !== false && $lastPeriod > 30)
            ? trim(substr($text, 0, $lastPeriod + 1))
            : trim($cut);
    }

    /**
     * Strip robotic AI filler openers from any response.
     */
    private function stripFillerOpeners(string $response): string
    {
        $phrases = [
            'I completely understand, ', 'I completely understand. ', 'I completely understand',
            'Thank you for sharing, ', 'Thank you for sharing. ', 'Thank you for sharing',
            'That makes sense, ', 'That makes sense. ', 'That makes sense',
            'I understand your concern, ', 'I understand your concern. ', 'I understand your concern',
            'I understand, ', 'I understand. ', 'I understand',
            'I see, ', 'I see. ',
            'Absolutely! ', 'Absolutely, ',
            'Certainly! ', 'Of course! ',
            'Great question! ', 'Great question, ',
            'Commendable! ',
            'As an AI, ', 'As an AI assistant, ',
            'As your health assistant, ', 'As a health assistant, ', 'As your AI coach, ',
            // Voice-specific over-acknowledgements to strip
            'Hmm, ', 'Hmm. ', 'Okay, ', 'Okay. ', 'Got it. ', 'Got it, ',
            'I see that ', 'I heard that ', 'You mentioned that ', 'You said that ',
            'aapne bola ki ', 'aapne kaha ki ', 'aapne mention kiya ki ',
            'Samajh gaya. ', 'Samajh gayi. ', 'Theek hai. ', 'Haan, ',
        ];

        foreach ($phrases as $phrase) {
            if (stripos($response, $phrase) === 0) {
                $response = substr($response, strlen($phrase));
                $response = ucfirst(trim($response));
                break; // only strip one opener
            }
        }

        return $response;
    }

    /**
     * Strip markdown formatting that breaks conversational tone.
     */
    private function stripMarkdown(string $response): string
    {
        $response = preg_replace('/\*\*(.+?)\*\*/s', '$1', $response);
        $response = preg_replace('/__(.+?)__/s', '$1', $response);
        $response = preg_replace('/^#{1,6}\s+/m', '', $response);
        $response = preg_replace('/^[\*\-\x{2022}]\s+/mu', '', $response);
        $response = preg_replace('/^\d+\.\s+/m', '', $response);
        return $response;
    }

    public function adviseMeal(User $user, array $analysis): string
    {
        $user->loadMissing(['goals']);

        $firstName = $user->first_name ?? '';
        $goals     = $user->goals->pluck('name')->join(', ') ?: 'general wellness';
        $nameRef   = $firstName ? ", {$firstName}" : '';

        $prompt = <<<PROMPT
You are Rakhi, a warm Indian health coach texting a close friend{$nameRef}.
User goals: {$goals}
They just ate: {$analysis['meal_name']}
Calories: {$analysis['estimated_calories']} | Protein: {$analysis['protein_g']}g | Carbs: {$analysis['carbs_g']}g | Fat: {$analysis['fat_g']}g | Health score: {$analysis['health_score']}/10

Rules:
- Reply in 1-2 short conversational lines. No bullet points, no lists.
- NEVER shame or judge the food choice — even if it's unhealthy.
- If the food is unhealthy, acknowledge it lightly ("happens sometimes") and give ONE gentle tip.
- If the food is healthy, celebrate it briefly and connect it to their goal.
- Sound like a real person texting, not a nutrition app.
- One practical tip tied to their goals, nothing more.
PROMPT;

        return $this->optimizeResponse($this->llm->chat($prompt));
    }
}
