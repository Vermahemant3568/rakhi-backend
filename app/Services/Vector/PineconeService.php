<?php

namespace App\Services\Vector;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PineconeService
{
    public function query(array $vector, array $options = []): array
    {
        try {
            $host = env('PINECONE_HOST');
            $apiKey = env('PINECONE_API_KEY');
            
            if (!$host || !$apiKey) {
                Log::error('Pinecone configuration missing');
                return ['matches' => []];
            }
            
            $response = Http::withHeaders([
                'Api-Key' => $apiKey,
                'Content-Type' => 'application/json'
            ])->post("{$host}/query", array_merge([
                'vector' => $vector,
                'topK' => 5,
                'includeMetadata' => true
            ], $options));

            if ($response->successful()) {
                return $response->json();
            }
            
            Log::error('Pinecone API Error: ' . $response->body());
            return ['matches' => []];
            
        } catch (\Exception $e) {
            Log::error('Pinecone Service Error: ' . $e->getMessage());
            return ['matches' => []];
        }
    }

    public function upsert(string $id, array $vector, array $metadata = []): bool
    {
        try {
            $host = env('PINECONE_HOST');
            $apiKey = env('PINECONE_API_KEY');
            
            if (!$host || !$apiKey) {
                Log::error('Pinecone configuration missing');
                return false;
            }
            
            $response = Http::withHeaders([
                'Api-Key' => $apiKey,
                'Content-Type' => 'application/json'
            ])->post("{$host}/vectors/upsert", [
                'vectors' => [
                    [
                        'id' => $id,
                        'values' => $vector,
                        'metadata' => $metadata
                    ]
                ]
            ]);

            if ($response->successful()) {
                return true;
            }
            
            Log::error('Pinecone Upsert Error: ' . $response->body());
            return false;
            
        } catch (\Exception $e) {
            Log::error('Pinecone Upsert Service Error: ' . $e->getMessage());
            return false;
        }
    }

    public function delete(string $id): bool
    {
        try {
            $host = env('PINECONE_HOST');
            $apiKey = env('PINECONE_API_KEY');
            
            if (!$host || !$apiKey) {
                Log::error('Pinecone configuration missing');
                return false;
            }
            
            $response = Http::withHeaders([
                'Api-Key' => $apiKey,
                'Content-Type' => 'application/json'
            ])->post("{$host}/vectors/delete", [
                'ids' => [$id]
            ]);

            if ($response->successful()) {
                return true;
            }
            
            Log::error('Pinecone Delete Error: ' . $response->body());
            return false;
            
        } catch (\Exception $e) {
            Log::error('Pinecone Delete Service Error: ' . $e->getMessage());
            return false;
        }
    }
}