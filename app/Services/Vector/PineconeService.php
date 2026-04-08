<?php

namespace App\Services\Vector;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PineconeService
{
    private string $apiKey;
    private string $baseUrl;
    private string $index;

    public function __construct()
    {
        $this->apiKey  = config('services.pinecone.api_key');
        $this->baseUrl = config('services.pinecone.host');
        $this->index   = config('rakhi.pinecone_index');
    }

    private function headers(): array
    {
        return [
            'Api-Key'      => $this->apiKey,
            'Content-Type' => 'application/json',
        ];
    }

    public function upsert(
        string $namespace,
        string $id,
        array $vector,
        array $metadata = []
    ): bool {
        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/vectors/upsert", [
                'namespace' => $namespace,
                'vectors'   => [[
                    'id'       => $id,
                    'values'   => $vector,
                    'metadata' => $metadata,
                ]],
            ]);

        if ($response->failed()) {
            Log::error('Pinecone upsert failed: ' . $response->body());
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
            ->post("{$this->baseUrl}/query", $body);

        if ($response->failed()) {
            Log::error('Pinecone query failed: ' . $response->body());
            return [];
        }

        return $response->json('matches') ?? [];
    }

    public function delete(string $namespace, string $id): bool
    {
        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/vectors/delete", [
                'namespace' => $namespace,
                'ids'       => [$id],
            ]);

        return $response->successful();
    }
}
