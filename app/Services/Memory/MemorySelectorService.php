<?php

namespace App\Services\Memory;

class MemorySelectorService
{
    public function select(array $matches): string
    {
        $context = [];

        foreach ($matches as $match) {

            // Only strong matches
            if (($match['score'] ?? 0) < 0.75) {
                continue;
            }

            if (isset($match['metadata']['summary'])) {
                $context[] = $match['metadata']['summary'];
            }
        }

        return implode("\n", $context);
    }
}