<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Smalot\PdfParser\Parser as PdfParser;

function extractTextFromFile($filePath) {
    $mimeType = mime_content_type($filePath);
    
    switch ($mimeType) {
        case 'application/pdf':
            $parser = new PdfParser();
            $pdf = $parser->parseFile($filePath);
            return $pdf->getText();
        
        case 'text/plain':
            return file_get_contents($filePath);
        
        default:
            throw new Exception('Unsupported file type: ' . $mimeType);
    }
}

function splitIntoChunks($text, $chunkSize = 1000) {
    $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
    
    $chunks = [];
    $currentChunk = '';
    
    foreach ($sentences as $sentence) {
        if (strlen($currentChunk) + strlen($sentence) < $chunkSize) {
            $currentChunk .= $sentence . ' ';
        } else {
            if (!empty($currentChunk)) {
                $chunks[] = trim($currentChunk);
            }
            $currentChunk = $sentence . ' ';
        }
    }
    
    if (!empty($currentChunk)) {
        $chunks[] = trim($currentChunk);
    }
    
    return $chunks;
}

// Check if file path is provided
if ($argc < 2) {
    die("Usage: php process_file.php <file_path>\n");
}

$filePath = $argv[1];

// Check if file exists
if (!file_exists($filePath)) {
    die("Error: File not found: $filePath\n");
}

try {
    echo "Processing file: $filePath\n";
    
    // Extract text
    echo "Extracting text...\n";
    $text = extractTextFromFile($filePath);
    echo "Extracted " . strlen($text) . " characters\n";
    
    // Split into chunks
    echo "\nSplitting into chunks...\n";
    $chunks = splitIntoChunks($text);
    echo "Created " . count($chunks) . " chunks\n";
    
    // Display first few chunks
    echo "\nFirst 3 chunks preview:\n";
    for ($i = 0; $i < min(3, count($chunks)); $i++) {
        echo "\nChunk " . ($i + 1) . " (" . strlen($chunks[$i]) . " chars):\n";
        echo substr($chunks[$i], 0, 200) . "...\n";
    }
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage() . "\n");
} 