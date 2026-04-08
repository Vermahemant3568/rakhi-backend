<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\Coach\CoachRouter;
use App\Services\Safety\MedicalBoundaryChecker;
use App\Services\Safety\SafetyLayer;
use App\Services\Voice\STTService;
use App\Services\Voice\TTSService;
use App\Services\Voice\CallSessionManager;
use App\Events\VoiceSessionStarted;
use Illuminate\Http\Request;

class VoiceController extends Controller
{
    public function __construct(
        private STTService $stt,
        private TTSService $tts,
        private CoachRouter $coachRouter,
        private SafetyLayer $safety,
        private MedicalBoundaryChecker $boundary,
        private CallSessionManager $callManager,
    ) {}

    public function startSession(Request $request)
    {
        $user = auth()->user();

        if (!$user->microphone_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'Please enable microphone access first.'
            ], 403);
        }

        $coachId = $user->primaryCoach()?->id ?? 1;

        $session = ChatSession::create([
            'user_id'               => $user->id,
            'coach_id'              => $coachId,
            'session_type'          => 'voice',
            'is_first_consultation' => $request->is_first_consultation ?? 0,
            'status'                => 'active',
        ]);

        broadcast(new VoiceSessionStarted($session));

        $greetingText = $this->buildVoiceGreeting($user);
        $audioUrl     = $this->tts->synthesize(
            text: $greetingText,
            languageCode: $user->language?->tts_code ?? 'en-IN'
        );

        return response()->json([
            'success'   => true,
            'session'   => $session,
            'greeting'  => $greetingText,
            'audio_url' => $audioUrl,
        ]);
    }

    public function sendVoice(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:chat_sessions,id',
            'audio'      => 'required|string',
            'mime_type'  => 'required|string',
        ]);

        $user    = auth()->user();
        $session = ChatSession::findOrFail($request->session_id);

        if ($session->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $transcribedText = $this->stt->transcribe(
            audioBase64:  $request->audio,
            mimeType:     $request->mime_type,
            languageCode: $user->language?->stt_code ?? 'en-IN'
        );

        if (empty($transcribedText)) {
            return response()->json([
                'success' => false,
                'message' => "Sorry, I couldn't hear that clearly. Could you please try again? 🎙️"
            ], 422);
        }

        ChatMessage::create([
            'session_id'   => $session->id,
            'user_id'      => $user->id,
            'role'         => 'user',
            'message'      => $transcribedText,
            'message_type' => 'voice',
        ]);

        $safetyResult = $this->safety->check($transcribedText);
        if (!$safetyResult['is_safe']) {
            $audioUrl = $this->tts->synthesize(
                text: $safetyResult['response'],
                languageCode: $user->language?->tts_code ?? 'en-IN'
            );

            return response()->json([
                'success'    => true,
                'transcript' => $transcribedText,
                'response'   => $safetyResult['response'],
                'audio_url'  => $audioUrl,
            ]);
        }

        if ($this->boundary->check($transcribedText)) {
            $boundaryResponse = $this->boundary->getBoundaryResponse();
            $audioUrl = $this->tts->synthesize(
                text: $boundaryResponse,
                languageCode: $user->language?->tts_code ?? 'en-IN'
            );

            return response()->json([
                'success'    => true,
                'transcript' => $transcribedText,
                'response'   => $boundaryResponse,
                'audio_url'  => $audioUrl,
            ]);
        }

        $coach        = $this->coachRouter->resolveCoach($user, $transcribedText);
        $coachService = app($this->resolveCoachClass($coach->slug));

        $rakhiText = $coachService->respond(
            user: $user,
            message: $transcribedText,
            sessionId: $session->id
        );

        $audioUrl = $this->tts->synthesize(
            text: $rakhiText,
            languageCode: $user->language?->tts_code ?? 'en-IN'
        );

        ChatMessage::create([
            'session_id'   => $session->id,
            'user_id'      => $user->id,
            'role'         => 'rakhi',
            'message'      => $rakhiText,
            'message_type' => 'voice',
            'coach_id'     => $coach->id,
        ]);

        return response()->json([
            'success'    => true,
            'transcript' => $transcribedText,
            'response'   => $rakhiText,
            'audio_url'  => $audioUrl,
        ]);
    }

    public function endSession(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:chat_sessions,id',
        ]);

        $session = ChatSession::where('id', $request->session_id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $session->update(['status' => 'closed', 'ended_at' => now()]);

        return response()->json(['success' => true, 'message' => 'Voice session ended.']);
    }

    private function buildVoiceGreeting($user): string
    {
        $name = $user->first_name ?? 'there';
        $hour = now()->hour;

        $greeting = match(true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default    => 'Good evening',
        };

        return "{$greeting} {$name}! I'm Rakhi, your personal health coach. How are you feeling today?";
    }

    private function resolveCoachClass(string $slug): string
    {
        return match($slug) {
            'diabetes'        => \App\Services\Coach\DiabetesCoach::class,
            'diet-nutrition'  => \App\Services\Coach\DietNutritionCoach::class,
            'fitness'         => \App\Services\Coach\FitnessCoach::class,
            'pcos-thyroid'    => \App\Services\Coach\PCOSThyroidCoach::class,
            'mental-wellness' => \App\Services\Coach\MentalWellnessCoach::class,
            'sleep'           => \App\Services\Coach\SleepCoach::class,
            'weight-loss'     => \App\Services\Coach\WeightLossCoach::class,
            'pregnancy'       => \App\Services\Coach\PregnancyCoach::class,
            'postpartum'      => \App\Services\Coach\PostpartumCoach::class,
            'energy'          => \App\Services\Coach\EnergyCoach::class,
            'stress'          => \App\Services\Coach\StressCoach::class,
            'habit'           => \App\Services\Coach\HabitCoach::class,
            default           => \App\Services\Coach\DietNutritionCoach::class,
        };
    }
}
