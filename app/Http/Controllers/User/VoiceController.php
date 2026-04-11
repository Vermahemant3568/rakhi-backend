<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\AI\WelcomeConsultationService;
use App\Services\Coach\CoachRouter;
use App\Services\Safety\MedicalBoundaryChecker;
use App\Services\Safety\SafetyLayer;
use App\Services\NLP\LanguageDetector;
use App\Services\Voice\STTService;
use App\Services\Voice\TTSService;
use App\Services\Voice\CallSessionManager;
use App\Events\VoiceSessionStarted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VoiceController extends Controller
{
    public function __construct(
        private STTService $stt,
        private TTSService $tts,
        private CoachRouter $coachRouter,
        private SafetyLayer $safety,
        private MedicalBoundaryChecker $boundary,
        private CallSessionManager $callManager,
        private WelcomeConsultationService $welcomeService,
        private LanguageDetector $languageDetector,
    ) {}

    public function startSession(Request $request)
    {
        $user = auth()->user();

        if (!$user->microphone_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'Please enable microphone access first.',
            ], 403);
        }

        $this->callManager->closeOldSessions($user);

        $coachId             = $user->primaryCoach()?->id ?? 1;
        $isFirstConsultation = !($user->first_consultation_complete ?? false);

        $session = ChatSession::create([
            'user_id'               => $user->id,
            'coach_id'              => $coachId,
            'session_type'          => 'voice',
            'is_first_consultation' => $isFirstConsultation,
            'status'                => 'active',
        ]);

        broadcast(new VoiceSessionStarted($session));

        $user->load('goals');
        $greetingText = $isFirstConsultation
            ? $this->welcomeService->getVoiceWelcomeMessage($user)
            : $this->buildVoiceGreeting($user);

        $audioUrl = $this->synthesizeSafe($greetingText, $user->language?->tts_code ?? 'en-IN');

        ChatMessage::create([
            'session_id'   => $session->id,
            'user_id'      => $user->id,
            'role'         => 'rakhi',
            'message'      => $greetingText,
            'message_type' => 'voice',
        ]);

        return response()->json([
            'success'               => true,
            'session'               => $session,
            'greeting'              => $greetingText,
            'audio_url'             => $audioUrl,
            'is_first_consultation' => $isFirstConsultation,
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

        // Use session's detected language for STT (better transcription accuracy)
        $sttLang = $this->toGoogleCode($session->detected_language ?? 'en');

        try {
            $transcribedText = $this->stt->transcribe(
                audioBase64:  $request->audio,
                mimeType:     $request->mime_type,
                languageCode: $sttLang
            );
        } catch (\Exception $e) {
            Log::error('STT failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => "Sorry, I couldn't hear that clearly. Could you please try again? 🎙️",
            ], 422);
        }

        if (empty($transcribedText)) {
            return response()->json([
                'success' => false,
                'message' => "Sorry, I couldn't hear that clearly. Could you please try again? 🎙️",
            ], 422);
        }

        ChatMessage::create([
            'session_id'   => $session->id,
            'user_id'      => $user->id,
            'role'         => 'user',
            'message'      => $transcribedText,
            'message_type' => 'voice',
        ]);

        // Detect language from transcribed text & persist to session
        $detectedLang = $this->languageDetector->detect($transcribedText);
        if ($detectedLang !== 'en') {
            $session->update(['detected_language' => $detectedLang]);
        }

        $ttsLang = $this->toGoogleCode($session->fresh()->detected_language ?? 'en');

        $safetyResult = $this->safety->check($transcribedText);
        if (!$safetyResult['is_safe']) {
            $audioUrl = $this->synthesizeSafe($safetyResult['response'], $ttsLang);
            return response()->json([
                'success'    => true,
                'transcript' => $transcribedText,
                'response'   => $safetyResult['response'],
                'audio_url'  => $audioUrl,
            ]);
        }

        if ($this->boundary->check($transcribedText)) {
            $boundaryResponse = $this->boundary->getBoundaryResponse($transcribedText);
            $audioUrl = $this->synthesizeSafe($boundaryResponse, $ttsLang);
            return response()->json([
                'success'    => true,
                'transcript' => $transcribedText,
                'response'   => $boundaryResponse,
                'audio_url'  => $audioUrl,
            ]);
        }

        // First consultation voice flow
        if ($session->is_first_consultation) {
            return $this->handleFirstConsultationVoice($session, $user, $transcribedText, $ttsLang);
        }

        // Regular coach response
        try {
            $coach        = $this->coachRouter->resolveCoach($user, $transcribedText);
            $coachService = app($this->resolveCoachClass($coach->slug));

            $rakhiText = $coachService->respond(
                user: $user,
                message: $transcribedText,
                sessionId: $session->id
            );
        } catch (\Exception $e) {
            Log::error('Voice coach respond failed: ' . $e->getMessage());
            $rakhiText = "Sorry, I hit a small snag. Give me a second and try again? 🙏";
            $coach     = null;
        }

        $audioUrl = $this->synthesizeSafe($rakhiText, $ttsLang);

        ChatMessage::create([
            'session_id'   => $session->id,
            'user_id'      => $user->id,
            'role'         => 'rakhi',
            'message'      => $rakhiText,
            'message_type' => 'voice',
            'coach_id'     => $coach?->id,
        ]);

        return response()->json([
            'success'    => true,
            'transcript' => $transcribedText,
            'response'   => $rakhiText,
            'audio_url'  => $audioUrl,
        ]);
    }

    private function handleFirstConsultationVoice(
        ChatSession $session,
        $user,
        string $transcribedText,
        string $ttsLang
    ) {
        // Get LLM-driven consultation response for voice
        try {
            $response = $this->welcomeService->getConsultationResponse(
                session: $session,
                user: $user,
                userMessage: $transcribedText,
                voice: true
            );
        } catch (\Exception $e) {
            Log::error('Voice consultation LLM failed: ' . $e->getMessage());
            $response = "Thanks for sharing that. Tell me a bit more about your daily routine — what does a typical day look like for you?";
        }

        $consultationComplete = false;

        // Check if LLM signalled it's ready to generate plans
        if (str_contains($response, '[GENERATE_PLANS]')) {
            $response = trim(str_replace('[GENERATE_PLANS]', '', $response));
            $consultationComplete = true;

            $audioUrl = $this->synthesizeSafe($response, $ttsLang);

            ChatMessage::create([
                'session_id'   => $session->id,
                'user_id'      => $user->id,
                'role'         => 'rakhi',
                'message'      => $response,
                'message_type' => 'voice',
            ]);

            $this->welcomeService->generateAllPlans($user, $session->id);
            $session->update(['is_first_consultation' => false]);

            $completionText = "Thank you so much for this wonderful conversation. I now have a clear picture of your lifestyle and goals. I am creating your personalized Health Report, Diet Plan, and Fitness Plan right now. I will send them to your chat in just a few moments!";
            $completionAudio = $this->synthesizeSafe($completionText, $ttsLang);

            ChatMessage::create([
                'session_id'   => $session->id,
                'user_id'      => $user->id,
                'role'         => 'rakhi',
                'message'      => $completionText,
                'message_type' => 'voice',
            ]);

            return response()->json([
                'success'               => true,
                'transcript'            => $transcribedText,
                'response'              => $completionText,
                'audio_url'             => $completionAudio,
                'consultation_complete' => true,
            ]);
        }

        $audioUrl = $this->synthesizeSafe($response, $ttsLang);

        ChatMessage::create([
            'session_id'   => $session->id,
            'user_id'      => $user->id,
            'role'         => 'rakhi',
            'message'      => $response,
            'message_type' => 'voice',
        ]);

        return response()->json([
            'success'    => true,
            'transcript' => $transcribedText,
            'response'   => $response,
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

    /**
     * TTS wrapper — never crash the response if TTS fails.
     */
    private function synthesizeSafe(string $text, string $langCode): string
    {
        try {
            return $this->tts->synthesize(text: $text, languageCode: $langCode);
        } catch (\Exception $e) {
            Log::error('TTS synthesize failed: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Map LanguageDetector codes → Google STT/TTS language codes
     */
    private function toGoogleCode(string $langCode): string
    {
        return match(true) {
            $langCode === 'hi'                          => 'hi-IN',
            $langCode === 'hi-roman'                    => 'hi-IN',
            str_ends_with($langCode, '-request')        => 'hi-IN',
            $langCode === 'ta'                          => 'ta-IN',
            $langCode === 'te'                          => 'te-IN',
            $langCode === 'mr'                          => 'mr-IN',
            default                                     => 'en-IN',
        };
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
        return $this->coachRouter->resolveCoachClass($slug);
    }
}
