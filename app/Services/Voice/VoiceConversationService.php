<?php

namespace App\Services\Voice;

use App\Services\Voice\GoogleStreamingSTT;
use App\Services\Voice\GoogleStreamingTTS;
use App\Services\AI\AiService;
use App\Services\Safety\MedicalSafetyService;
use App\Services\Safety\SafeResponses;
use App\Services\Safety\FallbackResponses;
use App\Services\Safety\CallTerminationService;
use App\Services\Memory\MemoryReader;

class VoiceConversationService
{
    protected GoogleStreamingSTT $stt;
    protected GoogleStreamingTTS $tts;
    protected int $failureCount = 0;
    protected bool $emergencyHandled = false;

    public function __construct()
    {
        $this->stt = new GoogleStreamingSTT();
        $this->tts = new GoogleStreamingTTS();

        $this->stt->start();
    }

    /**
     * Handle mic audio chunk
     * Returns audio chunk for Rakhi or null
     */
    public function handleAudioChunk(string $audioChunk): ?string
    {
        try {
            $this->stt->writeAudio($audioChunk);
            $responses = $this->stt->readResponses();

            foreach ($responses as $res) {
                if ($res['is_final']) {
                    $spokenText = $res['text'];

                    // Handle empty or failed STT
                    if (empty(trim($spokenText))) {
                        $this->failureCount++;
                        return $this->tts->synthesize(
                            FallbackResponses::sttFail()
                        );
                    }

                    // Reset failure count on successful STT
                    $this->failureCount = 0;

                    // Check for call termination keywords
                    if ((new CallTerminationService())->shouldEndCall($spokenText)) {
                        return $this->tts->synthesize(
                            (new CallTerminationService())->endCallMessage()
                        );
                    }

                    // 🚨 Safety check FIRST
                    if ((new MedicalSafetyService())->isCritical($spokenText)) {
                        $this->emergencyHandled = true;
                        return $this->tts->synthesize(
                            SafeResponses::emergency()
                        );
                    }

                    // Recall relevant memories
                    $memories = (new MemoryReader())->recall($spokenText);
                    
                    $memoryContext = collect($memories)
                        ->pluck('metadata.summary')
                        ->implode("\n");

                    // Normal AI flow with fallback
                    try {
                        $replyText = (new AiService())->reply($spokenText, $memoryContext, 'voice');
                    } catch (\Exception $e) {
                        $replyText = FallbackResponses::aiFail();
                    }

                    // TTS with fallback (handled by caller)
                    return $this->tts->synthesize($replyText);
                }
            }
        } catch (\Exception $e) {
            // STT failure fallback
            $this->failureCount++;
            return $this->tts->synthesize(
                FallbackResponses::sttFail()
            );
        }

        return null;
    }

    public function shouldTerminateCall(): bool
    {
        return $this->emergencyHandled || $this->failureCount >= 3;
    }

    public function close()
    {
        $this->stt->close();
        $this->tts->close();
    }
}