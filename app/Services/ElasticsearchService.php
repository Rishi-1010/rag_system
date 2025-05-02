<?php

namespace App\Services;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;

class ElasticsearchService
{
    private $client;
    private $index;

    public function __construct()
    {
        $this->index = config('services.elasticsearch.index', 'documents');
        
        $builder = ClientBuilder::create()
            ->setHosts([config('services.elasticsearch.host', 'http://localhost:9200')]);
            
        if ($apiKey = config('services.elasticsearch.api_key')) {
            $builder->setApiKey($apiKey);
        }
        
        $this->client = $builder->build();
    }

    public function createIndex()
    {
        $params = [
            'index' => $this->index,
            'body' => [
                'mappings' => [
                    'properties' => [
                        'content' => [
                            'type' => 'text'
                        ],
                        'embedding' => [
                            'type' => 'dense_vector',
                            'dims' => 1536, // OpenAI embedding dimension
                            'index' => true,
                            'similarity' => 'cosine'
                        ],
                        'file_id' => [
                            'type' => 'keyword'
                        ],
                        'chunk_index' => [
                            'type' => 'integer'
                        ]
                    ]
                ]
            ]
        ];

        try {
            $this->client->indices()->create($params);
            return true;
        } catch (\Exception $e) {
            // Index might already exist
            return false;
        }
    }

    public function indexDocument($fileId, $chunkIndex, $content, $embedding)
    {
        $params = [
            'index' => $this->index,
            'body' => [
                'content' => $content,
                'embedding' => $embedding,
                'file_id' => $fileId,
                'chunk_index' => $chunkIndex
            ]
        ];

        return $this->client->index($params);
    }

    public function searchSimilar($embedding, $limit = 5)
    {
        $params = [
            'index' => $this->index,
            'body' => [
                'query' => [
                    'script_score' => [
                        'query' => ['match_all' => new \stdClass()],
                        'script' => [
                            'source' => "cosineSimilarity(params.query_vector, 'embedding') + 1.0",
                            'params' => [
                                'query_vector' => $embedding
                            ]
                        ]
                    ]
                ],
                'size' => $limit
            ]
        ];

        return $this->client->search($params);
    }
} 