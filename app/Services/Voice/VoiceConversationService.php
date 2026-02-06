<?php

namespace App\Services\Voice;

use App\Services\Voice\GoogleStreamingSTT;
use App\Services\Voice\GoogleStreamingTTS;
use App\Services\Coach\CoachService;
use App\Services\Safety\MedicalSafetyService;
use App\Services\Safety\SafeResponses;
use App\Services\Safety\FallbackResponses;
use App\Services\Safety\CallTerminationService;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Memory\MemoryManager;

class VoiceConversationService
{
    protected GoogleStreamingSTT $stt;
    protected GoogleStreamingTTS $tts;
    protected int $failureCount = 0;
    protected bool $emergencyHandled = false;
    protected $user;
    protected $voiceSession;
    protected $conversation;

    public function __construct(
        GoogleStreamingSTT $stt,
        GoogleStreamingTTS $tts,
        CoachService $coachService,
        MedicalSafetyService $safetyService,
        CallTerminationService $terminationService,
        MemoryManager $memoryManager
    ) {
        $this->stt = $stt;
        $this->tts = $tts;
        $this->coachService = $coachService;
        $this->safetyService = $safetyService;
        $this->terminationService = $terminationService;
        $this->memoryManager = $memoryManager;
    }

    public function initialize($user, $voiceSession): void
    {
        $this->user = $user;
        $this->voiceSession = $voiceSession;
        $this->conversation = Conversation::firstOrCreate(
            ['user_id' => $user->id, 'status' => 'active']
        );
        $this->stt->start();
    }

    public function handleAudioChunk(string $audioChunk): ?string
    {
        try {
            $this->stt->writeAudio($audioChunk);
            $responses = $this->stt->readResponses();

            foreach ($responses as $res) {
                if ($res['is_final']) {
                    $spokenText = trim($res['text']);

                    if (empty($spokenText)) {
                        $this->failureCount++;
                        return $this->tts->synthesize(FallbackResponses::sttFail());
                    }

                    $this->failureCount = 0;
                    $this->storeMessage('user', $spokenText, 'voice_input');

                    if ($this->terminationService->shouldEndCall($spokenText)) {
                        return $this->handleCallTermination();
                    }

                    if ($this->safetyService->isCritical($spokenText)) {
                        return $this->handleEmergency();
                    }

                    $replyText = $this->coachService->processMessage($this->user, $spokenText);
                    $this->storeMessage('rakhi', $replyText, 'voice_response');
                    $this->storeVoiceMemory($spokenText);

                    return $this->tts->synthesize($replyText);
                }
            }
        } catch (\Exception $e) {
            $this->failureCount++;
            return $this->tts->synthesize(FallbackResponses::sttFail());
        }

        return null;
    }

    public function shouldTerminateCall(): bool
    {
        return $this->emergencyHandled || $this->failureCount >= 3;
    }

    public function close(): void
    {
        $this->stt->close();
        $this->tts->close();
    }

    private function handleCallTermination(): string
    {
        $message = $this->terminationService->endCallMessage();
        $this->storeMessage('rakhi', $message, 'call_termination');
        return $this->tts->synthesize($message);
    }

    private function handleEmergency(): string
    {
        $this->emergencyHandled = true;
        $message = SafeResponses::emergency();
        $this->storeMessage('rakhi', $message, 'emergency_response', 'urgent');
        return $this->tts->synthesize($message);
    }

    private function storeMessage(string $sender, string $message, string $intent, string $emotion = 'neutral'): void
    {
        if (!$this->conversation) return;

        Message::create([
            'conversation_id' => $this->conversation->id,
            'voice_session_id' => $this->voiceSession->id ?? null,
            'sender' => $sender,
            'message' => $message,
            'intent' => $intent,
            'emotion' => $emotion
        ]);
    }

    private function storeVoiceMemory(string $content): void
    {
        if (!$this->user) return;

        $this->memoryManager->storeMemory(
            $this->user->id,
            'voice_conversation',
            $content,
            [
                'conversation_id' => $this->conversation->id ?? null,
                'voice_session_id' => $this->voiceSession->id ?? null,
                'intent' => 'voice_input',
                'emotion' => 'neutral'
            ]
        );
    }
}