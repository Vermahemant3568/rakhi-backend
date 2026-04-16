<?php

namespace App\Services\Vector;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\ApiConfigService;

class PineconeService
{
    private function apiKey(): string
    {
        // 1st: Admin Panel DB, 2nd: .env fallback
        return ApiConfigService::get('pinecone', 'api_key')
            ?: config('services.pinecone.api_key', '');
    }

    private function baseUrl(): string
    {
        // 1st: Admin Panel DB, 2nd: .env fallback
        return ApiConfigService::get('pinecone', 'host')
            ?: config('services.pinecone.host', '');
    }

    private function isConfigured(): bool
    {
        return !empty($this->apiKey()) && !empty($this->baseUrl());
    }

    private function headers(): array
    {
        return [
            'Api-Key'      => $this->apiKey(),
            'Content-Type' => 'application/json',
        ];
    }

    public function upsert(
        string $namespace,
        string $id,
        array $vector,
        array $metadata = []
    ): bool {
        if (!$this->isConfigured()) {
            Log::warning('Pinecone upsert skipped — api_key or host not configured.');
            return false;
        }

        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl()}/vectors/upsert", [
                'namespace' => $namespace,
                'vectors'   => [[
                    'id'       => $id,
                    'values'   => $vector,
                    'metadata' => $metadata,
                ]],
            ]);

        if ($response->failed()) {
            Log::error('Pinecone upsert failed: ' . $response->body(), [
                'host'    => $this->baseUrl(),
                'api_key' => substr($this->apiKey(), 0, 10) . '...',
            ]);
            return false;
        }

        return true;
    }

    public function query(
        string $namespace,
        array $vector,
        int $topK = 5,
        array $filter = []
    ): array {
        if (!$this->isConfigured()) {
            Log::warning('Pinecone query skipped — api_key or host not configured.');
            return [];
        }

        $body = [
            'namespace'       => $namespace,
            'vector'          => $vector,
            'topK'            => $topK,
            'includeMetadata' => true,
        ];

        if (!empty($filter)) {
            $body['filter'] = $filter;
        }

        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl()}/query", $body);

        if ($response->failed()) {
            Log::error('Pinecone query failed: ' . $response->body());
            return [];
        }

        return $response->json('matches') ?? [];
    }

    public function delete(string $namespace, string $id): bool
    {
        if (!$this->isConfigured()) {
            Log::warning('Pinecone delete skipped — api_key or host not configured.');
            return false;
        }

        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl()}/vectors/delete", [
                'namespace' => $namespace,
                'ids'       => [$id],
            ]);

        return $response->successful();
    }
}
