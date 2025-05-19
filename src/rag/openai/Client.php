<?php

namespace Rag\OpenAI;

use OpenAI\Client as OpenAIClient;
use OpenAI\Factory;

class Client
{
    private OpenAIClient $client;

    public function __construct(string $apiKey)
    {
        $this->client = (new Factory())
            ->withApiKey($apiKey)
            ->make();
    }

    public function generateEmbedding(string $text): array
    {
        $response = $this->client->embeddings()->create([
            'model' => 'text-embedding-3-small',
            'input' => $text,
        ]);

        return $response->embeddings[0]->embedding;
    }

    public function generateAnswer(string $question, array $context): string
    {
        $prompt = $this->buildPrompt($question, $context);
        
        $response = $this->client->chat()->create([
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a helpful assistant that answers questions based on the provided context. If the answer cannot be found in the context, say "I cannot answer this question based on the provided context."'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.7,
            'max_tokens' => 500
        ]);

        return $response->choices[0]->message->content;
    }

    private function buildPrompt(string $question, array $context): string
    {
        $contextText = implode("\n\n", array_map(function($item) {
            return "Context: " . $item['text'];
        }, $context));

        // Add instruction for bullet points
        $instruction = "Please answer in clear, concise bullet points. Each point should be on a new line, and do not include introductory or summary sentences.";

        return "$instruction\n\nQuestion: {$question}\n\nContext:\n{$contextText}\n\nAnswer:";
    }
} 