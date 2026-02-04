<?php

namespace App\Services\Coach;

use App\Services\Vision\FoodImageAnalyzer;
use App\Services\Nutrition\NutritionEstimator;

class MealResponseGenerator
{
    protected $intentAnalyzer;
    protected $timeAwareness;
    protected $habitMemory;
    protected $languageService;
    protected $foodAnalyzer;
    protected $nutritionEstimator;
    
    public function __construct()
    {
        $this->intentAnalyzer = new MealIntentAnalyzer();
        $this->timeAwareness = new TimeAwarenessService();
        $this->habitMemory = new HabitMemoryService();
        $this->languageService = new LanguageService();
        $this->foodAnalyzer = new FoodImageAnalyzer();
        $this->nutritionEstimator = new NutritionEstimator();
    }
    
    public function generateMealResponse($user, string $message, ?string $imageUrl = null): string
    {
        // 🧠 1️⃣ Intent Analysis
        $intent = $this->intentAnalyzer->analyze($message);
        
        // 🧠 2️⃣ Time Awareness
        $timeContext = $this->timeAwareness->getTimeContext();
        
        // 🧠 3️⃣ Habit Memory
        $patterns = $this->habitMemory->analyzePatterns($user);
        
        // 🧠 4️⃣ Language Mirroring
        $detectedLang = $this->languageService->detect($message);
        
        // Handle guilt/emotional eating with support
        if ($intent['intent'] === 'guilt') {
            return $this->generateSupportiveResponse($detectedLang);
        }
        
        // Handle meal photo analysis
        if ($imageUrl) {
            return $this->generateMealAnalysisResponse($imageUrl, $detectedLang, $timeContext, $user);
        }
        
        // Handle general meal conversation
        return $this->generateGeneralMealResponse($intent, $timeContext, $patterns, $detectedLang);
    }
    
    private function generateSupportiveResponse(string $language): string
    {
        $responses = [
            'hinglish' => "Koi baat nahi 🙂 ek meal se journey kharab nahi hoti.\nChalo, next meal ko thoda light rakhte hain.",
            'hi' => "कोई बात नहीं 🙂 एक खाना से यात्रा खराब नहीं होती।\nचलो, अगला खाना हल्का रखते हैं।",
            'en' => "It's okay 🙂 one meal doesn't ruin your journey.\nLet's keep the next meal lighter."
        ];
        
        return $responses[$language] ?? $responses['en'];
    }
    
    private function generateMealAnalysisResponse(string $imageUrl, string $language, array $timeContext, $user): string
    {
        $analysis = $this->foodAnalyzer->analyze($imageUrl);
        $nutrition = $this->nutritionEstimator->estimate($analysis['foods']);
        
        $responses = [
            'hinglish' => "Thanks for sharing 😊\nLag raha hai is meal mein " . $this->formatFoods($analysis['foods']) . " hai.\n\nApprox:\n• Protein: moderate\n• Carbs: medium\n• Calories: balanced range\n\n" . $user->goals->first()?->title . " goal ke hisaab se yeh theek hai 👍\nAap batao, yeh " . $timeContext['meal_context'] . " tha?",
            
            'hi' => "शेयर करने के लिए धन्यवाद 😊\nलग रहा है इस खाने में " . $this->formatFoods($analysis['foods']) . " है।\n\nलगभग:\n• प्रोटीन: मध्यम\n• कार्ब्स: मध्यम\n• कैलोरी: संतुलित\n\nयह ठीक लग रहा है 👍",
            
            'en' => "Thanks for sharing 😊\nI can see " . $this->formatFoods($analysis['foods']) . " in this meal.\n\nApprox:\n• Protein: moderate\n• Carbs: medium\n• Calories: balanced range\n\nThis looks good for your goals 👍"
        ];
        
        return $responses[$language] ?? $responses['en'];
    }
    
    private function generateGeneralMealResponse(array $intent, array $timeContext, array $patterns, string $language): string
    {
        // Include habit patterns if available
        if (!empty($patterns)) {
            return $patterns[0]['message'];
        }
        
        // Time-based meal question
        $responses = [
            'hinglish' => $timeContext['meal_question'],
            'hi' => $timeContext['meal_question'],
            'en' => "What did you have for " . $timeContext['meal_context'] . "?"
        ];
        
        return $responses[$language] ?? $responses['en'];
    }
    
    private function formatFoods(array $foods): string
    {
        $formatted = [];
        foreach ($foods as $food) {
            $formatted[] = $food['quantity'] . ' ' . $food['name'];
        }
        return implode(', ', $formatted);
    }
}