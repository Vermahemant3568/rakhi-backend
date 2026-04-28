<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\AI\UnifiedInputProcessor;
use App\Services\AI\WelcomeConsultationService;
use App\Services\Voice\CallSessionManager;
use App\Services\Voice\STTService;
use App\Services\Voice\TTSRouter;
use App\Services\Voice\VoiceCallBehavior;
use App\Events\VoiceSessionStarted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VoiceController extends Controller
{
    // After this many consecutive STT failures, suggest switching to chat
    private const STT_FAIL_THRESHOLD = 3;

    public function __construct(
        private STTService $stt,
        private TTSRouter $tts,
        private CallSessionManager $callManager,
        private WelcomeConsultationService $welcomeService,
        private UnifiedInputProcessor $processor,
        private VoiceCallBehavior $callBehavior,
    ) {}

    // ─── Start session ────────────────────────────────────────────────────────

    public function startSession(Request $request)
    {
        $user = auth()->user();

        $this->callManager->closeOldSessions($user);

        $coachId             = $user->primaryCoach()?->id ?? 1;
        $isFirstConsultation = !($user->first_consultation_complete ?? false);

        $parentChatSession = ChatSession::where('user_id', $user->id)
            ->where('session_type', 'chat')
            ->where('status', 'active')
            ->latest()
            ->first();

        $session = ChatSession::create([
            'user_id'                => $user->id,
            'coach_id'               => $coachId,
            'session_type'           => 'voice',
            'is_first_consultation'  => $isFirstConsultation,
            'status'                 => 'active',
            'parent_chat_session_id' => $parentChatSession?->id,
            'detected_language'      => $parentChatSession?->detected_language,
            'detected_script'        => $parentChatSession?->detected_script,
            'unified_session_id'     => $parentChatSession?->unified_session_id ?? $parentChatSession?->id,
        ]);

        if ($session->user_id) {
            broadcast(new VoiceSessionStarted($session));
        }

        $user->load('goals');
        $lang    = $session->detected_language ?? 'en';
        $ttsLang = $this->toTtsCode($lang);

        // Build the main greeting
        if ($isFirstConsultation) {
            $mainGreeting = $this->welcomeService->getVoiceWelcomeMessage($user, $lang);
        } else {
            $lastMsg      = $this->getLastRakhiMessageAcrossChain($session, $parentChatSession);
            $mainGreeting = $this->buildVoiceGreetingWithContext($user, $lastMsg, $lang);
        }

        // ── Append audibility check — every call starts with this ─────────────
        $audibilityCheck = $this->callBehavior->audibilityCheck($lang);
        $greetingText    = $mainGreeting . ' ' . $audibilityCheck;

        $audioBase64 = $this->synthesizeSafe($greetingText, $ttsLang);

        $this->saveVoiceMessage($session, $user->id, 'rakhi', $greetingText);

        if ($parentChatSession) {
            ChatMessage::create([
                'session_id'   => $parentChatSession->id,
                'user_id'      => $user->id,
                'role'         => 'rakhi',
                'message'      => '📞 Voice call started',
                'message_type' => 'voice_summary',
            ]);
        }

        return response()->json([
            'success'                => true,
            'session'                => $session,
            'greeting'               => $greetingText,
            'audio_base64'           => $audioBase64,
            'audio_mime'             => 'audio/mp3',
            'is_first_consultation'  => $isFirstConsultation,
            'parent_chat_session_id' => $parentChatSession?->id,
        ]);
    }

    // ─── Receive voice turn ───────────────────────────────────────────────────

    public function sendVoice(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:chat_sessions,id',
            'audio'      => 'required|string',
            'mime_type'  => 'required|string',
            'is_silence' => 'nullable|boolean',   // frontend detected silence (no speech energy)
        ]);

        $user    = auth()->user();
        $session = ChatSession::where('id', $request->session_id)
            ->where('user_id', $user->id)
            ->where('session_type', 'voice')
            ->where('status', 'active')
            ->first();

        if (!$session) {
            return response()->json([
                'success'    => false,
                'message'    => 'Session not found or already ended.',
                'call_ended' => true,
            ], 404);
        }

        $lang    = $session->detected_language ?? 'en';
        $ttsLang = $this->toTtsCode($lang);

        // ── Handle frontend-reported silence (no speech energy detected) ──────
        if ($request->boolean('is_silence')) {
            $silenceText  = $this->callBehavior->silencePrompt($lang);
            $audioBase64  = $this->synthesizeSafe($silenceText, $ttsLang);

            return response()->json([
                'success'      => true,
                'silent'       => true,
                'response'     => $silenceText,
                'audio_base64' => $audioBase64,
                'audio_mime'   => 'audio/mp3',
            ]);
        }

        // ── STT: voice → text ─────────────────────────────────────────────────
        try {
            $transcribedText = $this->stt->transcribe(
                audioBase64:  $request->audio,
                mimeType:     $request->mime_type,
                languageCode: $this->toGoogleCode($lang)
            );
        } catch (\Exception $e) {
            Log::error('STT failed: ' . $e->getMessage());
            return $this->handleSttFailure($session, $user->id, $lang, $ttsLang);
        }

        // ── Empty transcript = STT returned nothing (silence / noise) ─────────
        if (empty($transcribedText)) {
            return $this->handleSttFailure($session, $user->id, $lang, $ttsLang);
        }

        // ── STT succeeded — reset consecutive failure counter ─────────────────
        if ($session->stt_fail_count > 0) {
            $session->update(['stt_fail_count' => 0]);
        }

        // ── Detect end-of-call intent ─────────────────────────────────────────
        if ($this->isEndOfCallIntent($transcribedText)) {
            return $this->handleCallEnd($session, $user, $transcribedText);
        }

        // ── Save user turn ────────────────────────────────────────────────────
        $this->saveVoiceMessage($session, $user->id, 'user', $transcribedText);

        if ($session->parent_chat_session_id) {
            ChatMessage::create([
                'session_id'   => $session->parent_chat_session_id,
                'user_id'      => $user->id,
                'role'         => 'user',
                'message'      => $transcribedText,
                'message_type' => 'voice',
            ]);
        }

        // ── Unified pipeline — same brain as chat ─────────────────────────────
        $result = $this->processor->process($user, $transcribedText, $session, 'voice');

        $session->refresh();
        $ttsLang   = $this->toTtsCode($session->detected_language ?? 'en');
        $rakhiText   = $result['response'];
        $rakhiText   = $this->stripEmojisForVoice($rakhiText);
        $audioBase64 = $this->synthesizeSafe($rakhiText, $ttsLang);

        $this->saveVoiceMessage($session, $user->id, 'rakhi', $rakhiText);

        if ($session->parent_chat_session_id) {
            ChatMessage::create([
                'session_id'   => $session->parent_chat_session_id,
                'user_id'      => $user->id,
                'role'         => 'rakhi',
                'message'      => $rakhiText,
                'message_type' => 'voice',
            ]);
        }

        $response = [
            'success'      => true,
            'transcript'   => $transcribedText,
            'response'     => $rakhiText,
            'audio_base64' => $audioBase64,
            'audio_mime'   => 'audio/mp3',
        ];

        if ($result['consultation_complete']) {
            $response['consultation_complete'] = true;
            if ($result['generate_plans']) {
                $response['call_ended'] = true;
            }
        }

        return response()->json($response);
    }

    // ─── STT diagnostic ───────────────────────────────────────────────────────
    // POST /api/voice/test-stt  { audio: base64, mime_type: string }
    // Returns the raw transcript + which provider was used.
    // Use this to confirm STT is working before debugging Flutter.

    public function testStt(Request $request)
    {
        $request->validate([
            'audio'     => 'required|string',
            'mime_type' => 'required|string',
        ]);

        $provider = $this->stt->activeProvider();

        try {
            $transcript = $this->stt->transcribe(
                audioBase64:  $request->audio,
                mimeType:     $request->mime_type,
                languageCode: $request->input('language_code', 'en-IN')
            );
        } catch (\Exception $e) {
            return response()->json([
                'success'    => false,
                'provider'   => $provider,
                'error'      => $e->getMessage(),
                'transcript' => null,
            ], 422);
        }

        return response()->json([
            'success'    => true,
            'provider'   => $provider,
            'transcript' => $transcript,
            'empty'      => empty($transcript),
        ]);
    }

    // ─── End session ──────────────────────────────────────────────────────────

    public function endSession(Request $request)
    {
        $request->validate(['session_id' => 'required|exists:chat_sessions,id']);

        $user    = auth()->user();
        $session = ChatSession::where('id', $request->session_id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($session->status !== 'closed') {
            $session->update(['status' => 'closed', 'ended_at' => now()]);
        }

        $this->addVoiceSummary($session);

        if ($session->is_first_consultation) {
            $this->postChatFallbackAfterVoice($session, $user, $session->detected_language ?? 'en');
        }

        return response()->json([
            'success'                => true,
            'message'                => 'Voice session ended.',
            'parent_chat_session_id' => $session->parent_chat_session_id,
        ]);
    }

    // ─── STT failure handler ──────────────────────────────────────────────────

    private function handleSttFailure(ChatSession $session, int $userId, string $lang, string $ttsLang): \Illuminate\Http\JsonResponse
    {
        $session->increment('stt_fail_count');
        $session->refresh();

        $failCount = $session->stt_fail_count;

        // After threshold consecutive failures → suggest chat, end call
        if ($failCount >= self::STT_FAIL_THRESHOLD) {
            $fallbackText = $this->callBehavior->networkFallbackSuggestion($lang);
            $audioBase64  = $this->synthesizeSafe($fallbackText, $ttsLang);

            $this->saveVoiceMessage($session, $userId, 'rakhi', $fallbackText);
            $session->update(['status' => 'closed', 'ended_at' => now(), 'voice_fallback_active' => true]);
            $this->addVoiceSummary($session);

            if ($session->parent_chat_session_id) {
                ChatMessage::create([
                    'session_id'   => $session->parent_chat_session_id,
                    'user_id'      => $userId,
                    'role'         => 'rakhi',
                    'message'      => $fallbackText,
                    'message_type' => 'text',
                ]);
                ChatSession::where('id', $session->parent_chat_session_id)
                    ->update(['voice_fallback_active' => true, 'call_invite_pending' => false]);
            }

            return response()->json([
                'success'                => true,
                'silent'                 => true,
                'response'               => $fallbackText,
                'audio_base64'           => $audioBase64,
                'audio_mime'             => 'audio/mp3',
                'call_ended'             => true,
                'suggest_chat'           => true,
                'parent_chat_session_id' => $session->parent_chat_session_id,
            ]);
        }

        $notHeardText = $failCount === 1
            ? $this->callBehavior->notHeardOnce($lang)
            : $this->callBehavior->notHeardTwice($lang);

        $audioBase64 = $this->synthesizeSafe($notHeardText, $ttsLang);

        return response()->json([
            'success'      => true,
            'silent'       => true,
            'response'     => $notHeardText,
            'audio_base64' => $audioBase64,
            'audio_mime'   => 'audio/mp3',
        ]);
    }

    // ─── Call end ─────────────────────────────────────────────────────────────

    private function handleCallEnd(ChatSession $session, $user, string $transcribedText): \Illuminate\Http\JsonResponse
    {
        $lang    = $session->detected_language ?? 'en';
        $ttsLang = $this->toTtsCode($lang);

        // ── Farewell includes clarity confirmation ────────────────────────────
        $farewell    = $this->callBehavior->farewell($lang);
        $audioBase64 = $this->synthesizeSafe($farewell, $ttsLang);

        $this->saveVoiceMessage($session, $user->id, 'rakhi', $farewell);

        if ($session->parent_chat_session_id) {
            ChatMessage::create([
                'session_id'   => $session->parent_chat_session_id,
                'user_id'      => $user->id,
                'role'         => 'rakhi',
                'message'      => $farewell,
                'message_type' => 'voice',
            ]);
        }

        $session->update(['status' => 'closed', 'ended_at' => now()]);
        $this->addVoiceSummary($session);

        if ($session->is_first_consultation) {
            $this->postChatFallbackAfterVoice($session, $user, $lang);
        }

        return response()->json([
            'success'                => true,
            'transcript'             => $transcribedText,
            'response'               => $farewell,
            'audio_base64'           => $audioBase64,
            'audio_mime'             => 'audio/mp3',
            'call_ended'             => true,
            'parent_chat_session_id' => $session->parent_chat_session_id,
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function addVoiceSummary(ChatSession $session): void
    {
        $parentId = $session->parent_chat_session_id;
        if (!$parentId) return;

        $alreadyExists = ChatMessage::where('session_id', $parentId)
            ->where('message_type', 'voice_summary')
            ->where('user_id', $session->user_id)
            ->where('message', 'LIKE', '📞 Voice call —%')
            ->exists();

        if ($alreadyExists) return;

        $duration      = $this->callManager->getDurationSeconds($session);
        $mins          = intdiv($duration, 60);
        $secs          = $duration % 60;
        $durationLabel = $mins > 0 ? "{$mins}m {$secs}s" : "{$secs}s";

        ChatMessage::create([
            'session_id'   => $parentId,
            'user_id'      => $session->user_id,
            'role'         => 'rakhi',
            'message'      => "📞 Voice call — {$durationLabel}",
            'message_type' => 'voice_summary',
        ]);
    }

    private function postChatFallbackAfterVoice(ChatSession $voiceSession, $user, string $lang): void
    {
        $parentId = $voiceSession->parent_chat_session_id;
        if (!$parentId) return;

        $user->refresh();
        // Do not post fallback if plan generation is already in progress or done
        if ($user->isPlanGenerating() || $user->isPlanCompleted() || $user->consultation_state === 'generating_plans') {
            Log::info('postChatFallbackAfterVoice skipped — plan already generating/completed', [
                'user_id'           => $user->id,
                'voice_session_id'  => $voiceSession->id,
                'plan_state'        => $user->plan_generation_state,
                'consultation_state'=> $user->consultation_state,
            ]);
            return;
        }

        $alreadySent = ChatMessage::where('session_id', $parentId)
            ->where('role', 'rakhi')
            ->where('message_type', 'text')
            ->where('user_id', $user->id)
            ->exists();

        if ($alreadySent) return;

        ChatSession::where('id', $parentId)->where('user_id', $user->id)->update([
            'voice_fallback_active' => true,
            'call_invite_pending'   => false,
        ]);

        $user->loadMissing(['goals']);
        $fallbackMsg = $this->welcomeService->getVoiceFallbackMessage($user, $lang);

        ChatMessage::create([
            'session_id'   => $parentId,
            'user_id'      => $user->id,
            'role'         => 'rakhi',
            'message'      => $fallbackMsg,
            'message_type' => 'text',
        ]);

        Log::info('Voice fallback posted to parent chat session', [
            'voice_session_id'  => $voiceSession->id,
            'parent_session_id' => $parentId,
            'user_id'           => $user->id,
        ]);
    }

    private function saveVoiceMessage(ChatSession $session, int $userId, string $role, string $message): void
    {
        ChatMessage::create([
            'session_id'   => $session->id,
            'user_id'      => $userId,
            'role'         => $role,
            'message'      => $message,
            'message_type' => 'voice',
        ]);
    }

    private function getLastRakhiMessageAcrossChain(ChatSession $voiceSession, ?ChatSession $parentChatSession): ?string
    {
        $last = ChatMessage::where('session_id', $voiceSession->id)
            ->where('role', 'rakhi')
            ->orderBy('created_at', 'desc')
            ->value('message');

        if (!$last && $parentChatSession) {
            $last = ChatMessage::where('session_id', $parentChatSession->id)
                ->where('role', 'rakhi')
                ->orderBy('created_at', 'desc')
                ->value('message');
        }

        return $last;
    }

    private function buildVoiceGreetingWithContext($user, ?string $lastMsg, string $lang): string
    {
        $name    = $user->first_name ?? 'there';
        $isHindi = str_starts_with($lang, 'hi');

        if ($lastMsg) {
            if ($isHindi) {
                $variants = [
                    "Namaste {$name}! Main Rakhi hoon. Hum wahan se shuru karte hain jahan humne chhoda tha.",
                    "Hello {$name}! Rakhi bol rahi hoon. Chaliye wahan se continue karte hain jahan baat ruki thi.",
                    "Namaste {$name}! Main Rakhi hoon — aapki health coach. Hum apni baat continue karte hain.",
                ];
                return $variants[array_rand($variants)];
            }
            $variants = [
                "Hey {$name}! It's Rakhi. Let's pick up right where we left off.",
                "Hello {$name}! Rakhi here. Let's continue from where we stopped.",
                "Hi {$name}! It's Rakhi — let's carry on with our conversation.",
            ];
            return $variants[array_rand($variants)];
        }

        $hour     = now()->hour;
        $greeting = match(true) {
            $hour < 12 => $isHindi ? 'Subah bakhair' : 'Good morning',
            $hour < 17 => $isHindi ? 'Namaste' : 'Good afternoon',
            default    => $isHindi ? 'Namaste' : 'Good evening',
        };

        if ($isHindi) {
            $variants = [
                "{$greeting} {$name}! Main Rakhi hoon, aapki health coach. Aaj aap kaise hain?",
                "{$greeting} {$name}! Rakhi bol rahi hoon. Batayein, aaj kaisa feel ho raha hai?",
                "Namaste {$name}! Main Rakhi hoon. Aaj kya chal raha hai — kaise hain aap?",
            ];
            return $variants[array_rand($variants)];
        }

        $variants = [
            "{$greeting} {$name}! I'm Rakhi, your health coach. How are you feeling today?",
            "{$greeting} {$name}! Rakhi here. How's everything going?",
            "Hey {$name}! I'm Rakhi. How are you doing today?",
        ];
        return $variants[array_rand($variants)];
    }

    private function isEndOfCallIntent(string $text): bool
    {
        $msg      = strtolower(trim($text));
        $patterns = [
            '/\b(bye|goodbye|good bye|end call|stop call|hang up|disconnect|that\'s all|that is all|i\'m done|i am done|talk later|see you)\b/',
            '/\b(bye|alvida|band karo|call band|rakhna|phone rakh|bas karo|theek hai bye|ok bye|achha bye)\b/',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $msg)) return true;
        }
        return false;
    }

    private function synthesizeSafe(string $text, string $langCode): string
    {
        try {
            return $this->tts->synthesize(text: $text, languageCode: $langCode) ?? '';
        } catch (\Exception $e) {
            Log::error('TTS synthesize failed: ' . $e->getMessage());
            return '';
        }
    }

    private function toTtsCode(string $langCode): string
    {
        return match(true) {
            $langCode === 'hi', $langCode === 'hi-roman' => 'hi-IN',
            str_ends_with($langCode, '-request')         => 'hi-IN',
            $langCode === 'ta'                           => 'ta-IN',
            $langCode === 'te'                           => 'te-IN',
            $langCode === 'mr'                           => 'mr-IN',
            $langCode === 'bn'                           => 'bn-IN',
            default                                      => 'en-IN',
        };
    }

    private function toGoogleCode(string $langCode): string
    {
        return $this->toTtsCode($langCode);
    }

    private function stripEmojisForVoice(string $text): string
    {
        // Remove emojis — TTS reads them as "emoji" or skips awkwardly
        $text = preg_replace('/[\x{1F300}-\x{1FFFF}]/u', '', $text);
        $text = preg_replace('/[\x{2600}-\x{27BF}]/u', '', $text);
        return trim($text);
    }
}
