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
use App\Events\MessageSent;
use App\Events\VoiceSessionStarted;
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

        if ($isFirstConsultation && !$alreadyGreeted) {
            // Use a DB lock to prevent duplicate greetings from concurrent requests
            $inserted = \Illuminate\Support\Facades\DB::transaction(function () use ($session, $user) {
                // Re-check inside the lock
                $exists = ChatMessage::where('session_id', $session->id)
                    ->lockForUpdate()
                    ->exists();

                if ($exists) return false;

                $user->load('goals');
                $this->saveMessage($session->id, $user->id, 'rakhi', $this->welcomeService->getWelcomeMessage($user));
                $this->saveMessage($session->id, $user->id, 'rakhi', $this->welcomeService->getCallInviteMessage(), null, 'call_action');
                return true;
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

        $user->loadMissing('goals');
        $rakhiOpener = $this->welcomeService->getChatOpener($user);

        $msg = $this->saveMessage($session->id, $user->id, 'rakhi', $rakhiOpener);
        broadcast(new MessageSent($session->id, 'rakhi', $rakhiOpener));

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

        broadcast(new VoiceSessionStarted($voiceSession));

        $user->load('goals');
        $greetingText = $this->welcomeService->getVoiceWelcomeMessage($user);

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

        broadcast(new MessageSent($session->id, 'user', $userMessage))->toOthers();

        // Mark any pending proactive reminders as responded
        $this->proactiveReminder->markUserResponded($user);

        // Detect & persist language (only update if non-English detected)
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
            Log::warning('LLM timeout on sendMessage: ' . $e->getMessage());
            $rakhiResponse = $this->timeoutFallback($user->first_name);
        } catch (\Exception $e) {
            Log::error('sendMessage error: ' . $e->getMessage());
            $rakhiResponse = $this->timeoutFallback($user->first_name);
        }

        return $this->sendRakhiResponse($session, $user, $rakhiResponse, $detectedMood, $coach->id);
    }

    private function handleFirstConsultationMessage(ChatSession $session, $user, string $userMessage, string $mood)
    {
        try {
            $response = $this->welcomeService->getConsultationResponse(
                session: $session,
                user: $user,
                userMessage: $userMessage
            );
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('Consultation LLM timeout: ' . $e->getMessage());
            return $this->sendRakhiResponse($session, $user, $this->timeoutFallback($user->first_name), $mood);
        } catch (\Exception $e) {
            Log::error('Consultation response error: ' . $e->getMessage());
            return $this->sendRakhiResponse($session, $user, $this->timeoutFallback($user->first_name), $mood);
        }

        if (str_contains($response, '[GENERATE_PLANS]')) {

            $data    = $this->extractConsultationData($session->id);
            $missing = $this->getMissingFields($data);

            if (!empty($missing)) {
                $questions = [
                    'diet'     => "What do you usually eat in a full day? 😊",
                    'activity' => "Do you do any exercise or walking daily?",
                    'sleep'    => "How many hours do you sleep at night?",
                    'stress'   => "Do you feel stressed or tired during the day?"
                ];
                return $this->sendRakhiResponse($session, $user, $questions[$missing[0]], $mood);
            }

            $response = str_replace('[GENERATE_PLANS]', '', $response);

            // Send completion message immediately, generate plans after response
            $completionMsg = $this->welcomeService->getCompletionMessage($user->first_name ?? '');
            $session->update(['is_first_consultation' => false]);

            // Use defer() to run plan generation after HTTP response is sent
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

            return $this->sendRakhiResponse($session, $user, $completionMsg, $mood);
        }

        return $this->sendRakhiResponse($session, $user, $response, $mood);
    }

    private function extractConsultationData(int $sessionId): array
    {
        $text = strtolower(
            ChatMessage::where('session_id', $sessionId)->pluck('message')->implode(' ')
        );

        return [
            'diet' => preg_match('/(eat|food|diet|meal)/', $text),
            'activity' => preg_match('/(exercise|walk|gym|yoga)/', $text),
            'sleep' => preg_match('/(sleep|night)/', $text),
            'stress' => preg_match('/(stress|problem|tension)/', $text),
        ];
    }

    private function getMissingFields(array $data): array
    {
        return array_keys(array_filter($data, fn($v) => !$v));
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

    private function timeoutFallback(string $firstName = ''): string
    {
        $name = $firstName ? ", {$firstName}" : '';
        return "Hey{$name} — I'm just taking a second to think 🌸\n\nCould you send that again? I want to make sure I give you the right response.";
    }

    private function sendRakhiResponse(ChatSession $session, $user, string $response, string $mood, ?int $coachId = null)
    {
        $msg = $this->saveMessage($session->id, $user->id, 'rakhi', $response, $coachId);

        broadcast(new MessageSent($session->id, 'rakhi', $response));

        return response()->json([
            'success' => true,
            'message' => $msg,
            'response' => $response,
            'mood' => $mood,
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