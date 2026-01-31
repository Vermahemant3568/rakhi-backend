<?php

namespace App\Services\Vector;

use Illuminate\Support\Facades\Http;

class PineconeService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = env('PINECONE_HOST');
    }

    public function upsert(string $id, array $vector, array $metadata = [])
    {
        return Http::withHeaders([
            'Api-Key' => env('PINECONE_API_KEY'),
            'Content-Type' => 'application/json'
        ])->post($this->baseUrl . '/vectors/upsert', [
            'vectors' => [[
                'id' => $id,
                'values' => $vector,
                'metadata' => $metadata
            ]]
        ]);
    }

    public function query(array $vector, int $topK = 5)
    {
        return Http::withHeaders([
            'Api-Key' => env('PINECONE_API_KEY'),
            'Content-Type' => 'application/json'
        ])->post($this->baseUrl . '/query', [
            'vector' => $vector,
            'topK' => $topK,
            'includeMetadata' => true
        ]);
    }
}