<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\OpenAIService;
use App\Services\ElasticsearchService;

class TestEmbedding extends Command
{
    protected $signature = 'test:embedding {text?}';
    protected $description = 'Test OpenAI embedding generation and Elasticsearch storage';

    public function handle(OpenAIService $openAI, ElasticsearchService $elasticsearch)
    {
        $this->info('Starting embedding test...');

        try {
            // Create index if it doesn't exist
            $this->info('Creating/Checking Elasticsearch index...');
            $elasticsearch->createIndex();

            // Get text from argument or use default
            $text = $this->argument('text') ?? "This is a test document for embedding generation and storage in Elasticsearch.";
            $this->info("Using text: " . $text);

            // Generate embedding
            $this->info('Generating embedding via OpenAI...');
            $embedding = $openAI->generateEmbedding($text);
            $this->info('Embedding generated! Vector size: ' . count($embedding));

            // Store in Elasticsearch
            $this->info('Storing in Elasticsearch...');
            $result = $elasticsearch->indexDocument(
                'test-cli-' . time(),
                1,
                $text,
                $embedding
            );

            $this->info('Success! Document stored in Elasticsearch');
            $this->table(
                ['Field', 'Value'],
                [
                    ['Index', $result['_index']],
                    ['Document ID', $result['_id']],
                    ['Result', $result['result']]
                ]
            );

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
} 