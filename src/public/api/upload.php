<?php
// Use the Rag namespace
use Rag\RagService;
use Elastic\Elasticsearch\ClientBuilder;
use Smalot\PdfParser\Parser;

// Disable error display and enable error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Set error log file
ini_set('error_log', __DIR__ . '/../../error.log');

// Set JSON header
header('Content-Type: application/json');

// Function to send JSON response
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

// Function to clean text
function cleanText($text) {
    // Remove non-printable characters
    $text = preg_replace('/[\x00-\x1F\x7F-\xFF]/', ' ', $text);
    // Replace multiple spaces with single space
    $text = preg_replace('/\s+/', ' ', $text);
    // Trim whitespace
    return trim($text);
}

try {
    // Set the base directory
    $baseDir = realpath(__DIR__ . '/../..');
    
    // Include necessary files
    require_once $baseDir . '/vendor/autoload.php';
    require_once $baseDir . '/rag/RagService.php';

    // Load environment variables from .env file
    $envFile = $baseDir . '/.env';
    if (!file_exists($envFile)) {
        throw new Exception('Environment file (.env) not found at: ' . $envFile);
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }

    // Check if file was uploaded
    if (!isset($_FILES['file'])) {
        throw new Exception('No file uploaded');
    }

    $file = $_FILES['file'];
    $documentId = $file['name']; // Use the actual filename as document ID

    // Log file information
    error_log("Processing file: " . $file['name']);
    error_log("File type: " . $file['type']);
    error_log("File size: " . $file['size']);

    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload failed with error code: ' . $file['error']);
    }

    // Validate file type
    $allowedTypes = ['application/pdf', 'text/plain'];
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Invalid file type. Only PDF and TXT files are allowed. Got: ' . $file['type']);
    }

    // Initialize RAG service
    $openaiApiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY');
    $elasticsearchUrl = $_ENV['ELASTIC_URL'] ?? getenv('ELASTIC_URL') ?? 'http://localhost:9200';
    $elasticsearchApiKey = $_ENV['ELASTIC_API_KEY'] ?? getenv('ELASTIC_API_KEY');

    if (!$openaiApiKey || !$elasticsearchApiKey) {
        throw new Exception('API keys not configured. Please check your .env file in the src directory.');
    }

    // Log API configuration
    error_log("OpenAI API Key configured: " . (bool)$openaiApiKey);
    error_log("Elasticsearch URL: " . $elasticsearchUrl);
    error_log("Elasticsearch API Key configured: " . (bool)$elasticsearchApiKey);

    // Initialize Elasticsearch client
    $elasticsearch = ClientBuilder::create()
        ->setHosts([$elasticsearchUrl])
        ->setApiKey($elasticsearchApiKey)
        ->build();

    // Initialize RAG service
    $ragService = new RagService($openaiApiKey, $elasticsearch);

    // Read file content
    $content = '';
    if ($file['type'] === 'application/pdf') {
        // For PDF files, use PDF Parser to extract text
        $parser = new Parser();
        $pdf = $parser->parseFile($file['tmp_name']);
        $content = $pdf->getText();
        
        // Clean the extracted text
        $content = cleanText($content);
        
        if (empty($content)) {
            throw new Exception('Failed to extract text from PDF file');
        }
    } else {
        // For text files, read directly and clean
        $content = file_get_contents($file['tmp_name']);
        if ($content === false) {
            throw new Exception('Failed to read text file content');
        }
        $content = cleanText($content);
    }

    // Process the document
    $ragService->indexDocument($content, $documentId);

    // Return success response
    sendJsonResponse([
        'success' => true,
        'message' => 'Document processed successfully',
        'document_id' => $documentId
    ]);

} catch (Exception $e) {
    error_log("Error in upload.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    sendJsonResponse([
        'success' => false,
        'error' => 'Failed to process document',
        'message' => $e->getMessage()
    ], 500);
} 