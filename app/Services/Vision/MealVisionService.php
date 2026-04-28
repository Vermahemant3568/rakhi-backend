<?php

namespace App\Services\Vision;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MealVisionService
{
    private string $apiKey;
    private string $endpoint;

    public function __construct()
    {
        $this->apiKey   = config('services.gemini.api_key') ?? '';
        $this->endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';
    }

    public function analyze(string $imageUrl): array
    {
        $imageData = $this->fetchImageAsBase64($imageUrl);

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'inline_data' => [
                                'mime_type' => 'image/jpeg',
                                'data'      => $imageData,
                            ],
                        ],
                        [
                            'text' => $this->buildPrompt(),
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature'     => 0.2,
                'responseMimeType' => 'application/json',
            ],
        ];

        $response = Http::withQueryParameters(['key' => $this->apiKey])
            ->timeout(30)
            ->post($this->endpoint, $payload);

        if ($response->failed()) {
            Log::error('MealVisionService: Gemini API error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException('Gemini Vision API request failed.');
        }

        $text = $response->json('candidates.0.content.parts.0.text');

        return json_decode($text, true) ?? [];
    }

    private function fetchImageAsBase64(string $imageUrl): string
    {
        $response = Http::timeout(15)->get($imageUrl);

        if ($response->failed()) {
            throw new \RuntimeException('Failed to fetch image from URL.');
        }

        return base64_encode($response->body());
    }

    private function buildPrompt(): string
    {
        return <<<PROMPT
Analyze this meal image and return a JSON object with the following fields:
{
  "meal_name": "string",
  "meal_time_suggestion": "breakfast|lunch|dinner|snack",
  "calories": number,
  "protein": number,
  "carbs": number,
  "fat": number,
  "fiber": number,
  "ingredients": ["string"],
  "health_score": number (1-10),
  "summary": "brief description of the meal"
}
Return only valid JSON. All nutritional values should be per serving in grams except calories (kcal).
PROMPT;
    }
}
