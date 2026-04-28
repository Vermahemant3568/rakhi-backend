<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiService;
use App\Models\LlmConfig;
use App\Services\ApiConfigService;
use App\Services\AI\LLMRouter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiManagerController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => ApiService::orderBy('id')->get(),
        ]);
    }

    public function show(ApiService $apiService): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $apiService]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'service_name' => 'required|string|max:100',
            'display_name' => 'required|string|max:150',
            'config'       => 'required',
            'is_active'    => 'boolean',
        ]);

        // Accept config as JSON string or array
        $data['config'] = is_string($data['config'])
            ? json_decode($data['config'], true) ?? []
            : $data['config'];

        return response()->json(['success' => true, 'data' => ApiService::create($data)], 201);
    }

    public function update(Request $request, ApiService $apiService): JsonResponse
    {
        $data = $request->validate([
            'service_name' => 'sometimes|string|max:100',
            'display_name' => 'sometimes|string|max:150',
            'config'       => 'sometimes',
            'is_active'    => 'sometimes|boolean',
        ]);

        // Accept config as JSON string or array
        if (isset($data['config']) && is_string($data['config'])) {
            $decoded = json_decode($data['config'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid JSON in config field: ' . json_last_error_msg(),
                ], 422);
            }
            $data['config'] = $decoded;
        }

        $apiService->update($data);

        // Clear cache so services pick up new config immediately
        $this->clearServiceCache($apiService->service_name);

        return response()->json(['success' => true, 'data' => $apiService->fresh()]);
    }

    public function destroy(ApiService $apiService): JsonResponse
    {
        $this->clearServiceCache($apiService->service_name);
        $apiService->delete();
        return response()->json(['success' => true, 'message' => 'Deleted successfully']);
    }

    public function test(ApiService $apiService): JsonResponse
    {
        $result = match ($apiService->service_name) {
            'msg91'                    => $this->testMsg91($apiService),
            'fast2sms'                 => $this->testFast2Sms($apiService),
            'google_stt', 'google_tts' => $this->testGoogle($apiService),
            'elevenlabs_tts'           => $this->testElevenLabs($apiService),
            'voice_provider'           => $this->testVoiceProvider($apiService),
            'stt_provider'             => $this->testSttProvider($apiService),
            'groq_stt'                 => $this->testGroqStt($apiService),
            'pinecone'                 => $this->testPinecone($apiService),
            'razorpay'                 => $this->testRazorpay($apiService),
            'firebase'                 => $this->testFirebase($apiService),
            'otp_mode'                 => $this->testOtpMode($apiService),
            'pusher'                   => $this->testPusher($apiService),
            default                    => ['success' => false, 'message' => 'Unknown service'],
        };

        $apiService->update(['last_tested_at' => now()]);

        return response()->json($result);
    }

    private function testMsg91(ApiService $apiService): array
    {
        $apiKey = $apiService->config['api_key'] ?? '';

        if (empty($apiKey)) {
            return ['success' => false, 'message' => 'Invalid MSG91 Auth Key.'];
        }

        try {
            $response = Http::withHeaders([
                'authkey' => $apiKey,
                'accept'  => 'application/json',
            ])->get('https://control.msg91.com/api/v5/balance');

            $data = $response->json();

            if ($response->successful() && isset($data['balance'])) {
                return ['success' => true, 'message' => 'MSG91 connected successfully.'];
            }

            return ['success' => false, 'message' => 'Invalid MSG91 Auth Key.'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Invalid MSG91 Auth Key.'];
        }
    }

    private function testFast2Sms(ApiService $apiService): array
    {
        $apiKey = $apiService->config['api_key'] ?? '';

        if (empty($apiKey)) {
            return ['success' => false, 'message' => 'Invalid Fast2SMS API Key.'];
        }

        try {
            // Wallet endpoint rejects some valid keys — use send endpoint with dummy number instead.
            // status_code 412 = invalid auth key. Any other code means key is valid.
            $response = Http::withHeaders([
                'authorization' => $apiKey,
                'accept'        => 'application/json',
            ])->post('https://www.fast2sms.com/dev/bulkV2', [
                'route'    => 'q',
                'message'  => 'Test',
                'language' => 'english',
                'numbers'  => '9999999999',
            ]);

            $data = $response->json();
            Log::info('Fast2SMS test response', $data ?? []);

            // 412 = invalid auth key
            if (isset($data['status_code']) && $data['status_code'] === 412) {
                return ['success' => false, 'message' => 'Invalid Fast2SMS API Key.'];
            }

            return ['success' => true, 'message' => 'Fast2SMS connected successfully.'];
        } catch (\Exception $e) {
            Log::error('Fast2SMS test exception: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Invalid Fast2SMS API Key.'];
        }
    }

    private function testGoogle(ApiService $apiService): array
    {
        $apiKey = $apiService->config['api_key'] ?? '';

        if (empty($apiKey)) {
            return ['success' => false, 'message' => 'Google API Key is required.'];
        }

        try {
            // Hit a lightweight Google API endpoint to validate the key
            $response = Http::timeout(8)->get(
                'https://texttospeech.googleapis.com/v1/voices?key=' . $apiKey
            );

            if ($response->successful()) {
                return ['success' => true, 'message' => 'Google API key is valid and connected.'];
            }

            if ($response->status() === 400 || $response->status() === 403) {
                return ['success' => false, 'message' => 'Invalid Google API Key or API not enabled.'];
            }

            return ['success' => false, 'message' => 'Google API returned HTTP ' . $response->status()];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Could not reach Google API: ' . $e->getMessage()];
        }
    }

    private function testPusher(ApiService $apiService): array
    {
        $appId     = $apiService->config['app_id'] ?? '';
        $appKey    = $apiService->config['app_key'] ?? '';
        $appSecret = $apiService->config['app_secret'] ?? '';
        $cluster   = $apiService->config['cluster'] ?? 'ap2';

        if (empty($appId) || empty($appKey) || empty($appSecret)) {
            return ['success' => false, 'message' => 'Pusher App ID, App Key, and App Secret are all required.'];
        }

        if ($appKey === 'your_pusher_app_key') {
            return ['success' => false, 'message' => 'Pusher credentials are still placeholders. Please update with real values.'];
        }

        try {
            $timestamp = time();
            $path      = "/apps/{$appId}/channels";
            $params    = "auth_key={$appKey}&auth_timestamp={$timestamp}&auth_version=1.0";
            $signature = hash_hmac('sha256', "GET\n{$path}\n{$params}", $appSecret);

            $response = Http::timeout(8)->get(
                "https://api-{$cluster}.pusher.com{$path}?{$params}&auth_signature={$signature}"
            );

            if ($response->successful()) {
                return ['success' => true, 'message' => 'Pusher connected successfully.'];
            }

            if ($response->status() === 401 || $response->status() === 403) {
                return ['success' => false, 'message' => 'Invalid Pusher credentials.'];
            }

            return ['success' => false, 'message' => 'Pusher returned HTTP ' . $response->status()];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Could not reach Pusher: ' . $e->getMessage()];
        }
    }

    private function testPinecone(ApiService $apiService): array
    {
        $apiKey = $apiService->config['api_key'] ?? '';
        $host   = $apiService->config['host'] ?? '';

        if (empty($apiKey) || empty($host)) {
            return ['success' => false, 'message' => 'Pinecone API Key and Host URL are required.'];
        }

        try {
            $response = Http::withHeaders([
                'Api-Key'      => $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(8)->get(rtrim($host, '/') . '/describe_index_stats');

            if ($response->successful()) {
                $stats = $response->json();
                $vectorCount = $stats['totalVectorCount'] ?? $stats['namespaces'] ?? 'N/A';
                return ['success' => true, 'message' => 'Pinecone connected successfully. Index is reachable.'];
            }

            if ($response->status() === 401 || $response->status() === 403) {
                return ['success' => false, 'message' => 'Invalid Pinecone API Key or unauthorized.'];
            }

            return ['success' => false, 'message' => 'Pinecone returned HTTP ' . $response->status() . '. Check Host URL.'];
        } catch (\Exception $e) {
            Log::error('Pinecone test failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Could not reach Pinecone. Check Host URL and API Key.'];
        }
    }

    private function testRazorpay(ApiService $apiService): array
    {
        $keyId     = $apiService->config['key_id'] ?? '';
        $keySecret = $apiService->config['key_secret'] ?? '';

        if (empty($keyId) || empty($keySecret)) {
            return ['success' => false, 'message' => 'Razorpay Key ID and Key Secret are required.'];
        }

        try {
            $response = Http::withBasicAuth($keyId, $keySecret)
                ->timeout(8)
                ->get('https://api.razorpay.com/v1/payments?count=1');

            if ($response->successful()) {
                return ['success' => true, 'message' => 'Razorpay connected successfully.'];
            }

            if ($response->status() === 401) {
                return ['success' => false, 'message' => 'Invalid Razorpay credentials.'];
            }

            return ['success' => false, 'message' => 'Razorpay returned HTTP ' . $response->status()];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Could not reach Razorpay: ' . $e->getMessage()];
        }
    }

    private function testFirebase(ApiService $apiService): array
    {
        $serverKey = $apiService->config['server_key'] ?? '';

        if (empty($serverKey)) {
            return ['success' => false, 'message' => 'Firebase Server Key is required.'];
        }

        // Validate key format — FCM legacy keys start with AAAA, v1 tokens are JWTs
        if (strlen($serverKey) < 20) {
            return ['success' => false, 'message' => 'Firebase Server Key appears invalid (too short).'];
        }

        $projectId = $apiService->config['project_id'] ?? '';
        if (empty($projectId)) {
            return [
                'success' => true,
                'message' => 'Firebase key is set. Note: Add Project ID to use FCM v1 API (recommended). Currently using legacy FCM.',
            ];
        }

        return ['success' => true, 'message' => 'Firebase configured with project: ' . $projectId];
    }

    private function testElevenLabs(ApiService $apiService): array
    {
        $apiKey = $apiService->config['api_key'] ?? '';

        if (empty($apiKey)) {
            return ['success' => false, 'message' => 'ElevenLabs API Key is required.'];
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders(['xi-api-key' => $apiKey])
                ->get('https://api.elevenlabs.io/v1/user');

            if ($response->successful()) {
                $tier = $response->json('subscription.tier') ?? 'unknown';
                return ['success' => true, 'message' => "ElevenLabs connected. Plan: {$tier}"];
            }

            if ($response->status() === 401) {
                return ['success' => false, 'message' => 'Invalid ElevenLabs API Key.'];
            }

            return ['success' => false, 'message' => 'ElevenLabs returned HTTP ' . $response->status()];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Could not reach ElevenLabs: ' . $e->getMessage()];
        }
    }

    private function testVoiceProvider(ApiService $apiService): array
    {
        $provider = strtolower($apiService->config['provider'] ?? '');

        if (!in_array($provider, ['google', 'elevenlabs'])) {
            return ['success' => false, 'message' => 'Invalid provider. Use: google or elevenlabs'];
        }

        return ['success' => true, 'message' => "Active TTS provider set to: {$provider}"];
    }

    private function testSttProvider(ApiService $apiService): array
    {
        $provider = strtolower($apiService->config['provider'] ?? '');

        if (!in_array($provider, ['google', 'groq'])) {
            return ['success' => false, 'message' => 'Invalid STT provider. Use: google or groq'];
        }

        return ['success' => true, 'message' => "Active STT provider set to: {$provider}"];
    }

    private function testGroqStt(ApiService $apiService): array
    {
        $apiKey = $apiService->config['api_key'] ?? '';

        if (empty($apiKey)) {
            return ['success' => false, 'message' => 'Groq API Key is required.'];
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders(['Authorization' => 'Bearer ' . $apiKey])
                ->get('https://api.groq.com/openai/v1/models');

            if ($response->successful()) {
                $models = collect($response->json('data') ?? [])
                    ->pluck('id')
                    ->filter(fn($m) => str_contains($m, 'whisper'))
                    ->values();

                $modelList = $models->isNotEmpty() ? $models->implode(', ') : 'connected';
                return ['success' => true, 'message' => "Groq STT connected. Whisper models: {$modelList}"];
            }

            if ($response->status() === 401) {
                return ['success' => false, 'message' => 'Invalid Groq API Key.'];
            }

            return ['success' => false, 'message' => 'Groq returned HTTP ' . $response->status()];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Could not reach Groq API: ' . $e->getMessage()];
        }
    }

    private function testOtpMode(ApiService $apiService): array
    {
        $mode = strtoupper($apiService->config['mode'] ?? 'TEST');
        if (!in_array($mode, ['LIVE', 'TEST'])) {
            return ['success' => false, 'message' => 'Invalid OTP mode. Use LIVE or TEST.'];
        }
        return ['success' => true, 'message' => "OTP mode is set to {$mode}."];
    }

    // ── LLM Config Methods ────────────────────────

    public function llmList(): JsonResponse
    {
        $configs = LlmConfig::all()->map(function ($config) {
            $data = $config->toArray();
            try {
                $data['api_key'] = decrypt($config->api_key);
            } catch (\Exception $e) {
                $data['api_key'] = $config->api_key;
            }
            return $data;
        });

        return response()->json(['success' => true, 'data' => $configs]);
    }

    public function llmStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider'    => 'required|in:gemini,chatgpt,openrouter',
            'api_key'     => 'required|string',
            'model_name'  => 'required|string|max:100',
            'max_tokens'  => 'nullable|integer|min:100|max:8000',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'top_p'       => 'nullable|numeric|min:0|max:1',
        ]);

        $data['api_key'] = encrypt($data['api_key']);

        return response()->json([
            'success' => true,
            'data'    => LlmConfig::create($data),
        ], 201);
    }

    public function llmUpdate(Request $request, int $id): JsonResponse
    {
        Log::info('llmUpdate called', ['id' => $id, 'body' => $request->all()]);

        $config = LlmConfig::findOrFail($id);

        $data = $request->validate([
            'provider'    => 'sometimes|in:gemini,chatgpt,openrouter',
            'api_key'     => 'sometimes|string',
            'model_name'  => 'sometimes|string|max:100',
            'max_tokens'  => 'sometimes|integer|min:100|max:8000',
            'temperature' => 'sometimes|numeric|min:0|max:2',
            'top_p'       => 'sometimes|numeric|min:0|max:1',
        ]);

        if (isset($data['api_key'])) {
            $data['api_key'] = encrypt($data['api_key']);
        }

        $config->update($data);

        // Clear LLM router cache so updated key is picked up immediately
        LLMRouter::clearCache();

        $fresh = $config->fresh();
        $result = $fresh->toArray();
        $result['has_api_key'] = !empty($fresh->getRawOriginal('api_key'));

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function llmActivate(int $id): JsonResponse
    {
        // Deactivate all first
        LlmConfig::query()->update(['is_active' => 0]);

        // Activate selected
        LlmConfig::findOrFail($id)->update(['is_active' => 1]);

        // Clear LLM router cache so new config is picked up immediately
        LLMRouter::clearCache();

        return response()->json(['success' => true, 'message' => 'LLM activated']);
    }

    public function toggle(ApiService $apiService): JsonResponse
    {
        $newState = !$apiService->is_active;
        $apiService->update(['is_active' => $newState]);
        $this->clearServiceCache($apiService->service_name);

        // MSG91 ON → disable Fast2SMS
        if ($apiService->service_name === 'msg91' && $newState) {
            ApiService::where('service_name', 'fast2sms')->update(['is_active' => 0]);
            $this->clearServiceCache('fast2sms');
        }

        return response()->json(['success' => true, 'data' => $apiService->fresh()]);
    }

    public function toggleById(int $id): JsonResponse
    {
        $apiService = ApiService::findOrFail($id);
        $newState   = !$apiService->is_active;
        $apiService->update(['is_active' => $newState]);
        $this->clearServiceCache($apiService->service_name);

        // MSG91 ON → disable Fast2SMS
        if ($apiService->service_name === 'msg91' && $newState) {
            ApiService::where('service_name', 'fast2sms')->update(['is_active' => 0]);
            $this->clearServiceCache('fast2sms');
        }

        return response()->json(['success' => true, 'data' => $apiService->fresh()]);
    }

    /**
     * Clear service config cache safely — works even if cache driver has issues.
     */
    private function clearServiceCache(string $serviceName): void
    {
        try {
            ApiConfigService::forget($serviceName);
        } catch (\Exception $e) {
            Log::warning('Cache clear failed (non-fatal): ' . $e->getMessage());
        }

        // For Pusher: also clear the broadcasting config cache if it exists
        if ($serviceName === 'pusher') {
            try {
                cache()->forget('api_config_pusher');
            } catch (\Exception $e) {}
        }
    }
}
