<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ensures all api_services rows exist with correct structure.
 * Safe to run multiple times — uses updateOrInsert.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Pinecone — preserve existing api_key if already set, only fix placeholder
        $pinecone = DB::table('api_services')->where('service_name', 'pinecone')->first();
        if ($pinecone) {
            $config = json_decode($pinecone->config, true) ?? [];
            // Only reset if still the literal placeholder string
            if (($config['api_key'] ?? '') === 'your_pinecone_api_key'
                || ($config['api_key'] ?? '') === 'your_pinec...'
            ) {
                $config['api_key'] = '';
                DB::table('api_services')
                    ->where('service_name', 'pinecone')
                    ->update(['config' => json_encode($config)]);
            }
        } else {
            DB::table('api_services')->insert([
                'service_name' => 'pinecone',
                'display_name' => 'Pinecone Vector DB',
                'config'       => json_encode([
                    'api_key' => '',
                    'host'    => '',
                    'index'   => 'rakhi-ai',
                ]),
                'field_labels' => json_encode([
                    'api_key' => 'Pinecone API Key',
                    'host'    => 'Host URL (e.g. https://your-index.svc.pinecone.io)',
                    'index'   => 'Index Name',
                ]),
                'is_active' => 1,
            ]);
        }

        // Pusher — reset placeholder values so the service stops throwing errors
        $pusher = DB::table('api_services')->where('service_name', 'pusher')->first();
        if ($pusher) {
            $config = json_decode($pusher->config, true) ?? [];
            $isPlaceholder = in_array($config['app_key'] ?? '', [
                'your_pusher_app_key', '', 'null',
            ]);
            if ($isPlaceholder) {
                // Deactivate Pusher until real credentials are entered
                DB::table('api_services')
                    ->where('service_name', 'pusher')
                    ->update(['is_active' => 0]);
            }
        }

        // Ensure a chatgpt LLM config row exists (inactive by default)
        // so the fallback path in LLMRouter doesn't throw "config not found"
        $chatgptExists = DB::table('llm_configs')->where('provider', 'chatgpt')->exists();
        if (!$chatgptExists) {
            DB::table('llm_configs')->insert([
                'provider'    => 'chatgpt',
                'api_key'     => encrypt(''),   // empty — admin must fill in
                'model_name'  => 'gpt-4o-mini',
                'is_active'   => 0,
                'max_tokens'  => 300,
                'temperature' => 0.65,
                'top_p'       => 0.85,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Non-destructive migration — no rollback needed
    }
};
