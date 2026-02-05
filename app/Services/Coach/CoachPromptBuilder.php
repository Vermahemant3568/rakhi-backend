<?php

namespace App\Services\Coach;

use App\Services\Memory\MemoryReader;
use App\Services\Memory\MemorySelectorService;

class CoachPromptBuilder
{
    public function build($user, string $userMessage): string
    {
        $memoryMatches = (new MemoryReader())
            ->recall($userMessage, $user->id);

        $memoryContext = (new MemorySelectorService())
            ->select($memoryMatches);

        $userContext = (new UserContextBlock())
            ->build($user);

        return "
You are Rakhi, an empathetic Indian AI health coach.

You DO know the user's onboarding details.
You DO remember past conversations.
Never say you don't know the user.

$userContext

Relevant Memory:
$memoryContext

User says:
$userMessage
";
    }
}