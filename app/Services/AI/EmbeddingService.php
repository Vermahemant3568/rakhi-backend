<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;

class EmbeddingService
{
    public function embed(string $text): array
    {
        // Placeholder – replace with Gemini embeddings API
        // Must return vector of size 768

        return array_fill(0, 768, 0.01);
    }
}