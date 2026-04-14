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
        $config = Cache::remember("api_config_{$serviceName}", 300, function () use ($serviceName) {
            $service = ApiService::where('service_name', $serviceName)
                ->where('is_active', 1)
                ->first();

            return $service?->config ?? [];
        });

        return $config[$key] ?? $default;
    }

    /**
     * Get the full config array for a service.
     */
    public static function all(string $serviceName): array
    {
        return Cache::remember("api_config_{$serviceName}", 300, function () use ($serviceName) {
            $service = ApiService::where('service_name', $serviceName)
                ->where('is_active', 1)
                ->first();

            return $service?->config ?? [];
        });
    }

    /**
     * Clear cache for a service — call this after admin updates.
     */
    public static function forget(string $serviceName): void
    {
        Cache::forget("api_config_{$serviceName}");
    }
}
