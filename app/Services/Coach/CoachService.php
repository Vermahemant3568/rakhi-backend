<?php

namespace App\Services\Coach;

use App\Services\Coach\LanguageService;
use App\Services\Coach\UserContextService;
use App\Services\Coach\FollowUpService;
use App\Services\Coach\ResponsePolicy;
use App\Services\AI\AiService;

class CoachService
{
    protected $languageService;
    protected $contextService;
    protected $followUpService;
    protected $aiService;

    public function __construct()
    {
        $this->languageService = new LanguageService();
        $this->contextService = new UserContextService();
        $this->followUpService = new FollowUpService();
        $this->aiService = new AiService();
    }

    public function processMessage($user, string $message): string
    {
        // 1. Language detection
        $detectedLanguage = $this->languageService->detect($message);
        $languageInstruction = $this->languageService->getResponseInstruction($detectedLanguage);

        // 2. User context (profile + goals + memory)
        $userContext = $this->contextService->build($user);

        // 3. Follow-up logic
        $followUpQuestion = $this->followUpService->nextQuestion($user);
        $followUpContext = $followUpQuestion ? "Consider asking: {$followUpQuestion}" : "";

        // 4. Response policy (what Rakhi can say)
        $responsePolicy = ResponsePolicy::systemRules();

        // 5. Build complete prompt
        $fullPrompt = $this->buildPrompt([
            'policy' => $responsePolicy,
            'context' => $userContext,
            'language' => $languageInstruction,
            'followUp' => $followUpContext,
            'message' => $message
        ]);

        // 6. Send to AI Service (dynamic - Gemini/OpenAI/Claude)
        return $this->aiService->reply($fullPrompt);
    }

    private function buildPrompt(array $components): string
    {
        return "
{$components['policy']}

{$components['context']}

{$components['language']}

{$components['followUp']}

User message: {$components['message']}

Respond as Rakhi:
";
    }
}