<?php

namespace App\Services;

use OpenAI\Client;
use OpenAI\Factory;

class OpenAIService
{
    private $client;
    
    public function __construct()
    {
        $this->client = (new Factory)->withApiKey(config('services.openai.api_key'))->make();
    }
    
    public function generateEmbedding($text)
    {
        $response = $this->client->embeddings()->create([
            'model' => 'text-embedding-ada-002',
            'input' => $text
        ]);
        
        return $response->embeddings[0]->embedding;
    }
    
    public function generateEmbeddingBatch($texts, $batchSize = 10)
    {
        $embeddings = [];
        $batches = array_chunk($texts, $batchSize);
        
        foreach ($batches as $batch) {
            $response = $this->client->embeddings()->create([
                'model' => 'text-embedding-ada-002',
                'input' => $batch
            ]);
            
            foreach ($response->embeddings as $embedding) {
                $embeddings[] = $embedding->embedding;
            }
        }
        
        return $embeddings;
    }
} 