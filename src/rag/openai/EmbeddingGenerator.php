<?php

namespace Rag\OpenAI;

use OpenAI\Client as OpenAIClient;
use OpenAI\Factory;

class EmbeddingGenerator
{
    private OpenAIClient $client;

    public function __construct(string $apiKey)
    {
        $this->client = (new Factory())
            ->withApiKey($apiKey)
            ->make();
    }

    public function generateEmbeddings(string $filePath): array
    {
        $documents = $this->processFile($filePath);
        $embeddedDocuments = [];

        foreach ($documents as $doc) {
            $embedding = $this->generateEmbedding($doc->content);
            $doc->embedding = $embedding;
            $embeddedDocuments[] = $doc;
        }

        return $embeddedDocuments;
    }

    private function generateEmbedding(string $text): array
    {
        $response = $this->client->embeddings()->create([
            'model' => 'text-embedding-3-small',
            'input' => $text,
        ]);

        return $response->embeddings[0]->embedding;
    }

    private function processFile(string $filePath): array
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $documents = [];

        if (empty($ext) || $ext === 'txt') {
            $content = file_get_contents($filePath);
            if ($content === false) {
                throw new \RuntimeException("Could not read file: $filePath");
            }

            $qaPairs = $this->extractQAPairs($content);
            foreach ($qaPairs as $index => $pair) {
                $doc = new \stdClass();
                $doc->content = $pair['question'] . "\n" . $pair['answer'];
                $doc->sourceName = realpath($filePath);
                $doc->chunkNumber = $index + 1;
                $documents[] = $doc;
            }
        } elseif ($ext === 'pdf') {
            // For PDF files, we'll need to implement PDF processing
            throw new \RuntimeException("PDF processing not implemented yet");
        } elseif ($ext === 'json') {
            $jsonData = json_decode(file_get_contents($filePath), true);
            if (!is_array($jsonData)) {
                throw new \RuntimeException("JSON file does not contain an array at the top level");
            }

            foreach ($jsonData as $item) {
                if (isset($item['message']) && !empty($item['message'])) {
                    $doc = new \stdClass();
                    $doc->content = $item['message'];
                    $doc->sourceName = realpath($filePath);
                    $documents[] = $doc;
                }
                if (isset($item['responses']) && is_array($item['responses'])) {
                    foreach ($item['responses'] as $response) {
                        if (isset($response['message']) && !empty($response['message'])) {
                            $doc = new \stdClass();
                            $doc->content = $response['message'];
                            $doc->sourceName = realpath($filePath);
                            $documents[] = $doc;
                        }
                    }
                }
            }
        } else {
            throw new \RuntimeException("Unsupported file type: $ext");
        }

        if (empty($documents)) {
            throw new \RuntimeException("No content was extracted from the file");
        }

        return $documents;
    }

    private function extractQAPairs(string $content): array
    {
        $qaPairs = [];
        $lines = explode("\n", $content);
        $currentQ = '';
        $currentA = '';

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if (strpos($line, 'Q:') === 0) {
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
                $currentA .= "\n" . $line;
            }
        }

        if (!empty($currentQ) && !empty($currentA)) {
            $qaPairs[] = [
                'question' => $currentQ,
                'answer' => $currentA
            ];
        }

        return $qaPairs;
    }
} 