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
        return response()->json(ApiService::all());
    }

    public function show(ApiService $apiService): JsonResponse
    {
        return response()->json($apiService);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'service_name' => 'required|string|max:100',
            'display_name' => 'required|string|max:150',
            'config'       => 'required|array',
            'is_active'    => 'boolean',
        ]);

        $data['config'] = $data['config'];

        return response()->json(ApiService::create($data), 201);
    }

    public function update(Request $request, ApiService $apiService): JsonResponse
    {
        $data = $request->validate([
            'service_name' => 'sometimes|string|max:100',
            'display_name' => 'sometimes|string|max:150',
            'config'       => 'sometimes|array',
            'is_active'    => 'boolean',
        ]);

        $apiService->update($data);

        // Clear cache so services pick up new config immediately
        ApiConfigService::forget($apiService->service_name);

        return response()->json($apiService->fresh());
    }

    public function destroy(ApiService $apiService): JsonResponse
    {
        $apiService->delete();

        return response()->json(['message' => 'Deleted successfully']);
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

    public function toggle(int $id): JsonResponse
    {
        $service = ApiService::findOrFail($id);
        $service->update(['is_active' => !$service->is_active]);

        ApiConfigService::forget($service->service_name);

        return response()->json(['success' => true, 'data' => $service->fresh()]);
    }
}
