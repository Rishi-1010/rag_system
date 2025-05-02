<?php

namespace App\Rag;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use OpenAI\Client as OpenAIClient;

class RagService
{
    private string $openaiApiKey;
    private Client $elasticsearch;
    private OpenAIClient $openai;
    private const INDEX_NAME = 'llphant';
    private const CHUNK_SIZE = 1000;

    public function __construct(string $openaiApiKey, Client $elasticsearch)
    {
        $this->openaiApiKey = $openaiApiKey;
        $this->elasticsearch = $elasticsearch;
        $this->openai = \OpenAI::client($openaiApiKey);
        $this->createIndexIfNotExists();
    }

    public function indexDocument(string $content, string $documentId): void
    {
        // Check if content is a file path
        if (file_exists($content)) {
            $content = file_get_contents($content);
        }

        // Clean and chunk the content
        $chunks = $this->chunkText($content);
        $filename = basename($documentId);

        foreach ($chunks as $index => $chunk) {
            $embedding = $this->generateEmbedding($chunk);
            
            $params = [
                'index' => self::INDEX_NAME,
                'body' => [
                    'content' => $chunk,
                    'embedding' => $embedding,
                    'documentId' => $documentId,
                    'sourceName' => $filename,
                    'chunkIndex' => $index
                ]
            ];

            try {
                $this->elasticsearch->index($params);
            } catch (ClientResponseException | ServerResponseException $e) {
                throw new \Exception("Failed to index document: " . $e->getMessage());
            }
        }
    }

    public function answerQuestion(string $question): string
    {
        $questionEmbedding = $this->generateEmbedding($question);
        $similarDocs = $this->searchSimilarDocuments($questionEmbedding);
        
        $context = implode("\n", array_map(fn($doc) => $doc['content'], $similarDocs));
        
        $prompt = "Context:\n$context\n\nQuestion: $question\n\nAnswer:";
        
        try {
            $response = $this->openai->chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a helpful assistant that answers questions based on the provided context.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.7,
                'max_tokens' => 500
            ]);
            
            return $response->choices[0]->message->content;
        } catch (\Exception $e) {
            throw new \Exception("Failed to generate answer: " . $e->getMessage());
        }
    }

    private function generateEmbedding(string $text): array
    {
        try {
            $response = $this->openai->embeddings()->create([
                'model' => 'text-embedding-ada-002',
                'input' => $text
            ]);
            
            return $response->embeddings[0]->embedding;
        } catch (\Exception $e) {
            throw new \Exception("Failed to generate embedding: " . $e->getMessage());
        }
    }

    private function searchSimilarDocuments(array $embedding, int $limit = 5): array
    {
        try {
            $response = $this->elasticsearch->search([
                'index' => self::INDEX_NAME,
                'body' => [
                    'query' => [
                        'script_score' => [
                            'query' => ['match_all' => new \stdClass()],
                            'script' => [
                                'source' => 'cosineSimilarity(params.query_vector, "embedding") + 1.0',
                                'params' => ['query_vector' => $embedding]
                            ]
                        ]
                    ],
                    'size' => $limit
                ]
            ]);
            
            return array_map(fn($hit) => $hit['_source'], $response['hits']['hits']);
        } catch (ClientResponseException | ServerResponseException $e) {
            throw new \Exception("Failed to search documents: " . $e->getMessage());
        }
    }

    private function createIndexIfNotExists(): void
    {
        try {
            if (!$this->elasticsearch->indices()->exists(['index' => self::INDEX_NAME])->asBool()) {
                $this->elasticsearch->indices()->create([
                    'index' => self::INDEX_NAME,
                    'body' => [
                        'mappings' => [
                            'properties' => [
                                'content' => ['type' => 'text'],
                                'embedding' => [
                                    'type' => 'dense_vector',
                                    'dims' => 1536,
                                    'index' => true,
                                    'similarity' => 'cosine'
                                ],
                                'documentId' => ['type' => 'keyword'],
                                'sourceName' => ['type' => 'keyword'],
                                'chunkIndex' => ['type' => 'integer']
                            ]
                        ]
                    ]
                ]);
            }
        } catch (ClientResponseException | ServerResponseException $e) {
            throw new \Exception("Failed to create index: " . $e->getMessage());
        }
    }

    private function chunkText(string $text): array
    {
        $text = $this->cleanText($text);
        $words = preg_split('/\s+/', $text);
        $chunks = [];
        $currentChunk = [];
        $currentLength = 0;

        foreach ($words as $word) {
            if ($currentLength + strlen($word) > self::CHUNK_SIZE) {
                $chunks[] = implode(' ', $currentChunk);
                $currentChunk = [];
                $currentLength = 0;
            }
            $currentChunk[] = $word;
            $currentLength += strlen($word) + 1;
        }

        if (!empty($currentChunk)) {
            $chunks[] = implode(' ', $currentChunk);
        }

        return $chunks;
    }

    private function cleanText(string $text): string
    {
        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        // Remove special characters
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);
        return trim($text);
    }
} 