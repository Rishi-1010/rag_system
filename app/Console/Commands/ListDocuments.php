<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RagService;

class ListDocuments extends Command
{
    protected $signature = 'rag:list {index? : The index name to query} {--aggs : Show aggregations by file name}';
    protected $description = 'List all documents stored in Elasticsearch';

    private $ragService;

    public function __construct(RagService $ragService)
    {
        parent::__construct();
        $this->ragService = $ragService;
    }

    public function handle()
    {
        try {
            $indexName = $this->argument('index') ?? 'documents';
            $showAggs = $this->option('aggs');

            if ($showAggs) {
                $results = $this->ragService->getDocumentAggregations($indexName);
                
                if (empty($results['aggregations']['all_files']['buckets'])) {
                    $this->info("No documents found in index: $indexName");
                    return;
                }

                $this->info("\nDocument counts by file in index '$indexName':\n");
                foreach ($results['aggregations']['all_files']['buckets'] as $bucket) {
                    $this->info(sprintf("%-40s : %d chunks", $bucket['key'], $bucket['doc_count']));
                }
            } else {
                $results = $this->ragService->listDocuments($indexName);
                
                if (empty($results['hits']['hits'])) {
                    $this->info("No documents found in index: $indexName");
                    return;
                }

                $this->info("\nFound " . $results['hits']['total']['value'] . " documents in index '$indexName':\n");

                foreach ($results['hits']['hits'] as $hit) {
                    $this->info("Document ID: " . $hit['_id']);
                    $this->info("File: " . $hit['_source']['file_path']);
                    $this->info("Chunk Index: " . $hit['_source']['chunk_index']);
                    $this->info("Content Preview: " . substr($hit['_source']['content'], 0, 100) . "...");
                    $this->info("Embedding Dimensions: " . count($hit['_source']['embedding']));
                    $this->info(str_repeat('-', 80) . "\n");
                }
            }
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
        }
    }
} 