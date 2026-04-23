<?php

namespace App\Services;

use App\Models\ApiService;
use Illuminate\Support\Facades\Cache;

class ApiConfigService
{
    /**
     * Get a config value for a service.
     * Cached for 5 minutes — cleared automatically when admin updates.
     */
    public static function get(string $serviceName, string $key, mixed $default = null): mixed
    {
        $config = static::all($serviceName);
        return $config[$key] ?? $default;
    }

    public static function all(string $serviceName): array
    {
        // otp_mode must NEVER be cached — always read fresh from DB
        if ($serviceName === 'otp_mode') {
            return static::fetchFromDb($serviceName);
        }

        try {
            return Cache::remember("api_config_{$serviceName}", 300, function () use ($serviceName) {
                return static::fetchFromDb($serviceName);
            });
        } catch (\Exception $e) {
            return static::fetchFromDb($serviceName);
        }
    }

    private static function fetchFromDb(string $serviceName): array
    {
        $query = ApiService::where('service_name', $serviceName);

        // otp_mode is config-only — read regardless of is_active
        // pinecone is infrastructure — read regardless of is_active so vector ops always work
        if (!in_array($serviceName, ['otp_mode', 'pinecone'])) {
            $query->where('is_active', 1);
        }

        return $query->first()?->config ?? [];
    }

    public static function forget(string $serviceName): void
    {
        try {
            Cache::forget("api_config_{$serviceName}");
        } catch (\Exception $e) {
            // Cache unavailable — nothing to clear
        }
    }
}
