<?php
/**
 * Embedding with OpenAI
 */
ini_set('memory_limit', '2G'); // Increase memory limit to 2GB
require dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';

// Debug information
echo "Current directory: " . __DIR__ . "\n";
$rootDir = dirname(dirname(dirname(__DIR__)));
echo "Root directory: " . $rootDir . "\n";

// Set PDF path (default single file)
$defaultPdfPath = $rootDir . '/data/presaleqna';

// Function to resolve file path
function resolveFilePath($path, $rootDir) {
    // If path is already absolute, return it
    if (strpos($path, '/') === 0 || strpos($path, ':\\') === 1) {
        return $path;
    }
    
    // If path starts with 'src/', remove it as we're already in src directory
    if (strpos($path, 'src/') === 0 || strpos($path, 'src\\') === 0) {
        $path = substr($path, 4);
    }
    
    // Try to resolve relative to root directory
    $resolvedPath = $rootDir . '/' . $path;
    if (file_exists($resolvedPath)) {
        return $resolvedPath;
    }
    
    // Try to resolve relative to current directory
    $resolvedPath = __DIR__ . '/' . $path;
    if (file_exists($resolvedPath)) {
        return $resolvedPath;
    }
    
    return $path; // Return original path if no resolution worked
}

// Check for command-line argument
if (php_sapi_name() === 'cli' && isset($argv[1])) {
    $pdfPath = resolveFilePath($argv[1], $rootDir);
    echo "Using file from command-line argument: $pdfPath\n";
} else {
    $pdfPath = $defaultPdfPath;
    echo "Using default file path: $pdfPath\n";
}

// Try to find the file with or without extension
$possibleExtensions = ['', '.txt', '.pdf', '.json', '.md', '.doc', '.docx'];
$foundFile = false;

foreach ($possibleExtensions as $ext) {
    $testPath = $pdfPath . $ext;
    if (file_exists($testPath)) {
        $pdfPath = $testPath;
        $foundFile = true;
        echo "Found file with extension: $pdfPath\n";
        break;
    }
}

if (!$foundFile) {
    echo "Error: Could not find file. Tried the following paths:\n";
    foreach ($possibleExtensions as $ext) {
        echo "- " . $pdfPath . $ext . "\n";
    }
    die("\nPlease provide a valid file path.\n");
}

echo "File exists: " . (file_exists($pdfPath) ? "Yes" : "No") . "\n\n";

// Try to load .env file directly
$envFile = $rootDir . '/config/.env';
echo "Looking for .env file at: " . $envFile . "\n";
echo "File exists: " . (file_exists($envFile) ? "Yes" : "No") . "\n";

if (file_exists($envFile)) {
    echo "\n.env file contents:\n";
    // echo file_get_contents($envFile) . "\n";
} else {
    die("Error: .env file not found at " . $envFile . "\n");
}

// Load environment variables
try {
    $dotenv = Dotenv\Dotenv::createImmutable($rootDir . '/config');
    $dotenv->load();
    echo "\nEnvironment variables loaded successfully\n";
    
    // Debug: Print all environment variables
    // echo "\nCurrent environment variables:\n";
    // echo "OPENAI_API_KEY: " . (isset($_ENV['OPENAI_API_KEY']) ? 'Set' : 'Not Set') . "\n";
    // echo "ELASTIC_URL: " . (isset($_ENV['ELASTIC_URL']) ? 'Set' : 'Not Set') . "\n";
    // echo "ELASTIC_API_KEY: " . (isset($_ENV['ELASTIC_API_KEY']) ? 'Set' : 'Not Set') . "\n";

    // Also check $_SERVER
    // echo "\nServer environment variables:\n";
    // echo "OPENAI_API_KEY: " . (isset($_SERVER['OPENAI_API_KEY']) ? 'Set' : 'Not Set') . "\n";
    // echo "ELASTIC_URL: " . (isset($_SERVER['ELASTIC_URL']) ? 'Set' : 'Not Set') . "\n";
    // echo "ELASTIC_API_KEY: " . (isset($_SERVER['ELASTIC_API_KEY']) ? 'Set' : 'Not Set') . "\n";
} catch (\Exception $e) {
    die("Error loading .env file: " . $e->getMessage() . "\n");
}

// Verify environment variables
// if (!isset($_ENV['OPENAI_API_KEY']) && !isset($_SERVER['OPENAI_API_KEY'])) {
//     die("Error: OPENAI_API_KEY environment variable is not set in config/.env\n");
// }
// if (!isset($_ENV['ELASTIC_URL']) && !isset($_SERVER['ELASTIC_URL'])) {
//     die("Error: ELASTIC_URL environment variable is not set in config/.env\n");
// }
// if (!isset($_ENV['ELASTIC_API_KEY']) && !isset($_SERVER['ELASTIC_API_KEY'])) {
//     die("Error: ELASTIC_API_KEY environment variable is not set in config/.env\n");
// }

// Set the variables for use in the rest of the code
$openaiApiKey = $_ENV['OPENAI_API_KEY'] ?? $_SERVER['OPENAI_API_KEY'];
$elasticUrl = $_ENV['ELASTIC_URL'] ?? $_SERVER['ELASTIC_URL'];
$elasticApiKey = $_ENV['ELASTIC_API_KEY'] ?? $_SERVER['ELASTIC_API_KEY'];

use Elastic\Elasticsearch\ClientBuilder;
use LLPhant\Embeddings\DataReader\FileDataReader;
use LLPhant\Embeddings\DocumentSplitter\DocumentSplitter;
use LLPhant\Embeddings\EmbeddingGenerator\OpenAI\OpenAI3SmallEmbeddingGenerator;
use LLPhant\Embeddings\VectorStores\Elasticsearch\ElasticsearchVectorStore;
use LLPhant\OpenAIConfig;
use LLPhant\Embeddings\Document;

// Elasticsearch setup (moved up for reuse)
$es = (new ClientBuilder())::create()
    ->setHosts([$elasticUrl])
    ->setApiKey($elasticApiKey)
    ->build();
$elasticVectorStore = new ElasticsearchVectorStore($es);
$indexName = 'llphant';

// Always resolve to absolute path for comparison and storage
$absolutePath = realpath($pdfPath);
if ($absolutePath === false) {
    die("Error: Could not resolve absolute path for $pdfPath\n");
}

// Detect file type
$ext = strtolower(pathinfo($pdfPath, PATHINFO_EXTENSION));
$documents = [];

// For text files (including files without extension), handle them directly
if (empty($ext) || $ext === 'txt') {
    printf("- Reading text content from file\n");
    $content = file_get_contents($pdfPath);
    if ($content === false) {
        die("Error: Could not read file: $pdfPath\n");
    }
    
    // Split content into Q&A pairs
    $qaPairs = [];
    $lines = explode("\n", $content);
    $currentQ = '';
    $currentA = '';
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        if (strpos($line, 'Q:') === 0) {
            // If we have a previous Q&A pair, save it
            if (!empty($currentQ) && !empty($currentA)) {
                $qaPairs[] = [
                    'question' => $currentQ,
                    'answer' => $currentA
                ];
            }
            $currentQ = $line;
            $currentA = '';
        } elseif (strpos($line, 'A:') === 0) {
            $currentA = $line;
        } elseif (!empty($currentA)) {
            // Append to current answer if we're in an answer
            $currentA .= "\n" . $line;
        }
    }
    
    // Add the last Q&A pair if exists
    if (!empty($currentQ) && !empty($currentA)) {
        $qaPairs[] = [
            'question' => $currentQ,
            'answer' => $currentA
        ];
    }
    
    printf("Found %d Q&A pairs\n", count($qaPairs));
    
    // Create documents for each Q&A pair
    foreach ($qaPairs as $index => $pair) {
        $doc = new Document();
        $doc->content = $pair['question'] . "\n" . $pair['answer'];
        $doc->sourceName = $absolutePath;
        $doc->chunkNumber = $index + 1;
        
        // Debug: Print document fields
        printf("Creating document %d:\n", $index + 1);
        printf("  Content: %s\n", substr($doc->content, 0, 100) . "...");
        printf("  SourceName: %s\n", $doc->sourceName);
        printf("  ChunkNumber: %d\n", $doc->chunkNumber);
        
        $documents[] = $doc;
    }
    
    printf("Created %d documents from Q&A pairs\n", count($documents));
    
    // Debug: Print first document's fields before embedding
    if (!empty($documents)) {
        printf("\nFirst document before embedding:\n");
        printf("  Content: %s\n", substr($documents[0]->content, 0, 100) . "...");
        printf("  SourceName: %s\n", $documents[0]->sourceName);
        printf("  ChunkNumber: %d\n", $documents[0]->chunkNumber);
    }
} elseif ($ext === 'pdf') {
    // Existing PDF logic
    printf("- Reading the PDF file\n");
    $reader = new FileDataReader($pdfPath);
    $documents = $reader->getDocuments();
    printf("Number of documents: %d\n", count($documents));
    if (count($documents) > 0) {
        foreach ($documents as $doc) {
            $doc->sourceName = $absolutePath;
        }
        printf("First document content length: %d\n", strlen($documents[0]->content));
        printf("First document source: %s\n", $documents[0]->sourceName);
    }
} elseif ($ext === 'json') {
    // Handle JSON files
    printf("- Reading the JSON file\n");
    $jsonData = json_decode(file_get_contents($pdfPath), true);
    if (!is_array($jsonData)) {
        die("Error: JSON file does not contain an array at the top level.\n");
    }
    $chunkCount = 0;
    foreach ($jsonData as $item) {
        if (isset($item['message']) && !empty($item['message'])) {
            $doc = new Document();
            $doc->content = $item['message'];
            $doc->sourceName = $absolutePath;
            $documents[] = $doc;
            $chunkCount++;
        }
        if (isset($item['responses']) && is_array($item['responses'])) {
            foreach ($item['responses'] as $response) {
                if (isset($response['message']) && !empty($response['message'])) {
                    $doc = new Document();
                    $doc->content = $response['message'];
                    $doc->sourceName = $absolutePath;
                    $documents[] = $doc;
                    $chunkCount++;
                }
            }
        }
    }
    printf("Number of message chunks extracted from JSON: %d\n", $chunkCount);
    if ($chunkCount > 0) {
        printf("First chunk content length: %d\n", strlen($documents[0]->content));
        printf("First chunk source: %s\n", $documents[0]->sourceName);
    }
} else {
    die("Unsupported file type: $ext\nSupported types are: TXT, PDF, JSON\n");
}

if (empty($documents)) {
    die("Error: No content was extracted from the file.\n");
}

// Document split
printf("- Document split\n");
$splitDocuments = DocumentSplitter::splitDocuments($documents, 1000);
printf("Number of split documents (chunks): %d\n", count($splitDocuments));

// Before embedding, check if file is already embedded in Elasticsearch
function isFileEmbeddedInElasticsearch($es, $indexName, $fileSource) {
    try {
        $params = [
            'index' => $indexName,
            'body'  => [
                'query' => [
                    'term' => [
                        'source.keyword' => $fileSource
                    ]
                ],
                'size' => 1
            ]
        ];
        $response = $es->search($params);
        if (isset($response['hits']['total']['value']) && $response['hits']['total']['value'] > 0) {
            return true;
        }
    } catch (Exception $e) {
        echo "[Warning] Could not check Elasticsearch for file $fileSource: " . $e->getMessage() . "\n";
    }
    return false;
}

// In your file processing logic, before embedding:
// Example for single file mode:
if (isFileEmbeddedInElasticsearch($es, $indexName, $absolutePath)) {
    echo "[Info] File $absolutePath already embedded in Elasticsearch. Skipping.\n";
    exit(0);
}

# Embedding
printf("- Generating embeddings\n");
$config = new OpenAIConfig();
$config->apiKey = $openaiApiKey;
$embeddingGenerator = new OpenAI3SmallEmbeddingGenerator($config);
$embeddedDocuments = $embeddingGenerator->embedDocuments($splitDocuments);

# Save all embeddings to JSON file
printf("- Saving all embeddings to embeddings.json\n");
$embeddingsData = [];
foreach ($embeddedDocuments as $index => $doc) {
    $embeddingsData[] = [
        'chunk_number' => $index + 1,
        'content' => $doc->content,
        'source' => $doc->sourceName,
        'embedding_vector' => $doc->embedding,
        'vector_length' => count($doc->embedding)
    ];
}

$jsonFile = $rootDir . '/data/embeddings.json';
file_put_contents($jsonFile, json_encode($embeddingsData, JSON_PRETTY_PRINT));
printf("All embeddings saved to: %s\n", $jsonFile);

# Elasticsearch
printf("- Index all the embeddings to Elasticsearch\n");
$elasticVectorStore->addDocuments($embeddedDocuments);

printf("Added %d documents in Elasticsearch with embedding included\n", count($embeddedDocuments));