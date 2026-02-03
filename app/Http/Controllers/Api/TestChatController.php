<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AI\AiService;
use Illuminate\Http\Request;

class TestChatController extends Controller
{
    public function testChat(Request $request)
    {
        $request->validate([
            'message' => 'required|string'
        ]);

        try {
            $aiService = new AiService();
            $reply = $aiService->reply($request->message);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'reply' => $reply,
                    'intent' => 'general',
                    'emotion' => 'neutral'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}