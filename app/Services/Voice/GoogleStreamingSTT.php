<?php

namespace App\Services\Voice;

use Google\Cloud\Speech\V1\SpeechClient;
use Google\Cloud\Speech\V1\RecognitionConfig;
use Google\Cloud\Speech\V1\RecognitionConfig\AudioEncoding;
use Google\Cloud\Speech\V1\StreamingRecognitionConfig;

class GoogleStreamingSTT
{
    protected SpeechClient $speechClient;
    protected $stream;

    public function __construct()
    {
        $this->speechClient = new SpeechClient();
    }

    public function start()
    {
        $config = new RecognitionConfig([
            'encoding' => AudioEncoding::LINEAR16,
            'sample_rate_hertz' => 16000,
            'language_code' => 'en-IN',
            'enable_automatic_punctuation' => true,
        ]);

        $streamingConfig = new StreamingRecognitionConfig([
            'config' => $config,
            'interim_results' => true,
        ]);

        $this->stream = $this->speechClient->streamingRecognize();
        $this->stream->write([
            'streaming_config' => $streamingConfig
        ]);
    }

    public function writeAudio(string $audioChunk)
    {
        $this->stream->write([
            'audio_content' => $audioChunk
        ]);
    }

    public function readResponses(): array
    {
        $results = [];

        foreach ($this->stream->closeWriteAndReadAll() as $response) {
            foreach ($response->getResults() as $result) {
                $results[] = [
                    'text' => $result->getAlternatives()[0]->getTranscript(),
                    'is_final' => $result->getIsFinal()
                ];
            }
        }

        return $results;
    }

    public function close()
    {
        $this->speechClient->close();
    }
}