<?php

namespace Rag;

use Elastic\Elasticsearch\Client as ElasticClient;
use OpenAI\Client as OpenAIClient;
use OpenAI\Factory;

class RagService
{
    private OpenAIClient $openai;
    private ElasticClient $elasticsearch;
    private string $indexName;
    private const MAX_TOKENS = 8000; // Slightly below the limit to be safe

    public function __construct(
        string $openaiApiKey,
        ElasticClient $elasticsearch,
        string $indexName = 'llphant'
    ) {
        $this->openai = (new Factory())
            ->withApiKey($openaiApiKey)
            ->make();
        $this->elasticsearch = $elasticsearch;
        $this->indexName = $indexName;
        $this->createIndexIfNotExists();
    }

    private function readFileContent(string $filePath): string
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: $filePath");
        }
        return file_get_contents($filePath);
    }

    private function splitIntoChunks(string $content): array
    {
        // Split by paragraphs first
        $paragraphs = preg_split('/\n\s*\n/', $content);
        $chunks = [];
        $currentChunk = '';

        foreach ($paragraphs as $paragraph) {
            // If adding this paragraph would exceed the token limit
            if (strlen($currentChunk . $paragraph) > self::MAX_TOKENS) {
                if (!empty($currentChunk)) {
                    $chunks[] = $currentChunk;
                    $currentChunk = '';
                }
                // If a single paragraph is too long, split it by sentences
                if (strlen($paragraph) > self::MAX_TOKENS) {
                    $sentences = preg_split('/(?<=[.!?])\s+/', $paragraph);
                    foreach ($sentences as $sentence) {
                        if (strlen($currentChunk . $sentence) > self::MAX_TOKENS) {
                            if (!empty($currentChunk)) {
                                $chunks[] = $currentChunk;
                                $currentChunk = '';
                            }
                            // If a single sentence is too long, split it by words
                            if (strlen($sentence) > self::MAX_TOKENS) {
                                $words = explode(' ', $sentence);
                                $currentWordChunk = '';
                                foreach ($words as $word) {
                                    if (strlen($currentWordChunk . ' ' . $word) > self::MAX_TOKENS) {
                                        $chunks[] = $currentWordChunk;
                                        $currentWordChunk = $word;
                                    } else {
                                        $currentWordChunk .= ' ' . $word;
                                    }
                                }
                                if (!empty($currentWordChunk)) {
                                    $chunks[] = $currentWordChunk;
                                }
                            } else {
                                $chunks[] = $sentence;
                            }
                        } else {
                            $currentChunk .= ' ' . $sentence;
                        }
                    }
                } else {
                    $chunks[] = $paragraph;
                }
            } else {
                $currentChunk .= "\n\n" . $paragraph;
            }
        }

        if (!empty($currentChunk)) {
            $chunks[] = $currentChunk;
        }

        return $chunks;
    }

    public function indexDocument(string $content, string $documentId): void
    {
        // If content is a file path, read the file
        if (file_exists($content)) {
            $content = $this->readFileContent($content);
        }

        // Get the actual filename from documentId
        $filename = basename($documentId);

        // Split content into chunks
        $chunks = $this->splitIntoChunks($content);
        
        // Process each chunk
        foreach ($chunks as $index => $chunk) {
            $chunkId = $documentId . '_' . $index;
            
            // Generate embedding for the chunk
            $embedding = $this->generateEmbedding($chunk);

            // Create or update document in Elasticsearch
            $params = [
                'index' => $this->indexName,
                'id' => $chunkId,
                'body' => [
                    'content' => $chunk,
                    'embedding' => $embedding,
                    'document_id' => $documentId,
                    'chunk_index' => $index,
                    'sourceName' => $filename, // Store the actual filename
                    'timestamp' => time()
                ]
            ];

            try {
                $this->elasticsearch->index($params);
            } catch (\Exception $e) {
                throw new \Exception("Failed to index document chunk: " . $e->getMessage());
            }
        }
    }

    public function answerQuestion(string $question): string
    {
        // Generate embedding for the question
        $questionEmbedding = $this->generateEmbedding($question);

        // Search for similar documents
        $similarDocuments = $this->searchSimilarDocuments($questionEmbedding);

        // Generate answer using the similar documents
        return $this->generateAnswer($question, $similarDocuments);
    }

    private function generateEmbedding(string $text): array
    {
        try {
            $response = $this->openai->embeddings()->create([
                'model' => 'text-embedding-3-small',
                'input' => $text
            ]);

            return $response->embeddings[0]->embedding;
        } catch (\Exception $e) {
            throw new \Exception("Failed to generate embedding: " . $e->getMessage());
        }
    }

    private function searchSimilarDocuments(array $embedding, int $k = 10): array
    {
        try {
            $params = [
                'index' => $this->indexName,
                'body' => [
                    'query' => [
                        'script_score' => [
                            'query' => [
                                'match_all' => new \stdClass()
                            ],
                            'script' => [
                                'source' => 'cosineSimilarity(params.query_vector, "embedding") + 1.0',
                                'params' => ['query_vector' => $embedding]
                            ]
                        ]
                    ],
                    'size' => $k
                ]
            ];

            $response = $this->elasticsearch->search($params);
            return $response['hits']['hits'];
        } catch (\Exception $e) {
            throw new \Exception("Failed to search similar documents: " . $e->getMessage());
        }
    }

    private function generateAnswer(string $question, array $documents): string
    {
        try {
            $context = $this->formatContext($documents);
            
            $response = $this->openai->chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a helpful assistant that answers questions based on the provided context.'],
                    ['role' => 'user', 'content' => "Context: $context\n\nQuestion: $question"]
                ]
            ]);

            return $response->choices[0]->message->content;
        } catch (\Exception $e) {
            throw new \Exception("Failed to generate answer: " . $e->getMessage());
        }
    }

    private function formatContext(array $documents): string
    {
        $context = '';
        foreach ($documents as $doc) {
            $context .= $doc['_source']['content'] . "\n\n";
        }
        return trim($context);
    }

    private function createIndexIfNotExists(): void
    {
        try {
            if (!$this->elasticsearch->indices()->exists(['index' => $this->indexName])) {
                $params = [
                    'index' => $this->indexName,
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
                                'document_id' => ['type' => 'keyword'],
                                'chunk_index' => ['type' => 'integer'],
                                'sourceName' => [
                                    'type' => 'keyword',
                                    'ignore_above' => 256,
                                    'doc_values' => true
                                ],
                                'timestamp' => ['type' => 'date']
                            ]
                        ],
                        'settings' => [
                            'index' => [
                                'number_of_shards' => 1,
                                'number_of_replicas' => 0
                            ]
                        ]
                    ]
                ];
                $this->elasticsearch->indices()->create($params);
            }
        } catch (\Exception $e) {
            throw new \Exception("Failed to create index: " . $e->getMessage());
        }
    }
} 