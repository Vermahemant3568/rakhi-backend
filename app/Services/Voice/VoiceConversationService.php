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

    public function __construct($user = null, $voiceSession = null)
    {
        $this->stt = new GoogleStreamingSTT();
        $this->tts = new GoogleStreamingTTS();
        $this->user = $user;
        $this->voiceSession = $voiceSession;

        // Create or get conversation for voice session
        if ($user && $voiceSession) {
            $this->conversation = Conversation::firstOrCreate(
                ['user_id' => $user->id, 'status' => 'active']
            );
        }

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

                    // Store user voice message
                    if ($this->conversation) {
                        Message::create([
                            'conversation_id' => $this->conversation->id,
                            'voice_session_id' => $this->voiceSession->id ?? null,
                            'sender' => 'user',
                            'message' => $spokenText,
                            'intent' => 'voice_input',
                            'emotion' => 'neutral'
                        ]);
                    }

                    // Check for call termination keywords
                    if ((new CallTerminationService())->shouldEndCall($spokenText)) {
                        $terminationMessage = (new CallTerminationService())->endCallMessage();
                        
                        // Store termination message
                        if ($this->conversation) {
                            Message::create([
                                'conversation_id' => $this->conversation->id,
                                'voice_session_id' => $this->voiceSession->id ?? null,
                                'sender' => 'rakhi',
                                'message' => $terminationMessage,
                                'intent' => 'call_termination',
                                'emotion' => 'neutral'
                            ]);
                        }
                        
                        return $this->tts->synthesize($terminationMessage);
                    }

                    // 🚨 Safety check FIRST
                    if ((new MedicalSafetyService())->isCritical($spokenText)) {
                        $this->emergencyHandled = true;
                        $emergencyResponse = SafeResponses::emergency();
                        
                        // Store emergency response
                        if ($this->conversation) {
                            Message::create([
                                'conversation_id' => $this->conversation->id,
                                'voice_session_id' => $this->voiceSession->id ?? null,
                                'sender' => 'rakhi',
                                'message' => $emergencyResponse,
                                'intent' => 'emergency_response',
                                'emotion' => 'urgent'
                            ]);
                        }
                        
                        return $this->tts->synthesize($emergencyResponse);
                    }

                    // Use enhanced CoachService with reasoning engine
                    try {
                        if ($this->user) {
                            $coachService = new CoachService();
                            $replyText = $coachService->processMessage($this->user, $spokenText);
                        } else {
                            $replyText = FallbackResponses::aiFail();
                        }
                    } catch (\Exception $e) {
                        $replyText = FallbackResponses::aiFail();
                    }

                    // Store Rakhi's voice response
                    if ($this->conversation) {
                        Message::create([
                            'conversation_id' => $this->conversation->id,
                            'voice_session_id' => $this->voiceSession->id ?? null,
                            'sender' => 'rakhi',
                            'message' => $replyText,
                            'intent' => 'voice_response',
                            'emotion' => 'supportive'
                        ]);
                    }

                    // Store voice interaction as memory
                    if ($this->user) {
                        $memoryManager = new MemoryManager();
                        $memoryManager->storeMemory(
                            $this->user->id,
                            'voice_conversation',
                            $spokenText,
                            [
                                'conversation_id' => $this->conversation->id ?? null,
                                'voice_session_id' => $this->voiceSession->id ?? null,
                                'intent' => 'voice_input',
                                'emotion' => 'neutral'
                            ]
                        );
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