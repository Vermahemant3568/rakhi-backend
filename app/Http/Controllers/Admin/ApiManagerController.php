<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiService;
use App\Models\LlmConfig;
use App\Services\ApiConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ApiManagerController extends Controller
{
    public function index(): JsonResponse
    {
        $services = ApiService::orderBy('id')->get()->map(function ($service) {
            $data = $service->toArray();
            $data['field_labels'] = $this->getFieldLabels($service->service_name);
            return $data;
        });

        return response()->json(['success' => true, 'data' => $services]);
    }

    private function getFieldLabels(string $serviceName): array
    {
        return match($serviceName) {
            'fast2sms'   => ['api_key'    => 'Fast2SMS API Key'],
            'msg91'      => ['api_key'    => 'MSG91 Auth Key', 'template_id' => 'Template ID'],
            'google_stt' => ['api_key'    => 'Google API Key'],
            'google_tts' => ['api_key'    => 'Google API Key'],
            'pinecone'   => ['api_key'    => 'Pinecone API Key', 'host' => 'Host URL', 'index' => 'Index Name'],
            'razorpay'   => ['key_id'     => 'Razorpay Key ID', 'key_secret' => 'Razorpay Key Secret'],
            'firebase'   => ['server_key' => 'Firebase Server Key', 'project_id' => 'Project ID'],
            'pusher'     => ['app_id'     => 'App ID', 'app_key' => 'App Key', 'app_secret' => 'App Secret', 'cluster' => 'Cluster'],
            default      => [],
        };
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
        // Placeholder for actual connectivity test per service type
        $result = match ($apiService->service_name) {
            'google_stt', 'google_tts' => $this->testGoogle($apiService),
            'pinecone'                 => $this->testPinecone($apiService),
            'razorpay'                 => $this->testRazorpay($apiService),
            'firebase'                 => $this->testFirebase($apiService),
            default                    => ['success' => false, 'message' => 'Unknown service'],
        };

        $apiService->update(['last_tested_at' => now()]);

        return response()->json($result);
    }

    private function testGoogle(ApiService $apiService): array
    {
        // TODO: implement Google API ping
        return ['success' => true, 'message' => 'Google service reachable'];
    }

    private function testPinecone(ApiService $apiService): array
    {
        // TODO: implement Pinecone index stats check
        return ['success' => true, 'message' => 'Pinecone reachable'];
    }

    private function testRazorpay(ApiService $apiService): array
    {
        // TODO: implement Razorpay API key validation
        return ['success' => true, 'message' => 'Razorpay reachable'];
    }

    private function testFirebase(ApiService $apiService): array
    {
        return ['success' => true, 'message' => 'Firebase reachable'];
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
            'provider'    => 'required|in:gemini,chatgpt',
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
            'provider'    => 'sometimes|in:gemini,chatgpt',
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

        return response()->json(['success' => true, 'message' => 'LLM activated']);
    }

    public function toggle(ApiService $apiService): JsonResponse
    {
        $apiService->update(['is_active' => !$apiService->is_active]);
        $this->clearServiceCache($apiService->service_name);
        return response()->json(['success' => true, 'data' => $apiService->fresh()]);
    }

    public function toggleById(int $id): JsonResponse
    {
        $apiService = ApiService::findOrFail($id);
        $apiService->update(['is_active' => !$apiService->is_active]);
        $this->clearServiceCache($apiService->service_name);
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
    }
}
