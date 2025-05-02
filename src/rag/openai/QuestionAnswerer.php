<?php

namespace Rag\OpenAI;

use OpenAI\Client as OpenAIClient;
use OpenAI\Factory;
use Elastic\Elasticsearch\ClientBuilder;

class QuestionAnswerer
{
    private OpenAIClient $openai;
    private array $retrievedDocs = [];
    private string $currentQuestion = '';

    public function __construct(
        string $openaiApiKey,
        string $elasticUrl,
        string $elasticApiKey,
        string $indexName = 'llphant'
    ) {
        // Initialize OpenAI client
        $this->openai = (new Factory())
            ->withApiKey($openaiApiKey)
            ->make();

        // Initialize Elasticsearch
        $this->es = (new ClientBuilder())::create()
            ->setHosts([$elasticUrl])
            ->setApiKey($elasticApiKey)
            ->build();
        $this->indexName = $indexName;
    }

    public function answerQuestion(string $question): string
    {
        $this->currentQuestion = $question;
        
        // Generate embedding for the question
        $questionEmbedding = $this->generateEmbedding($question);
        
        // Find similar documents
        $this->retrievedDocs = $this->searchSimilarDocuments($questionEmbedding);
        
        if (empty($this->retrievedDocs)) {
            return "I couldn't find any relevant information to answer your question.";
        }
        
        // Generate answer using the context
        return $this->generateAnswer($question, $this->retrievedDocs);
    }

    private function generateEmbedding(string $text): array
    {
        $response = $this->openai->embeddings()->create([
            'model' => 'text-embedding-3-small',
            'input' => $text,
        ]);

        return $response->embeddings[0]->embedding;
    }

    private function searchSimilarDocuments(array $embedding, int $limit = 5): array
    {
        $params = [
            'index' => $this->indexName,
            'body' => [
                'query' => [
                    'script_score' => [
                        'query' => ['match_all' => new \stdClass()],
                        'script' => [
                            'source' => "cosineSimilarity(params.query_vector, 'embedding') + 1.0",
                            'params' => ['query_vector' => $embedding]
                        ]
                    ]
                ],
                'size' => $limit
            ]
        ];

        $response = $this->es->search($params);
        $hits = $response['hits']['hits'];
        
        return array_map(function($hit) {
            $doc = new \stdClass();
            $doc->content = $hit['_source']['text'];
            $doc->sourceName = $hit['_source']['source'];
            $doc->score = $hit['_score'];
            return $doc;
        }, $hits);
    }

    private function generateAnswer(string $question, array $context): string
    {
        $contextText = implode("\n\n", array_map(function($doc) {
            return $doc->content;
        }, $context));

        $response = $this->openai->chat()->create([
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a helpful assistant that answers questions based on the provided context. If the answer cannot be found in the context, say "I cannot answer this question based on the provided context."'
                ],
                [
                    'role' => 'user',
                    'content' => "Context:\n$contextText\n\nQuestion: $question"
                ]
            ],
            'temperature' => 0.7,
            'max_tokens' => 500
        ]);

        return $response->choices[0]->message->content;
    }

    public function getRetrievedDocuments(): array
    {
        return $this->retrievedDocs;
    }

    public function hasRelevantDocuments(): bool
    {
        foreach ($this->retrievedDocs as $doc) {
            $score = $doc->score ?? 0;
            if ($score === 0) {
                // Calculate a simple similarity based on word overlap
                $questionWords = str_word_count(strtolower($this->currentQuestion), 1);
                $contentWords = str_word_count(strtolower($doc->content), 1);
                $commonWords = array_intersect($questionWords, $contentWords);
                
                if (count($questionWords) > 0) {
                    $score = count($commonWords) / count($questionWords);
                }
            }
            
            if ($score > 0.3) {
                return true;
            }
        }
        
        return false;
    }
} 