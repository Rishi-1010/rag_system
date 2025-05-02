<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RagService;

class ProcessDocument extends Command
{
    protected $signature = 'rag:process {file : The file to process}';
    protected $description = 'Process and index a document in Elasticsearch';

    private $ragService;

    public function __construct(RagService $ragService)
    {
        parent::__construct();
        $this->ragService = $ragService;
    }

    public function handle()
    {
        try {
            $file = $this->argument('file');
            
            $this->info("Processing file: $file");
            
            $this->ragService->indexDocument($file);
            
            $this->info("Successfully processed file: $file");
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
        }
    }
} 