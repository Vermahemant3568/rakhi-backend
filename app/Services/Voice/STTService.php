<?php

namespace App\Services\Voice;

use App\Services\ApiConfigService;
use Illuminate\Support\Facades\Log;

/**
 * STTService acts as the dynamic router for Speech-to-Text providers.
 * Keeping the class name STTService preserves the existing VoiceController binding.
 *
 * Admin panel: API Manager → STT Provider Settings → provider = groq | google
 */
class STTService
{
    public function __construct(
        private GoogleSTTService $google,
        private GroqSTTService   $groq,
    ) {}

    public function transcribe(string $audioBase64, string $mimeType, string $languageCode = 'en-IN'): string
    {
        $provider = $this->activeProvider();

        if ($provider === 'groq') {
            if (!$this->groq->isConfigured()) {
                Log::warning('STTService: Groq selected but not configured — falling back to Google');
                return $this->google->transcribe($audioBase64, $mimeType, $languageCode);
            }

            $text = $this->groq->transcribe($audioBase64, $mimeType, $languageCode);

            if ($text === '') {
                Log::warning('STTService: Groq transcription empty — falling back to Google');
                return $this->google->transcribe($audioBase64, $mimeType, $languageCode);
            }

            return $text;
        }

        // Default: Google
        return $this->google->transcribe($audioBase64, $mimeType, $languageCode);
    }

    public function activeProvider(): string
    {
        $config = ApiConfigService::all('stt_provider');
        return strtolower($config['provider'] ?? 'google');
    }
}
