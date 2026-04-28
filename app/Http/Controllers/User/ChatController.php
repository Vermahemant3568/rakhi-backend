<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\AI\EngagementTracker;
use App\Services\AI\ProactiveReminderService;
use App\Services\AI\UnifiedInputProcessor;
use App\Services\AI\WelcomeConsultationService;
use App\Services\NLP\LanguageDetector;
use App\Services\NLP\MoodAnalyzer;
use App\Events\VoiceSessionStarted;
use App\Services\Notification\PushNotificationService;
use App\Services\Voice\TTSRouter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    public function __construct(
        private UnifiedInputProcessor $processor,
        private WelcomeConsultationService $welcomeService,
        private LanguageDetector $languageDetector,
        private MoodAnalyzer $moodAnalyzer,
        private ProactiveReminderService $proactiveReminder,
        private EngagementTracker $engagementTracker,
        private PushNotificationService $pushNotification,
        private TTSRouter $tts,
    ) {}

    public function startSession(Request $request)
    {
        $request->validate([
            'coach_id' => 'nullable|exists:coaches,id',
        ]);

        $user = auth()->user();

        // Block chat access until onboarding is fully complete.
        // consultation_state is only set to 'pending' by completeOnboarding(),
        // so a null value means the user is still mid-onboarding.
        if (!$user->onboarding_complete || $user->consultation_state === null) {
            return response()->json([
                'success' => false,
                'message' => 'Please complete onboarding before starting a chat.',
                'redirect' => 'onboarding',
            ], 403);
        }

        $coachId = $request->coach_id
            ?? $user->primaryCoach()?->id
            ?? 1;

        $isFirstConsultation = !($user->first_consultation_complete ?? false);

        // Close any stale duplicate active chat sessions for this user+coach
        // keeping only the most recent one
        $existingSessions = ChatSession::where('user_id', $user->id)
            ->where('coach_id', $coachId)
            ->where('session_type', 'chat')
            ->where('status', 'active')
            ->orderBy('id', 'desc')
            ->get();

        $session = $existingSessions->first();

        // Close all duplicates except the most recent
        if ($existingSessions->count() > 1) {
            $staleIds = $existingSessions->skip(1)->pluck('id');
            ChatSession::whereIn('id', $staleIds)->update(['status' => 'closed', 'ended_at' => now()]);
            Log::info('Closed stale duplicate sessions', ['user_id' => $user->id, 'closed_ids' => $staleIds]);
        }

        if (!$session) {
            $session = ChatSession::create([
                'user_id'               => $user->id,
                'coach_id'              => $coachId,
                'session_type'          => 'chat',
                'is_first_consultation' => $isFirstConsultation,
                'status'                => 'active',
            ]);
            // Chat sessions are their own unified root
            $session->update(['unified_session_id' => $session->id]);
        }

        $alreadyGreeted = ChatMessage::where('session_id', $session->id)->exists();

        if (!$alreadyGreeted) {
            DB::transaction(function () use ($session, $user, $isFirstConsultation) {
                $exists = ChatMessage::where('session_id', $session->id)
                    ->lockForUpdate()
                    ->exists();

                if ($exists) return;

                $user->load(['goals', 'language']);
                $lang = $session->detected_language
                    ?? $this->resolveUserLanguage($user);

                if ($isFirstConsultation) {
                    $this->saveMessage($session->id, $user->id, 'rakhi', $this->welcomeService->getWelcomeMessage($user, $lang));
                    $this->saveMessage($session->id, $user->id, 'rakhi', $this->welcomeService->getCallInviteMessage($lang));
                    $session->update(['call_invite_pending' => true]);
                } else {
                    $greeting = $this->welcomeService->getReturningUserGreeting($user, $lang, false);
                    if ($greeting !== '') {
                        $this->saveMessage($session->id, $user->id, 'rakhi', $greeting);
                    }
                }
            });
        }

        $messages = ChatMessage::where('session_id', $session->id)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success'               => true,
            'session'               => $session->load('coach'),
            'messages'              => $messages,
            'is_first_consultation' => $isFirstConsultation,
            'consultation_state'    => $user->consultation_state,
            'engagement_state'      => $user->engagement_state ?? 'active',
            'should_initiate_call'  => $isFirstConsultation && !$alreadyGreeted,
        ]);
    }

    public function declineCall(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:chat_sessions,id',
        ]);

        $user    = auth()->user();
        $session = ChatSession::where('id', $request->session_id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $alreadyStarted = ChatMessage::where('session_id', $session->id)
            ->where('role', 'rakhi')
            ->where('message_type', 'text')
            ->exists();

        if ($alreadyStarted) {
            return response()->json(['success' => true, 'message' => null]);
        }

        $user->loadMissing(['goals', 'language']);
        $lang        = $session->detected_language ?? $this->resolveUserLanguage($user);
        $rakhiOpener = $this->welcomeService->getChatOpener($user, $lang);

        $msg = $this->saveMessage($session->id, $user->id, 'rakhi', $rakhiOpener);

        return response()->json(['success' => true, 'message' => $msg]);
    }

    public function initiateConsultationCall(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:chat_sessions,id',
        ]);

        $user    = auth()->user();
        $session = ChatSession::where('id', $request->session_id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $voiceSession = ChatSession::create([
            'user_id'                => $user->id,
            'coach_id'               => $session->coach_id,
            'session_type'           => 'voice',
            'is_first_consultation'  => true,
            'status'                 => 'active',
            'parent_chat_session_id' => $session->id,
            'detected_language'      => $session->detected_language,
            'detected_script'        => $session->detected_script,
            'unified_session_id'     => $session->unified_session_id ?? $session->id,
        ]);

        $user->load('goals');
        $lang          = $session->detected_language ?? 'en';
        $greetingText        = $this->welcomeService->getVoiceWelcomeMessage($user, $lang);
        $greetingAudioBase64 = $this->synthesizeGreeting($greetingText, $lang);

        ChatMessage::create([
            'session_id'   => $voiceSession->id,
            'user_id'      => $user->id,
            'role'         => 'rakhi',
            'message'      => $greetingText,
            'message_type' => 'voice',
        ]);

        return response()->json([
            'success'                => true,
            'voice_session'          => $voiceSession,
            'greeting'               => $greetingText,
            'greeting_audio_base64'  => $greetingAudioBase64,
            'audio_mime'             => 'audio/mp3',
            'parent_chat_session_id' => $session->id,
        ]);
    }

    public function sendMessage(Request $request)
    {
        $request->validate([
            'session_id'   => 'required|exists:chat_sessions,id',
            'message'      => 'required|string|max:2000',
            'call_failed'  => 'nullable|boolean',
        ]);

        $user    = auth()->user();
        $session = ChatSession::where('id', $request->session_id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($session->status !== 'active') {
            return response()->json(['success' => false, 'message' => 'Session is not active.'], 403);
        }

        $userMessage = trim($request->message);
        $lang        = $session->detected_language ?? $this->resolveUserLanguage($user);

        $this->saveMessage($session->id, $user->id, 'user', $userMessage);

        $this->proactiveReminder->markUserResponded($user);
        $this->engagementTracker->markActive($user);

        $detectedMood = $this->moodAnalyzer->analyze($userMessage);

        // ── Handle call failure report from frontend ──────────────────────────
        if ($request->boolean('call_failed')) {
            $session->update([
                'call_invite_pending'   => false,
                'voice_fallback_active' => true,
            ]);
            $session->increment('call_failed_count');

            $fallbackMsg = $this->welcomeService->getVoiceFallbackMessage($user, $lang);
            $this->saveMessage($session->id, $user->id, 'rakhi', $this->welcomeService->getCallFailedMessage($lang));
            $msg2 = $this->saveMessage($session->id, $user->id, 'rakhi', $fallbackMsg);

            return response()->json(['success' => true, 'message' => $msg2, 'response' => $fallbackMsg, 'mood' => $detectedMood]);
        }

        // ── Detect explicit call request ──────────────────────────────────────
        if ($this->isCallRequest($userMessage)) {
            return $this->triggerVoiceCall($session, $user, $lang, $detectedMood, false);
        }

        // ── Detect "yes to call" intent — ONLY when call invite is pending ────
        if ($session->is_first_consultation && $session->call_invite_pending && $this->isCallAcceptance($userMessage)) {
            $session->update(['call_invite_pending' => false]);
            return $this->triggerVoiceCall($session, $user, $lang, $detectedMood, true);
        }

        // ── Clear stale call invite if user just typed something else ─────────
        if ($session->call_invite_pending) {
            $session->update(['call_invite_pending' => false]);
        }

        // ── Unified pipeline — same brain as voice ────────────────────────────
        $result = $this->processor->process($user, $userMessage, $session, 'chat');

        return $this->sendRakhiResponse($session, $user, $result['response'], $detectedMood);
    }

    private function triggerVoiceCall(ChatSession $session, $user, string $lang, string $mood, bool $isAcceptance): \Illuminate\Http\JsonResponse
    {
        $user->loadMissing(['goals']);

        $voiceSession = ChatSession::create([
            'user_id'                => $user->id,
            'coach_id'               => $session->coach_id,
            'session_type'           => 'voice',
            'is_first_consultation'  => $session->is_first_consultation,
            'status'                 => 'active',
            'parent_chat_session_id' => $session->id,
            'detected_language'      => $session->detected_language,
            'detected_script'        => $session->detected_script,
            'unified_session_id'     => $session->unified_session_id ?? $session->id,
        ]);

        $greetingText        = $this->welcomeService->getVoiceWelcomeMessage($user, $lang);
        $greetingAudioBase64 = $this->synthesizeGreeting($greetingText, $lang);

        ChatMessage::create([
            'session_id'   => $voiceSession->id,
            'user_id'      => $user->id,
            'role'         => 'rakhi',
            'message'      => $greetingText,
            'message_type' => 'voice',
        ]);

        $ack   = $isAcceptance
            ? $this->welcomeService->getCallInitiatingMessage($lang)
            : $this->welcomeService->getCallRequestAck($lang);
        $saved = $this->saveMessage($session->id, $user->id, 'rakhi', $ack);

        broadcast(new VoiceSessionStarted($voiceSession));
        $this->pushNotification->sendToUser($user, 'Rakhi is calling... 📞', $greetingText, [
            'type'                   => 'incoming_call',
            'voice_session_id'       => (string) $voiceSession->id,
            'parent_chat_session_id' => (string) $session->id,
        ]);

        return response()->json([
            'success'                => true,
            'message'                => $saved,
            'response'               => $ack,
            'mood'                   => $mood,
            'trigger_call'           => true,
            'call_delay_ms'          => 2000,
            'voice_session'          => $voiceSession,
            'greeting'               => $greetingText,
            'greeting_audio_base64'  => $greetingAudioBase64,
            'audio_mime'             => 'audio/mp3',
            'parent_chat_session_id' => $session->id,
        ]);
    }

    public function history(int $sessionId)
    {
        $user    = auth()->user();
        $session = ChatSession::where('id', $sessionId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $messages = ChatMessage::where('session_id', $session->id)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success'  => true,
            'session'  => $session->load('coach'),
            'messages' => $messages,
        ]);
    }

    /**
     * Returns the full unified conversation thread across voice + chat sessions.
     * The chat UI should call this to show a seamless conversation history.
     */
    public function unifiedHistory(int $sessionId)
    {
        $user    = auth()->user();
        $session = ChatSession::where('id', $sessionId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $messages = $session->unifiedMessages()->get();

        return response()->json([
            'success'            => true,
            'session'            => $session->load('coach'),
            'messages'           => $messages,
            'unified_session_id' => $session->unified_session_id ?? $session->id,
        ]);
    }

    public function sessions()
    {
        $sessions = ChatSession::where('user_id', auth()->id())
            ->where('session_type', 'chat')
            ->with(['coach', 'messages' => fn($q) => $q->orderBy('created_at', 'desc')->take(1)])
            ->orderBy('id', 'desc')
            ->get();

        return response()->json(['success' => true, 'sessions' => $sessions]);
    }

    private function isCallRequest(string $message): bool
    {
        $msg = strtolower(trim($message));
        $patterns = [
            // English
            '/\bcall me\b/', '/\bcan you call\b/', '/\bplease call\b/',
            '/\bvoice call\b/', '/\bwant to talk\b/', '/\blet.s talk\b/',
            '/\bspeak with you\b/', '/\btalk to you\b/', '/\bon a call\b/',
            '/\bphone call\b/', '/\baudio call\b/',
            // Hindi / Hinglish
            '/\bcall karo\b/', '/\bcall kar\b/', '/\bcall karna\b/',
            '/\bcall chahiye\b/', '/\bcall pe\b/', '/\bcall par\b/',
            '/\bvoice pe\b/', '/\bvoice par\b/', '/\bbaat karna\b/',
            '/\bbaat karni\b/', '/\bbaat karo\b/', '/\bphone karo\b/',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $msg)) return true;
        }
        return false;
    }

    private function isCallAcceptance(string $message): bool
    {
        $msg = strtolower(trim($message));
        // Match whole-word only to avoid false positives (e.g. "ha" inside "that", "what")
        $keywords = ['yes', 'yeah', 'yep', 'sure', 'ok', 'okay', 'haan', 'bilkul', 'zaroor'];
        foreach ($keywords as $kw) {
            if (preg_match('/\b' . preg_quote($kw, '/') . '\b/', $msg)) return true;
        }
        return false;
    }

    private function resolveUserLanguage($user): string
    {
        $langName = strtolower($user->language?->name ?? '');
        return match(true) {
            str_contains($langName, 'hindi')   => 'hi',
            str_contains($langName, 'tamil')   => 'ta',
            str_contains($langName, 'telugu')  => 'te',
            str_contains($langName, 'marathi') => 'mr',
            default                            => 'en',
        };
    }

    private function timeoutFallback(string $firstName = ''): string
    {
        $name = $firstName ? ", {$firstName}" : '';
        return "Hey{$name}, I'm having a little trouble connecting right now 🌸 Give me a moment and try again — I'm here for you!";
    }

    private function sendRakhiResponse(ChatSession $session, $user, string $response, string $mood, ?int $coachId = null)
    {
        $msg = $this->saveMessage($session->id, $user->id, 'rakhi', $response, $coachId);

        return response()->json([
            'success'  => true,
            'message'  => $msg,
            'response' => $response,
            'mood'     => $mood,
        ]);
    }

    private function saveMessage(int $sessionId, int $userId, string $role, string $message, ?int $coachId = null, string $messageType = 'text')
    {
        return ChatMessage::create([
            'session_id'   => $sessionId,
            'user_id'      => $userId,
            'role'         => $role,
            'message'      => $message,
            'message_type' => $messageType,
            'coach_id'     => $coachId,
        ]);
    }

    private function synthesizeGreeting(string $text, string $lang): string
    {
        try {
            $langCode = match(true) {
                str_starts_with($lang, 'hi') => 'hi-IN',
                $lang === 'ta'               => 'ta-IN',
                $lang === 'te'               => 'te-IN',
                $lang === 'mr'               => 'mr-IN',
                default                      => 'en-IN',
            };
            return $this->tts->synthesize($text, $langCode);
        } catch (\Throwable $e) {
            Log::warning('Greeting TTS failed (non-fatal): ' . $e->getMessage());
            return '';
        }
    }

}
