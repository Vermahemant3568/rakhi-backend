<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\AI\ContextBuilder;
use App\Services\Coach\CoachRouter;
use App\Services\NLP\EntityExtractor;
use App\Services\NLP\IntentDetector;
use App\Services\NLP\MoodAnalyzer;
use App\Services\NLP\SentimentAnalyzer;
use App\Services\Safety\MedicalBoundaryChecker;
use App\Services\Safety\SafetyLayer;
use App\Events\MessageSent;
use App\Jobs\GenerateDietPlan;
use App\Jobs\GenerateFitnessPlan;
use Illuminate\Http\Request;

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
    ) {}

    public function startSession(Request $request)
    {
        $request->validate([
            'coach_id'              => 'nullable|exists:coaches,id',
            'is_first_consultation' => 'nullable|boolean',
        ]);

        $user = auth()->user();

        $coachId = $request->coach_id
            ?? $user->primaryCoach()?->id
            ?? 1;

        $session = ChatSession::where('user_id', $user->id)
            ->where('coach_id', $coachId)
            ->where('status', 'active')
            ->latest('id')
            ->first();

        if (!$session) {
            $session = ChatSession::create([
                'user_id'               => $user->id,
                'coach_id'              => $coachId,
                'session_type'          => 'chat',
                'is_first_consultation' => $request->is_first_consultation ?? 0,
                'status'                => 'active',
            ]);
        }

        if ($request->is_first_consultation) {
            $intro = $this->buildFirstConsultationMessage($user);
            $this->saveMessage($session->id, $user->id, 'rakhi', $intro);
        }

        return response()->json([
            'success' => true,
            'session' => $session->load('coach'),
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

        $userMsg = $this->saveMessage($session->id, $user->id, 'user', $userMessage);

        broadcast(new MessageSent($session->id, 'user', $userMessage))->toOthers();

        $detectedIntent    = $this->intent->detect($userMessage);
        $detectedSentiment = $this->sentiment->analyze($userMessage);
        $detectedMood      = $this->moodAnalyzer->analyze($userMessage);

        $safetyResult = $this->safety->check($userMessage);
        if (!$safetyResult['is_safe']) {
            return $this->sendRakhiResponse($session, $user, $safetyResult['response'], $detectedMood);
        }

        if ($this->boundary->check($userMessage)) {
            return $this->sendRakhiResponse($session, $user, $this->boundary->getBoundaryResponse($userMessage), $detectedMood);
        }

        if ($this->intent->isAskingForPlan($userMessage)) {
            return $this->handlePlanRequest($user, $session, $userMessage, $detectedMood);
        }

        $coach        = $this->coachRouter->resolveCoach($user, $userMessage);
        $coachService = $this->resolveCoachService($coach->slug);

        $rakhiResponse = $coachService->respond(
            user: $user,
            message: $userMessage,
            sessionId: $session->id
        );

        return $this->sendRakhiResponse($session, $user, $rakhiResponse, $detectedMood, $coach->id);
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
            ->latest()
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
        return ChatMessage::create([
            'session_id'   => $sessionId,
            'user_id'      => $userId,
            'role'         => $role,
            'message'      => $message,
            'message_type' => 'text',
            'coach_id'     => $coachId,
            'llm_provider' => $role === 'rakhi'
                ? app(\App\Services\AI\LLMRouter::class)->getActiveConfig()->provider
                : null,
        ]);
    }

    private function handlePlanRequest($user, ChatSession $session, string $message, string $mood)
    {
        // Dispatch synchronously so plans generate immediately without queue worker
        GenerateDietPlan::dispatchSync($user, $session->id);
        GenerateFitnessPlan::dispatchSync($user, $session->id);

        $response = "Done! I've just created your personalized Diet Plan and Fitness Plan. 📋 Check the messages above to download them. Let's get started! 💪";

        return $this->sendRakhiResponse($session, $user, $response, $mood);
    }

    private function buildFirstConsultationMessage($user): string
    {
        $name  = $user->first_name ?? 'there';
        $goals = $user->goals->pluck('name')->join(', ');

        return "Hi {$name}! 🌸 I'm Rakhi, your personal health " .
               "and wellness coach.\n\n" .
               "I can see your goals are: {$goals}.\n\n" .
               "I'm so excited to start this journey with you! " .
               "Let me ask you a few questions to understand " .
               "you better and create your personalized plan.\n\n" .
               "First — how are you feeling today? 😊";
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
