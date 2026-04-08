<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\MealLog;
use App\Services\Vision\MealVisionService;
use App\Services\AI\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MealVisionController extends Controller
{
    public function __construct(
        private MealVisionService $visionService,
        private GeminiService $gemini,
    ) {}

    public function analyze(Request $request): JsonResponse
    {
        $request->validate([
            'image'      => 'required|image|max:5120', // 5MB max
            'meal_time'  => 'nullable|in:breakfast,lunch,dinner,snack',
            'session_id' => 'nullable|exists:chat_sessions,id',
        ]);

        $user = $request->user();

        // Store image
        $path     = $request->file('image')->store("meals/{$user->id}", 's3');
        $imageUrl = Storage::disk('s3')->url($path);

        // Analyze via Gemini Vision
        $analysis = $this->visionService->analyze($imageUrl);

        // Generate personalized advice
        $advice = $this->generateAdvice($user, $analysis);

        // Store meal log
        $mealLog = MealLog::create([
            'user_id'      => $user->id,
            'session_id'   => $request->session_id,
            'image_url'    => $imageUrl,
            'meal_name'    => $analysis['meal_name'] ?? null,
            'meal_time'    => $request->meal_time ?? $analysis['meal_time_suggestion'] ?? null,
            'calories'     => $analysis['calories'] ?? null,
            'protein'      => $analysis['protein'] ?? null,
            'carbs'        => $analysis['carbs'] ?? null,
            'fat'          => $analysis['fat'] ?? null,
            'fiber'        => $analysis['fiber'] ?? null,
            'analysis_raw' => $analysis,
            'rakhi_advice' => $advice,
            'logged_date'  => today(),
        ]);

        return response()->json([
            'meal_log' => $mealLog,
            'analysis' => $analysis,
            'advice'   => $advice,
        ], 201);
    }

    public function history(Request $request): JsonResponse
    {
        $user = $request->user();

        $logs = MealLog::where('user_id', $user->id)
            ->when($request->filled('date'), fn($q) => $q->whereDate('logged_date', $request->date))
            ->when($request->filled('meal_time'), fn($q) => $q->where('meal_time', $request->meal_time))
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($logs);
    }

    public function daily(Request $request): JsonResponse
    {
        $user = $request->user();
        $date = $request->get('date', today()->toDateString());

        $logs = MealLog::where('user_id', $user->id)
            ->whereDate('logged_date', $date)
            ->get();

        return response()->json([
            'date'     => $date,
            'meals'    => $logs,
            'totals'   => [
                'calories' => $logs->sum('calories'),
                'protein'  => $logs->sum('protein'),
                'carbs'    => $logs->sum('carbs'),
                'fat'      => $logs->sum('fat'),
                'fiber'    => $logs->sum('fiber'),
            ],
        ]);
    }

    public function destroy(MealLog $mealLog, Request $request): JsonResponse
    {
        if ($mealLog->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $mealLog->delete();

        return response()->json(['message' => 'Meal log deleted']);
    }

    private function generateAdvice(object $user, array $analysis): string
    {
        $goals   = $user->goals()->pluck('name')->implode(', ');
        $calories = $analysis['calories'] ?? 'unknown';
        $meal     = $analysis['meal_name'] ?? 'this meal';
        $score    = $analysis['health_score'] ?? 'N/A';

        $prompt = <<<PROMPT
You are Rakhi, a personal health coach. The user just logged a meal.

User profile:
- Goals: {$goals}
- Diet preference: {$user->diet_preference}
- Activity level: {$user->activity_level}

Meal analyzed:
- Name: {$meal}
- Calories: {$calories} kcal
- Health score: {$score}/10
- Nutrients: Protein {$analysis['protein']}g, Carbs {$analysis['carbs']}g, Fat {$analysis['fat']}g, Fiber {$analysis['fiber']}g

Give a short, warm, personalized advice (2-3 sentences) about this meal in context of their goals.
PROMPT;

        return $this->gemini->chat($prompt, [], '');
    }
}
