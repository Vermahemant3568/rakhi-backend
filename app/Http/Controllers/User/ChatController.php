<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\AI\ContextBuilder;
use App\Services\AI\ProactiveReminderService;
use App\Services\AI\WelcomeConsultationService;
use App\Services\Coach\CoachRouter;
use App\Services\NLP\EntityExtractor;
use App\Services\NLP\IntentDetector;
use App\Services\NLP\MoodAnalyzer;
use App\Services\NLP\SentimentAnalyzer;
use App\Services\NLP\LanguageDetector;
use App\Services\Safety\MedicalBoundaryChecker;
use App\Services\Safety\SafetyLayer;
use App\Jobs\GenerateDietPlan;
use App\Jobs\GenerateFitnessPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    public function __construct(
        private CoachRouter $coachRouter,
        private SafetyLayer $safety,
        private MedicalBoundaryChecker $boundary,
        private IntentDetector $intent,
        private SentimentAnalyzer $sentiment,
        private MoodAnalyzer $moodAnalyzer,
        private EntityExtractor $entities,
        private ContextBuilder $contextBuilder,
        private WelcomeConsultationService $welcomeService,
        private LanguageDetector $languageDetector,
        private ProactiveReminderService $proactiveReminder,
    ) {}

    public function startSession(Request $request)
    {
        $request->validate([
            'coach_id' => 'nullable|exists:coaches,id',
        ]);

        $user = auth()->user();

        $coachId = $request->coach_id
            ?? $user->primaryCoach()?->id
            ?? 1;

        $isFirstConsultation = !($user->first_consultation_complete ?? false);

        $session = ChatSession::where('user_id', $user->id)
            ->where('coach_id', $coachId)
            ->where('status', 'active')
            ->orderBy('id', 'desc')
            ->first();

        if (!$session) {
            $session = ChatSession::create([
                'user_id'               => $user->id,
                'coach_id'              => $coachId,
                'session_type'          => 'chat',
                'is_first_consultation' => $isFirstConsultation,
                'status'                => 'active',
            ]);
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
            'user_id'               => $user->id,
            'coach_id'              => $session->coach_id,
            'session_type'          => 'voice',
            'is_first_consultation' => true,
            'status'                => 'active',
        ]);

        $user->load('goals');
        $lang         = $session->detected_language ?? 'en';
        $greetingText = $this->welcomeService->getVoiceWelcomeMessage($user, $lang);

        ChatMessage::create([
            'session_id'   => $voiceSession->id,
            'user_id'      => $user->id,
            'role'         => 'rakhi',
            'message'      => $greetingText,
            'message_type' => 'voice',
        ]);

        return response()->json([
            'success'       => true,
            'voice_session' => $voiceSession,
            'greeting'      => $greetingText,
        ]);
    }

    public function sendMessage(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:chat_sessions,id',
            'message'    => 'required|string|max:2000',
        ]);

        $user    = auth()->user();
        $session = ChatSession::findOrFail($request->session_id);

        if ($session->user_id !== $user->id) {
            return response()->json(['success' => false], 403);
        }

        $userMessage = trim($request->message);

        $this->saveMessage($session->id, $user->id, 'user', $userMessage);

        $this->proactiveReminder->markUserResponded($user);

        $detectedLang = $this->languageDetector->detect($userMessage);
        if ($detectedLang !== 'en') {
            $session->update(['detected_language' => $detectedLang]);
        }

        $detectedMood = $this->moodAnalyzer->analyze($userMessage);

        if ($session->is_first_consultation) {
            return $this->handleFirstConsultationMessage($session, $user, $userMessage, $detectedMood);
        }

        $coach        = $this->coachRouter->resolveCoach($user, $userMessage);
        $coachService = $this->resolveCoachService($coach->slug);

        try {
            $rakhiResponse = $coachService->respond($user, $userMessage, $session->id);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('LLM timeout on sendMessage, retrying: ' . $e->getMessage());
            try {
                $rakhiResponse = $coachService->respond($user, $userMessage, $session->id);
            } catch (\Exception $retryEx) {
                Log::error('LLM retry also failed: ' . $retryEx->getMessage());
                $rakhiResponse = $this->timeoutFallback($user->first_name);
            }
        } catch (\Exception $e) {
            Log::error('sendMessage error: ' . $e->getMessage());
            $rakhiResponse = $this->timeoutFallback($user->first_name);
        }

        return $this->sendRakhiResponse($session, $user, $rakhiResponse, $detectedMood, $coach->id);
    }

    private function handleFirstConsultationMessage(ChatSession $session, $user, string $userMessage, string $mood)
    {
        /** @var \App\Services\Coach\ConsultationCoach $consultationCoach */
        $consultationCoach = $this->coachRouter->resolveCoachService('consultation-coach');

        try {
            $response = $consultationCoach->respond($user, $userMessage, $session->id);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('Consultation LLM timeout, retrying: ' . $e->getMessage());
            try {
                $response = $consultationCoach->respond($user, $userMessage, $session->id);
            } catch (\Exception $retryEx) {
                Log::error('Consultation retry failed: ' . $retryEx->getMessage());
                return $this->sendRakhiResponse($session, $user, $this->timeoutFallback($user->first_name), $mood);
            }
        } catch (\Exception $e) {
            Log::error('Consultation response error: ' . $e->getMessage());
            return $this->sendRakhiResponse($session, $user, $this->timeoutFallback($user->first_name), $mood);
        }

        // ── Load history & goal for field checks ──────────────────────────────
        $history = ChatMessage::where('session_id', $session->id)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($m) => ['role' => $m->role, 'message' => $m->message])
            ->toArray();

        $user->loadMissing(['goals']);
        $goal    = strtolower($user->goals->pluck('name')->first() ?? 'general');
        $missing = $this->welcomeService->getMissingFields($history, $goal);
        $lang    = $session->detected_language ?? 'en';

        $userTurns = collect($history)->where('role', 'user')->count();

        // ── Safety valve: force completion if LLM forgot [GENERATE_PLANS] ────
        // Fires when all fields are collected + min turns met but LLM omitted the trigger
        if (
            !str_contains($response, '[GENERATE_PLANS]') &&
            $userTurns >= WelcomeConsultationService::MIN_USER_TURNS &&
            empty($missing)
        ) {
            Log::info('Safety valve fired — forcing plan generation', [
                'session_id' => $session->id,
                'user_turns' => $userTurns,
            ]);
            $response = $this->welcomeService->getCompletionMessage($user->first_name ?? '', $lang)
                . "\n[GENERATE_PLANS]";
        }

        // ── Handle [GENERATE_PLANS] trigger ──────────────────────────────────
        if (str_contains($response, '[GENERATE_PLANS]')) {

            // Re-check missing fields (handles the case where safety valve fired
            // but fields were actually not all collected yet — belt-and-suspenders)
            if (!empty($missing)) {
                $response = str_replace('[GENERATE_PLANS]', '', trim($response));
                Log::warning('GENERATE_PLANS fired but fields still missing — suppressed', [
                    'missing'    => $missing,
                    'session_id' => $session->id,
                ]);
                return $this->sendRakhiResponse($session, $user, $response, $mood);
            }

            $completion = $this->welcomeService->getCompletionMessage($user->first_name ?? '', $lang);

            // Mark session AND user as consultation complete
            $session->update(['is_first_consultation' => false]);
            $user->update(['first_consultation_complete' => true]);

            $userId    = $user->id;
            $sessionId = $session->id;
            defer(function () use ($userId, $sessionId) {
                try {
                    $u = \App\Models\User::find($userId);
                    if ($u) app(WelcomeConsultationService::class)->generateAllPlans($u, $sessionId);
                } catch (\Throwable $e) {
                    Log::error('Plan generation failed: ' . $e->getMessage());
                }
            });

            return $this->sendRakhiResponse($session, $user, $completion, $mood);
        }

        return $this->sendRakhiResponse($session, $user, $response, $mood);
    }

    public function history(int $sessionId)
    {
        $session = ChatSession::where('id', $sessionId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $messages = ChatMessage::where('session_id', $sessionId)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success'  => true,
            'session'  => $session->load('coach'),
            'messages' => $messages,
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

    private function resolveCoachService(string $slug): object
    {
        return $this->coachRouter->resolveCoachService($slug);
    }
}
