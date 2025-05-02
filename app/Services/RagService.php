<?php

namespace App\Services;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use OpenAI\Client as OpenAIClient;
use OpenAI\Factory;
use Illuminate\Support\Facades\Log;
use Exception;

class RagService
{
    private $openai;
    private $elasticsearch;
    private $indexName = 'llphant';
    private $originalQuery;

    public function __construct(OpenAIClient $openai, Client $elasticsearch, $indexName = null)
    {
        $this->openai = $openai;
        $this->elasticsearch = $elasticsearch;
        
        if ($indexName) {
            $this->indexName = $indexName;
        }

        $this->createIndexIfNotExists();
    }

    public function indexDocument($filePath, $progressCallback = null)
    {
        try {
            $startTime = microtime(true);
            $totalSteps = 100;
            $currentStep = 0;

            // Helper function to update progress
            $updateProgress = function($stage, $progress, $total = null) use ($progressCallback) {
                if ($progressCallback) {
                    $percentage = $total ? round(($progress / $total) * 100) : $progress;
                    call_user_func($progressCallback, [
                        'stage' => $stage,
                        'progress' => $percentage,
                        'current' => $progress,
                        'total' => $total,
                        'estimated_time' => null
                    ]);
                }
            };

            // Initial progress update
            $updateProgress('initializing', 0);

            if (!$this->elasticsearch) {
                throw new Exception("Elasticsearch is not configured");
            }

            Log::info("Starting to process file: {$filePath}");
            $filePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $filePath);
            
            if (!file_exists($filePath)) {
                throw new Exception("File not found: $filePath");
            }

            // Update progress for file reading stage (10%)
            $updateProgress('reading', 10);

            // Get file extension
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            
            // Extract text based on file type
            $extractStartTime = microtime(true);
            if ($extension === 'pdf') {
                // Use pdftotext if available
                $output = [];
                $returnVar = 0;
                exec("pdftotext \"{$filePath}\" -", $output, $returnVar);
                
                if ($returnVar !== 0) {
                    // Fallback to native PHP PDF parsing
                    $parser = new \Smalot\PdfParser\Parser();
                    $pdf = $parser->parseFile($filePath);
                    $content = $pdf->getText();
                } else {
                    $content = implode("\n", $output);
                }
            } else {
                // For text files
                $content = file_get_contents($filePath);
            }

            // Update progress for text extraction (20%)
            $updateProgress('extracting', 20);

            if (empty($content)) {
                throw new Exception("No valid text content could be extracted from the file");
            }

            $cleanStartTime = microtime(true);
            $content = $this->cleanText($content);
            
            // Update progress for text cleaning (30%)
            $updateProgress('cleaning', 30);

            $chunkStartTime = microtime(true);
            $chunks = $this->chunkText($content, 800);
            $totalChunks = count($chunks);

            // Update progress for chunking (40%)
            $updateProgress('chunking', 40, $totalChunks);

            if (empty($chunks)) {
                throw new Exception("No valid text chunks could be created from the file");
            }

            // Clean the filename
            $fileName = basename($filePath);
            if (preg_match('/^[a-f0-9]+_(.+)$/', $fileName, $matches)) {
                $fileName = $matches[1];
            }

            $batchSize = 10;
            $processedChunks = 0;
            $embeddingStartTime = microtime(true);

            // Calculate time per chunk after first batch for estimation
            $timePerChunk = null;

            // Process chunks in batches
            foreach (array_chunk($chunks, $batchSize) as $batchIndex => $batchChunks) {
                $batchStartTime = microtime(true);
                
                // Generate embeddings for the batch
                $batchEmbeddings = [];
                foreach ($batchChunks as $chunk) {
                    $batchEmbeddings[] = $this->generateEmbedding($chunk);
                }

                // Index the batch
                foreach ($batchChunks as $index => $chunk) {
                    try {
                        $this->elasticsearch->index([
                            'index' => $this->indexName,
                            'body' => [
                                'content' => $chunk,
                                'embedding' => $batchEmbeddings[$index],
                                'file_path' => $fileName,
                                'sourceName' => $fileName,
                                'chunk_index' => $processedChunks + $index
                            ]
                        ]);
                    } catch (\Exception $e) {
                        Log::error("Error processing chunk " . ($processedChunks + $index) . ": " . $e->getMessage());
                        throw new Exception("Error processing file content: " . $e->getMessage());
                    }
                }
                
                $processedChunks += count($batchChunks);
                $batchEndTime = microtime(true);
                $batchTime = $batchEndTime - $batchStartTime;

                // Calculate estimated time after first batch
                if ($batchIndex === 0) {
                    $timePerChunk = $batchTime / count($batchChunks);
                    $estimatedTotalTime = $timePerChunk * $totalChunks;
                }

                // Calculate progress (40-95%)
                $processingProgress = 40 + ($processedChunks / $totalChunks * 55);
                
                // Calculate estimated time remaining
                $estimatedTimeRemaining = null;
                if ($timePerChunk) {
                    $remainingChunks = $totalChunks - $processedChunks;
                    $estimatedTimeRemaining = round($timePerChunk * $remainingChunks);
                }

                // Update progress with processing information
                $updateProgress('processing', $processingProgress, $totalChunks);

                // Small delay to prevent overwhelming the API
                usleep(100000); // 100ms delay between batches
            }

            // Final progress update (100%)
            $updateProgress('completed', 100);

            $totalTime = round(microtime(true) - $startTime, 2);
            Log::info("Total processing time for {$fileName}: {$totalTime} seconds");

            return true;
        } catch (Exception $e) {
            Log::error('Error indexing document: ' . $e->getMessage());
            throw $e;
        }
    }

    public function answerQuestion($question)
    {
        try {
            if (empty($question)) {
                throw new Exception("Question cannot be empty");
            }

            $this->originalQuery = $question;
            $embedding = $this->generateEmbedding($question);
            $similarDocs = $this->searchSimilarDocuments($embedding);

            if (empty($similarDocs['hits']['hits'])) {
                return "I couldn't find any relevant information to answer your question.";
            }

            // Extract and format the context with source information
            $context = "";
            foreach ($similarDocs['hits']['hits'] as $hit) {
                $context .= "From {$hit['_source']['sourceName']} (chunk {$hit['_source']['chunk_index']}):\n";
                $context .= $hit['_source']['content'] . "\n\n";
            }

            Log::info('Constructed context for OpenAI:', ['context_length' => strlen($context)]);

            $response = $this->openai->chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system', 
                        'content' => 'You are a helpful assistant. Answer the question based on the provided context. If the context contains the information, provide a detailed answer. If the context does not contain relevant information, say so. Include the source document name if relevant.'
                    ],
                    [
                        'role' => 'user', 
                        'content' => "Context:\n{$context}\n\nQuestion: {$question}\n\nPlease answer the question based solely on the provided context. If the context doesn't contain relevant information, say so."
                    ]
                ],
                'temperature' => 0.7,
                'max_tokens' => 500
            ]);

            return $response->choices[0]->message->content;
        } catch (Exception $e) {
            Log::error('Error answering question: ' . $e->getMessage());
            throw $e;
        }
    }

    private function generateEmbedding($text)
    {
        try {
            if (empty($text)) {
                throw new Exception("Text cannot be empty");
            }

            $response = $this->openai->embeddings()->create([
                'model' => 'text-embedding-ada-002',
                'input' => $text
            ]);

            if (empty($response->embeddings[0]->embedding)) {
                throw new Exception("Failed to generate embedding");
            }

            return $response->embeddings[0]->embedding;
        } catch (Exception $e) {
            Log::error('Error generating embedding: ' . $e->getMessage());
            throw $e;
        }
    }

    private function searchSimilarDocuments($embedding)
    {
        try {
            if (empty($embedding)) {
                throw new Exception("Embedding cannot be empty");
            }

            $searchResult = $this->elasticsearch->search([
                'index' => $this->indexName,
                'body' => [
                    'size' => 10,  // Increased from 5 to 10 for better coverage
                    'query' => [
                        'bool' => [
                            'should' => [
                                [
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
                                [
                                    'match' => [
                                        'content' => [
                                            'query' => $this->getOriginalQuery(),
                                            'boost' => 0.3
                                        ]
                                    ]
                                ]
                            ],
                            'minimum_should_match' => 1
                        ]
                    ],
                    '_source' => ['content', 'file_path', 'sourceName', 'chunk_index'],
                    'track_scores' => true
                ]
            ]);

            // Log search results for debugging
            Log::info('Search query: ' . $this->getOriginalQuery());
            Log::info('Total hits: ' . $searchResult['hits']['total']['value']);
            
            foreach ($searchResult['hits']['hits'] as $hit) {
                Log::info('Document found:', [
                    'file' => $hit['_source']['sourceName'],
                    'chunk_index' => $hit['_source']['chunk_index'] ?? 'N/A',
                    'score' => $hit['_score'],
                    'content_preview' => substr($hit['_source']['content'], 0, 100) . '...'
                ]);
            }

            return $searchResult;
        } catch (Exception $e) {
            Log::error('Error searching similar documents: ' . $e->getMessage());
            throw $e;
        }
    }

    private function getOriginalQuery()
    {
        return $this->originalQuery ?? '';
    }

    private function createIndexIfNotExists()
    {
        try {
            $exists = $this->elasticsearch->indices()->exists(['index' => $this->indexName]);
            
            if (!$exists->asBool()) {
                $this->elasticsearch->indices()->create([
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
                                'file_path' => ['type' => 'keyword'],
                                'sourceName' => ['type' => 'keyword'],
                                'chunk_index' => ['type' => 'integer']
                            ]
                        ]
                    ]
                ]);
            }
        } catch (Exception $e) {
            Log::error('Error creating index: ' . $e->getMessage());
            throw $e;
        }
    }

    private function chunkText($text, $chunkSize = 1000)
    {
        $chunks = [];
        $sentences = preg_split('/(?<=[.!?])\s+/', $text);
        $currentChunk = '';
        
        foreach ($sentences as $sentence) {
            if (strlen($currentChunk) + strlen($sentence) + 1 > $chunkSize) {
                if (!empty($currentChunk)) {
                    $chunks[] = trim($currentChunk);
                }
                $currentChunk = $sentence;
            } else {
                $currentChunk .= ($currentChunk ? ' ' : '') . $sentence;
            }
        }
        
        if (!empty($currentChunk)) {
            $chunks[] = trim($currentChunk);
        }
        
        return $chunks;
    }

    private function cleanText($text)
    {
        // Normalize line endings
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        
        // Replace multiple newlines with a single newline
        $text = preg_replace('/\n\s*\n\s*\n+/', "\n\n", $text);
        
        // Replace multiple spaces with a single space
        $text = preg_replace('/[ \t]+/', ' ', $text);
        
        // Remove non-printable characters except newlines
        $text = preg_replace('/[\x00-\x08\x0B-\x1F\x7F-\xFF]/', '', $text);
        
        // Keep basic punctuation and special characters that are common in text
        $text = preg_replace('/[^\p{L}\p{N}\p{P}\p{Zs}\n]/u', ' ', $text);
        
        return trim($text);
    }

    public function listDocuments($indexName = null)
    {
        try {
            if (!$this->elasticsearch) {
                throw new Exception("Elasticsearch is not configured");
            }

            $indexName = $indexName ?? $this->indexName;

            return $this->elasticsearch->search([
                'index' => $indexName,
                'body' => [
                    'query' => [
                        'match_all' => new \stdClass()
                    ],
                    'size' => 100
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error listing documents: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getDocumentAggregations($indexName = null)
    {
        try {
            if (!$this->elasticsearch) {
                throw new Exception("Elasticsearch is not configured");
            }

            $indexName = $indexName ?? $this->indexName;

            return $this->elasticsearch->search([
                'index' => $indexName,
                'body' => [
                    'size' => 0,
                    'aggs' => [
                        'all_files' => [
                            'terms' => [
                                'field' => 'sourceName.keyword',
                                'size' => 1000
                            ]
                        ]
                    ]
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error getting document aggregations: ' . $e->getMessage());
            throw $e;
        }
    }

    public function deleteDocumentsByFileName($fileName)
    {
        try {
            if (!$this->elasticsearch) {
                throw new Exception("Elasticsearch is not configured");
            }

            $response = $this->elasticsearch->deleteByQuery([
                'index' => $this->indexName,
                'body' => [
                    'query' => [
                        'term' => [
                            'sourceName.keyword' => $fileName
                        ]
                    ]
                ]
            ]);

            return $response;
        } catch (Exception $e) {
            Log::error('Error deleting documents: ' . $e->getMessage());
            throw $e;
        }
    }

    public function listAllDocuments()
    {
        try {
            $result = $this->elasticsearch->search([
                'index' => $this->indexName,
                'body' => [
                    'size' => 1000,  // Get more documents
                    'query' => [
                        'match_all' => new \stdClass()
                    ],
                    'sort' => [
                        'sourceName.keyword' => 'asc',
                        'chunk_index' => 'asc'
                    ]
                ]
            ]);

            Log::info('Total documents in index: ' . $result['hits']['total']['value']);
            
            foreach ($result['hits']['hits'] as $hit) {
                Log::info('Document:', [
                    'file' => $hit['_source']['sourceName'],
                    'chunk_index' => $hit['_source']['chunk_index'] ?? 'N/A',
                    'content' => substr($hit['_source']['content'], 0, 200) . '...'
                ]);
            }

            return $result;
        } catch (Exception $e) {
            Log::error('Error listing all documents: ' . $e->getMessage());
            throw $e;
        }
    }
} 