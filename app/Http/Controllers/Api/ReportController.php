<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Services\Reports\ReportGenerator;
use App\Services\Reports\ReportTriggerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReportController extends Controller
{
    public function generate(Request $request)
    {
        $user = $request->user();
        $type = $request->type;
        
        $triggerService = new ReportTriggerService(new ReportGenerator());
        
        switch ($type) {
            case 'consultation':
                $report = $triggerService->generateConsultationReport($user, [
                    'summary' => 'Personalized lifestyle guidance...',
                    'notes' => ['Eat regularly', 'Stay hydrated']
                ]);
                break;
            case 'diet':
                $report = $triggerService->generateDietPlanReport($user);
                break;
            case 'fitness':
                $report = $triggerService->generateFitnessPlanReport($user);
                break;
            default:
                return response()->json(['success' => false, 'message' => 'Invalid report type'], 400);
        }
        
        return response()->json([
            'success' => true,
            'data' => $report
        ]);
    }
    
    public function index(Request $request)
    {
        $user = $request->user();
        
        $reports = Report::where('user_id', $user->id)
            ->latest()
            ->get(['id', 'type', 'title', 'file_path', 'created_at']);
        
        return response()->json($reports);
    }
    
    public function download(Request $request, $id)
    {
        $user = $request->user();
        
        $report = Report::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();
        
        if (!Storage::exists($report->file_path)) {
            return response()->json(['success' => false, 'message' => 'File not found'], 404);
        }
        
        return Storage::download($report->file_path, $report->title . '.pdf');
    }
}
