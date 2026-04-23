<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JobMonitorController extends Controller
{
    public function index(): JsonResponse
    {
        $pendingRaw = DB::table('jobs')
            ->select('id', 'queue', 'attempts', 'available_at', 'created_at', 'payload')
            ->orderBy('id', 'desc')
            ->limit(100)
            ->get();

        $failedRaw = DB::table('failed_jobs')
            ->select('id', 'uuid', 'queue', 'failed_at', 'payload', 'exception')
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get();

        // Count by type in PHP — avoids JSON_EXTRACT which fails on some DB drivers
        $byType = $pendingRaw->groupBy(function ($j) {
            return json_decode($j->payload, true)['displayName'] ?? 'Unknown';
        })->map(fn($group, $name) => ['job_name' => $name, 'count' => $group->count()])
          ->values();

        return response()->json([
            'success' => true,
            'stats'   => [
                'pending_count' => DB::table('jobs')->count(),
                'failed_count'  => DB::table('failed_jobs')->count(),
                'by_type'       => $byType,
            ],
            'pending' => $pendingRaw->map(fn($j) => $this->formatJob($j)),
            'failed'  => $failedRaw->map(fn($j) => $this->formatFailedJob($j)),
        ]);
    }

    public function retryFailed(Request $request): JsonResponse
    {
        $request->validate(['id' => 'required|integer']);

        $job = DB::table('failed_jobs')->where('id', $request->id)->first();
        if (!$job) {
            return response()->json(['success' => false, 'message' => 'Job not found'], 404);
        }

        try {
            DB::table('jobs')->insert([
                'queue'        => $job->queue,
                'payload'      => $job->payload,
                'attempts'     => 0,
                'reserved_at'  => null,
                'available_at' => time(),
                'created_at'   => time(),
            ]);
            DB::table('failed_jobs')->where('id', $request->id)->delete();
            return response()->json(['success' => true, 'message' => 'Job re-queued']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function retryAllFailed(): JsonResponse
    {
        $jobs  = DB::table('failed_jobs')->get();
        $count = $jobs->count();

        foreach ($jobs as $job) {
            try {
                DB::table('jobs')->insert([
                    'queue'        => $job->queue,
                    'payload'      => $job->payload,
                    'attempts'     => 0,
                    'reserved_at'  => null,
                    'available_at' => time(),
                    'created_at'   => time(),
                ]);
                DB::table('failed_jobs')->where('id', $job->id)->delete();
            } catch (\Exception $e) {
                Log::warning('Could not retry job ' . $job->id . ': ' . $e->getMessage());
            }
        }

        return response()->json(['success' => true, 'message' => "{$count} failed jobs re-queued"]);
    }

    public function deleteFailed(int $id): JsonResponse
    {
        DB::table('failed_jobs')->where('id', $id)->delete();
        return response()->json(['success' => true, 'message' => 'Failed job deleted']);
    }

    public function clearAllFailed(): JsonResponse
    {
        $count = DB::table('failed_jobs')->count();
        DB::table('failed_jobs')->truncate();
        return response()->json(['success' => true, 'message' => "{$count} failed jobs cleared"]);
    }

    public function clearStaleJobs(): JsonResponse
    {
        $count = DB::table('jobs')
            ->where('attempts', 0)
            ->where('available_at', '<', now()->subHours(24)->timestamp)
            ->delete();

        return response()->json(['success' => true, 'message' => "{$count} stale jobs cleared"]);
    }

    private function formatJob(object $job): array
    {
        $payload = json_decode($job->payload, true);
        return [
            'id'           => $job->id,
            'queue'        => $job->queue,
            'job_name'     => $payload['displayName'] ?? 'Unknown',
            'attempts'     => $job->attempts,
            'available_at' => date('Y-m-d H:i:s', $job->available_at),
            'created_at'   => date('Y-m-d H:i:s', $job->created_at),
        ];
    }

    private function formatFailedJob(object $job): array
    {
        $payload          = json_decode($job->payload, true);
        $exceptionSummary = explode("\n", $job->exception ?? '')[0] ?? '';
        return [
            'id'                => $job->id,
            'uuid'              => $job->uuid,
            'queue'             => $job->queue,
            'job_name'          => $payload['displayName'] ?? 'Unknown',
            'failed_at'         => $job->failed_at,
            'exception_summary' => $exceptionSummary,
        ];
    }
}
