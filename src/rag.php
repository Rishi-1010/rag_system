<?php

require_once __DIR__ . '/vendor/autoload.php';

use Elastic\Elasticsearch\ClientBuilder;
use Rag\RagService;

// Load environment variables from .env file if it exists
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Get environment variables
$openaiApiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY');
$elasticsearchUrl = $_ENV['ELASTIC_URL'] ?? getenv('ELASTIC_URL') ?? 'http://localhost:9200';
$elasticsearchApiKey = $_ENV['ELASTIC_API_KEY'] ?? getenv('ELASTIC_API_KEY');

if (!$openaiApiKey) {
    die("Error: OPENAI_API_KEY environment variable is not set\n");
}

if (!$elasticsearchApiKey) {
    die("Error: ELASTIC_API_KEY environment variable is not set\n");
}

// Initialize Elasticsearch client
$elasticsearch = ClientBuilder::create()
    ->setHosts([$elasticsearchUrl])
    ->setApiKey($elasticsearchApiKey)
    ->build();

// Create RAG service
$ragService = new RagService($openaiApiKey, $elasticsearch);

// Handle command line arguments
if ($argc < 2) {
    echo "Usage: php rag.php <command> [arguments]\n";
    echo "Commands:\n";
    echo "  index <file_path> <document_id> - Index a document\n";
    echo "  ask <question> - Ask a question\n";
    exit(1);
}

$command = $argv[1];

switch ($command) {
    case 'index':
        if ($argc < 4) {
            echo "Usage: php rag.php index <file_path> <document_id>\n";
            exit(1);
        }
        $filePath = str_replace('\\\\', '\\', $argv[2]);
        $documentId = $argv[3];
        
        if (!file_exists($filePath)) {
            echo "Error: File not found: $filePath\n";
            exit(1);
        }
        
        try {
            $ragService->indexDocument($filePath, $documentId);
            echo "Document indexed successfully!\n";
        } catch (Exception $e) {
            echo "Error indexing document: " . $e->getMessage() . "\n";
            exit(1);
        }
        break;

    case 'ask':
        if ($argc < 3) {
            echo "Usage: php rag.php ask <question>\n";
            exit(1);
        }
        $question = $argv[2];
        echo "Question: $question\n";
        $answer = $ragService->answerQuestion($question);
        echo "Answer: $answer\n";
        break;

    default:
        echo "Unknown command: $command\n";
        exit(1);
} 