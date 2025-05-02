<?php

namespace Rag\Elasticsearch;

use Elastic\Elasticsearch\Client as ElasticClient;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Exception\AuthenticationException;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;

class Client
{
    private ElasticClient $client;
    private string $index;

    public function __construct(string $url, string $apiKey, string $index = 'rag_documents')
    {
        $this->client = ClientBuilder::create()
            ->setHosts([$url])
            ->setApiKey($apiKey)
            ->build();
        $this->index = $index;
    }

    public function indexDocument(string $id, string $text, array $embedding): void
    {
        $params = [
            'index' => $this->index,
            'id' => $id,
            'body' => [
                'text' => $text,
                'embedding' => $embedding,
                'timestamp' => time()
            ]
        ];

        try {
            $this->client->index($params);
        } catch (ClientResponseException | ServerResponseException $e) {
            throw new \RuntimeException("Failed to index document: " . $e->getMessage());
        }
    }

    public function searchSimilarDocuments(array $embedding, int $limit = 5): array
    {
        $params = [
            'index' => $this->index,
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

        try {
            $response = $this->client->search($params);
            $hits = $response['hits']['hits'];
            
            return array_map(function($hit) {
                return [
                    'id' => $hit['_id'],
                    'text' => $hit['_source']['text'],
                    'score' => $hit['_score']
                ];
            }, $hits);
        } catch (ClientResponseException | ServerResponseException $e) {
            throw new \RuntimeException("Failed to search documents: " . $e->getMessage());
        }
    }

    public function createIndex(): void
    {
        $params = [
            'index' => $this->index,
            'body' => [
                'mappings' => [
                    'properties' => [
                        'text' => ['type' => 'text'],
                        'embedding' => [
                            'type' => 'dense_vector',
                            'dims' => 1536,
                            'index' => true,
                            'similarity' => 'cosine'
                        ],
                        'timestamp' => ['type' => 'date']
                    ]
                ]
            ]
        ];

        try {
            $this->client->indices()->create($params);
        } catch (ClientResponseException | ServerResponseException $e) {
            // Index might already exist, which is fine
            if (strpos($e->getMessage(), 'resource_already_exists_exception') === false) {
                throw new \RuntimeException("Failed to create index: " . $e->getMessage());
            }
        }
    }
} 