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
        try {
            return Cache::remember("api_config_{$serviceName}", 300, function () use ($serviceName) {
                return static::fetchFromDb($serviceName);
            });
        } catch (\Exception $e) {
            // Cache unavailable (e.g. DB cache table missing) — read directly
            return static::fetchFromDb($serviceName);
        }
    }

    private static function fetchFromDb(string $serviceName): array
    {
        $service = ApiService::where('service_name', $serviceName)
            ->where('is_active', 1)
            ->first();

        return $service?->config ?? [];
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
