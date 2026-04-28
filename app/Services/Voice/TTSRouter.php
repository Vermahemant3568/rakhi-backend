<?php

namespace App\Services\Voice;

use App\Services\ApiConfigService;
use Illuminate\Support\Facades\Log;

class TTSRouter
{
    public function __construct(
        private TTSService $google,
        private ElevenLabsTTSService $elevenlabs,
    ) {}

    /**
     * Synthesize text to audio URL using the configured provider.
     * Falls back to Google if ElevenLabs fails or is not configured.
     */
    public function synthesize(string $text, string $languageCode = 'en-IN'): string
    {
        $provider = $this->activeProvider();

        if ($provider === 'elevenlabs') {
            if (!$this->elevenlabs->isConfigured()) {
                Log::warning('TTSRouter: ElevenLabs selected but not configured — falling back to Google');
                return $this->google->synthesize($text, $languageCode);
            }

            $audioUrl = $this->elevenlabs->synthesize($text, $languageCode);

            if (empty($audioUrl)) {
                Log::warning('TTSRouter: ElevenLabs synthesis failed — falling back to Google');
                return $this->google->synthesize($text, $languageCode);
            }

            return $audioUrl;
        }

        // Default: Google
        return $this->google->synthesize($text, $languageCode);
    }

    public function activeProvider(): string
    {
        $config = ApiConfigService::all('voice_provider');
        return strtolower($config['provider'] ?? 'google');
    }
}
