<?php

namespace App\Services\Voice;

use App\Services\ApiConfigService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqSTTService
{
    private const API_URL       = 'https://api.groq.com/openai/v1/audio/transcriptions';
    private const DEFAULT_MODEL = 'whisper-large-v3-turbo';
    private const MAX_BYTES     = 25 * 1024 * 1024; // 25 MB — Groq limit

    private function config(): array
    {
        return ApiConfigService::all('groq_stt');
    }

    public function transcribe(string $audioBase64, string $mimeType, string $languageCode = 'en-IN'): string
    {
        $config = $this->config();
        $apiKey = $config['api_key'] ?? '';

        if (empty($apiKey)) {
            Log::warning('GroqSTT: api_key not configured');
            return '';
        }

        $audioBytes = base64_decode($audioBase64, true);

        if ($audioBytes === false || strlen($audioBytes) < 1000) {
            return '';
        }

        if (strlen($audioBytes) > self::MAX_BYTES) {
            Log::warning('GroqSTT: audio exceeds 25 MB limit');
            return '';
        }

        $model    = $config['model_name'] ?? self::DEFAULT_MODEL;
        $ext      = $this->extensionFromMime($mimeType);
        $filename = 'audio.' . $ext;
        $lang     = substr($languageCode, 0, 2); // e.g. "en-IN" → "en"

        try {
            $response = Http::timeout(20)
                ->withHeaders(['Authorization' => 'Bearer ' . $apiKey])
                ->attach('file', $audioBytes, $filename, ['Content-Type' => $mimeType])
                ->post(self::API_URL, [
                    'model'           => $model,
                    'language'        => $lang,
                    'response_format' => 'json',
                ]);

            if ($response->failed()) {
                Log::error('GroqSTT failed: HTTP ' . $response->status() . ' — ' . $response->body());
                return '';
            }

            $data = $response->json();

            // Log request ID for debugging — never exposed to user
            if (!empty($data['x_groq']['id'])) {
                Log::debug('GroqSTT request_id: ' . $data['x_groq']['id']);
            }

            $text = trim($data['text'] ?? '');

            return $this->isValidTranscript($text) ? $text : '';

        } catch (\Exception $e) {
            Log::error('GroqSTT Exception: ' . $e->getMessage());
            return '';
        }
    }

    public function isConfigured(): bool
    {
        return !empty($this->config()['api_key'] ?? '');
    }

    private function isValidTranscript(string $text): bool
    {
        if (strlen($text) < 2) return false;
        if (preg_match('/^[\s\.\,\!\?\-]+$/', $text)) return false;
        return true;
    }

    private function extensionFromMime(string $mimeType): string
    {
        return match (true) {
            str_contains($mimeType, 'webm') => 'webm',
            str_contains($mimeType, 'ogg')  => 'ogg',
            str_contains($mimeType, 'wav')  => 'wav',
            str_contains($mimeType, 'mp3'), str_contains($mimeType, 'mpeg') => 'mp3',
            str_contains($mimeType, 'flac') => 'flac',
            // m4a / mp4 / aac — all AAC-LC from mobile; Groq accepts as m4a
            str_contains($mimeType, 'm4a'),
            str_contains($mimeType, 'mp4'),
            str_contains($mimeType, 'aac')  => 'm4a',
            default                         => 'webm',
        };
    }
}
