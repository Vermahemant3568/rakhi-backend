<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Memory\MemorySelectorService;
use App\Services\Memory\MemoryManager;
use Illuminate\Http\Request;

class MemoryTestController extends Controller
{
    public function testMemorySearch(Request $request)
    {
        $request->validate([
            'message' => 'required|string'
        ]);

        $user = $request->user();
        $memorySelectorService = new MemorySelectorService();
        
        $memories = $memorySelectorService->getRelevantMemories($user->id, $request->message, 5);
        $memoryContext = $memorySelectorService->formatMemoriesForPrompt($memories);
        
        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $user->id,
                'query' => $request->message,
                'memories_found' => count($memories),
                'memories' => $memories,
                'formatted_context' => $memoryContext
            ]
        ]);
    }

    public function storeTestMemory(Request $request)
    {
        $request->validate([
            'content' => 'required|string',
            'type' => 'required|string'
        ]);

        $user = $request->user();
        $memoryManager = new MemoryManager();
        
        $success = $memoryManager->storeMemory(
            $user->id,
            $request->type,
            $request->content,
            ['test' => true]
        );
        
        return response()->json([
            'success' => $success,
            'message' => $success ? 'Memory stored successfully' : 'Failed to store memory'
        ]);
    }

    public function getUserMemoryStats(Request $request)
    {
        $user = $request->user();
        $memoryManager = new MemoryManager();
        
        $stats = $memoryManager->getUserMemoryStats($user->id);
        
        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}