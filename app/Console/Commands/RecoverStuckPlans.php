<?php

namespace App\Console\Commands;

use App\Models\ChatSession;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecoverStuckPlans extends Command
{
    protected $signature   = 'rakhi:recover-stuck-plans';
    protected $description = 'Mark users stuck in generating state (no active job) as failed so they can retry.';

    // If a user has been in generating state longer than this, consider it stuck
    private const STUCK_THRESHOLD_MINUTES = 15;

    public function handle(): void
    {
        $cutoff = now()->subMinutes(self::STUCK_THRESHOLD_MINUTES);

        // Find users stuck in generating with no active queue job
        $stuck = User::where('plan_generation_state', 'generating')
            ->where('updated_at', '<', $cutoff)
            ->get();

        if ($stuck->isEmpty()) {
            $this->info('No stuck plans found.');
            return;
        }

        foreach ($stuck as $user) {
            // Check if there's still an active job in the queue for this user
            $hasActiveJob = DB::table('jobs')
                ->where('payload', 'like', '%"user_id":' . $user->id . '%')
                ->exists();

            if ($hasActiveJob) {
                $this->line("User {$user->id} — job still active, skipping.");
                continue;
            }

            $user->update(['plan_generation_state' => 'failed']);

            // Notify user in their latest chat session
            $session = ChatSession::where('user_id', $user->id)
                ->where('session_type', 'chat')
                ->latest('id')
                ->first();

            if ($session) {
                \App\Models\ChatMessage::create([
                    'session_id'   => $session->id,
                    'user_id'      => $user->id,
                    'role'         => 'rakhi',
                    'message'      => 'Your plan generation timed out. You can retry it from the Plans screen. 🙏',
                    'message_type' => 'text',
                ]);
            }

            Log::warning('Stuck plan recovered', [
                'user_id'    => $user->id,
                'stuck_since'=> $user->updated_at,
            ]);

            $this->warn("User {$user->id} — marked as failed (was stuck since {$user->updated_at}).");
        }

        $this->info("Processed {$stuck->count()} stuck plan(s).");
    }
}
