<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\AI\ContextBuilder;
use App\Services\AI\WelcomeConsultationService;
use App\Services\Coach\CoachRouter;
use App\Services\NLP\EntityExtractor;
use App\Services\NLP\IntentDetector;
use App\Services\NLP\MoodAnalyzer;
use App\Services\NLP\SentimentAnalyzer;
use App\Services\Safety\MedicalBoundaryChecker;
use App\Services\Safety\SafetyLayer;
use App\Events\MessageSent;
use App\Events\VoiceSessionStarted;
use App\Jobs\GenerateDietPlan;
use App\Jobs\GenerateFitnessPlan;
use Illuminate\Http\Request;
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

        // Send welcome text message only once on brand-new first consultation
        $alreadyGreeted = ChatMessage::where('session_id', $session->id)->exists();
        if ($isFirstConsultation && !$alreadyGreeted) {
            $user->load('goals');
            $intro = $this->welcomeService->getWelcomeMessage($user);
            $this->saveMessage($session->id, $user->id, 'rakhi', $intro);

            // Tell the app Rakhi wants to call — frontend should auto-trigger voice
            $callPrompt = "I'd love to have a quick chat with you to understand your goals better! 📞 Tap the call button and let's talk — it'll only take a few minutes and I'll create your personalized plan right after! 🌸";
            $this->saveMessage($session->id, $user->id, 'rakhi', $callPrompt);
        }

        return response()->json([
            'success'               => true,
            'session'               => $session->load('coach'),
            'is_first_consultation' => $isFirstConsultation,
            'should_initiate_call'  => $isFirstConsultation && !$alreadyGreeted,
        ]);
    }

    /**
     * Called by the app when the user taps "Accept Call" on the first consultation.
     * Creates a voice session and returns the greeting audio URL.
     */
    public function initiateConsultationCall(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:chat_sessions,id',
        ]);

        $user    = auth()->user();
        $session = ChatSession::where('id', $request->session_id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Create a linked voice session for the consultation call
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
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $userMessage = trim($request->message);

        $this->saveMessage($session->id, $user->id, 'user', $userMessage);

        broadcast(new MessageSent($session->id, 'user', $userMessage))->toOthers();

        $detectedMood = $this->moodAnalyzer->analyze($userMessage);

        $safetyResult = $this->safety->check($userMessage);
        if (!$safetyResult['is_safe']) {
            return $this->sendRakhiResponse($session, $user, $safetyResult['response'], $detectedMood);
        }

        if ($this->boundary->check($userMessage)) {
            return $this->sendRakhiResponse($session, $user, $this->boundary->getBoundaryResponse($userMessage), $detectedMood);
        }

        // Handle first consultation chat flow
        if ($session->is_first_consultation) {
            return $this->handleFirstConsultationMessage($session, $user, $userMessage, $detectedMood);
        }

        if ($this->intent->isAskingForPlan($userMessage)) {
            return $this->handlePlanRequest($user, $session, $userMessage, $detectedMood);
        }

        // Intercept call requests — never let LLM say "I can't call you"
        if ($this->intent->isRequestingCall($userMessage)) {
            return $this->handleCallRequest($session, $user, $detectedMood);
        }

        $coach        = $this->coachRouter->resolveCoach($user, $userMessage);
        $coachService = $this->resolveCoachService($coach->slug);

        try {
            $rakhiResponse = $coachService->respond(
                user: $user,
                message: $userMessage,
                sessionId: $session->id
            );
        } catch (\Exception $e) {
            Log::error('Coach respond failed: ' . $e->getMessage());
            return $this->sendRakhiResponse(
                $session, $user,
                "Sorry, I hit a small snag. Give me a second and try again? 🙏",
                $detectedMood
            );
        }

        return $this->sendRakhiResponse($session, $user, $rakhiResponse, $detectedMood, $coach->id);
    }

    private function handleFirstConsultationMessage(
        ChatSession $session,
        $user,
        string $userMessage,
        string $mood
    ) {
        // Handle call issues gracefully — acknowledge and continue in chat
        if ($this->intent->isCallIssue($userMessage)) {
            $name    = $user->first_name ?? '';
            $nameStr = $name ? ", {$name}" : '';
            $ack     = "No worries{$nameStr}! 🌸 Let's just continue here in chat — works just as well. " .
                       "So tell me, how are you feeling today and what's been going on with your health lately?";
            return $this->sendRakhiResponse($session, $user, $ack, $mood);
        }

        // Get LLM-driven consultation response
        try {
            $response = $this->welcomeService->getConsultationResponse(
                session: $session,
                user: $user,
                userMessage: $userMessage,
                voice: false
            );
        } catch (\Exception $e) {
            Log::error('Consultation LLM failed: ' . $e->getMessage());
            $response = "Thanks for sharing that! Tell me a bit more about your daily routine — " .
                        "what does a typical day look like for you?";
        }

        // Check if LLM signalled it's ready to generate plans
        if (str_contains($response, '[GENERATE_PLANS]')) {
            $response = trim(str_replace('[GENERATE_PLANS]', '', $response));

            $this->saveMessage($session->id, $user->id, 'rakhi', $response);
            broadcast(new \App\Events\MessageSent($session->id, 'rakhi', $response));

            $this->welcomeService->generateAllPlans($user, $session->id);
            $session->update(['is_first_consultation' => false]);

            $completionMsg = $this->welcomeService->getCompletionMessage($user->first_name ?? '');
            return $this->sendRakhiResponse($session, $user, $completionMsg, $mood);
        }

        return $this->sendRakhiResponse($session, $user, $response, $mood);
    }

    public function history($sessionId)
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
            ->with(['coach', 'messages' => fn($q) => $q->latest()->take(1)])
            ->orderBy('id', 'desc')
            ->get();

        return response()->json(['success' => true, 'sessions' => $sessions]);
    }

    private function sendRakhiResponse(
        ChatSession $session,
        $user,
        string $response,
        string $mood,
        ?int $coachId = null
    ) {
        $rakhiMsg = $this->saveMessage($session->id, $user->id, 'rakhi', $response, $coachId);

        broadcast(new MessageSent($session->id, 'rakhi', $response));

        return response()->json([
            'success'  => true,
            'message'  => $rakhiMsg,
            'response' => $response,
            'mood'     => $mood,
        ]);
    }

    private function saveMessage(
        int $sessionId,
        int $userId,
        string $role,
        string $message,
        ?int $coachId = null
    ): ChatMessage {
        $provider = null;
        if ($role === 'rakhi') {
            try {
                $provider = app(\App\Services\AI\LLMRouter::class)->getActiveConfig()->provider;
            } catch (\Exception $e) {
                $provider = null;
            }
        }

        return ChatMessage::create([
            'session_id'   => $sessionId,
            'user_id'      => $userId,
            'role'         => $role,
            'message'      => $message,
            'message_type' => 'text',
            'coach_id'     => $coachId,
            'llm_provider' => $provider,
        ]);
    }

    private function handleCallRequest(ChatSession $session, $user, string $mood)
    {
        $name = $user->first_name ?? '';
        $nameStr = $name ? ", {$name}" : '';

        $response = "Of course{$nameStr}! I'm calling you right now 📞 Pick up and let's talk — I'm right here! 🌸";

        $rakhiMsg = $this->saveMessage($session->id, $user->id, 'rakhi', $response);

        broadcast(new MessageSent($session->id, 'rakhi', $response));

        // Create voice session immediately so frontend can connect
        $voiceSession = \App\Models\ChatSession::create([
            'user_id'               => $user->id,
            'coach_id'              => $session->coach_id,
            'session_type'          => 'voice',
            'is_first_consultation' => false,
            'status'                => 'active',
        ]);

        broadcast(new VoiceSessionStarted($voiceSession));

        return response()->json([
            'success'        => true,
            'message'        => $rakhiMsg,
            'response'       => $response,
            'mood'           => $mood,
            'initiate_call'  => true,
            'voice_session'  => $voiceSession,
        ]);
    }

    private function handlePlanRequest($user, ChatSession $session, string $message, string $mood)
    {
        GenerateDietPlan::dispatchSync($user, $session->id);
        GenerateFitnessPlan::dispatchSync($user, $session->id);

        $response = "Done! I've just created your personalized Diet Plan and Fitness Plan. 📋 Check the messages above to download them. Let's get started! 💪";

        return $this->sendRakhiResponse($session, $user, $response, $mood);
    }

    private function resolveCoachService(string $slug): object
    {
        return match($slug) {
            'diabetes-coach'        => app(\App\Services\Coach\DiabetesCoach::class),
            'diet-nutrition-coach'  => app(\App\Services\Coach\DietNutritionCoach::class),
            'fitness-coach'         => app(\App\Services\Coach\FitnessCoach::class),
            'pcos-thyroid-coach'    => app(\App\Services\Coach\PCOSThyroidCoach::class),
            'mental-wellness-coach' => app(\App\Services\Coach\MentalWellnessCoach::class),
            'sleep-coach'           => app(\App\Services\Coach\SleepCoach::class),
            'weight-loss-coach'     => app(\App\Services\Coach\WeightLossCoach::class),
            'pregnancy-coach'       => app(\App\Services\Coach\PregnancyCoach::class),
            'postpartum-coach'      => app(\App\Services\Coach\PostpartumCoach::class),
            'energy-coach'          => app(\App\Services\Coach\EnergyCoach::class),
            'stress-coach'          => app(\App\Services\Coach\StressCoach::class),
            'habit-coach'           => app(\App\Services\Coach\HabitCoach::class),
            'vision-coach'          => app(\App\Services\Coach\VisionCoach::class),
            default                 => app(\App\Services\Coach\DietNutritionCoach::class),
        };
    }
}
