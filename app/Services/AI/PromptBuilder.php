<?php

namespace App\Services\AI;

use App\Models\RakhiRule;
use App\Models\PromptTemplate;

class PromptBuilder
{
    public function build(string $userMessage, string $context): string
    {
        $personality = RakhiRule::where('key','personality')->value('value');
        $tone = RakhiRule::where('key','tone')->value('value');

        $basePrompt = PromptTemplate::where('type','chat')
            ->where('is_active', true)
            ->value('template');

        return "
You are Rakhi.
Personality: $personality
Tone: $tone

Context:
$context

User says:
$userMessage

$basePrompt
";
    }
}