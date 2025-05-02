<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\File;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser as PdfParser;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Illuminate\Support\Facades\Http;
use App\Services\OpenAIService;
use App\Services\ElasticsearchService;

class FileController extends Controller
{
    public function index()
    {
        $files = File::all();
        return view('files.index', compact('files'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,doc,docx,txt|max:10240', // 10MB max
        ]);

        $file = $request->file('file');
        $filename = time() . '_' . $file->getClientOriginalName();
        
        // Store the file
        $path = $file->storeAs('uploads', $filename, 'public');

        // Create file record in database
        File::create([
            'filename' => $filename,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'embedding_status' => 'pending'
        ]);

        return redirect()->route('files.index')->with('success', 'File uploaded successfully.');
    }

    public function generateEmbeddings(File $file)
    {
        try {
            $file->update(['embedding_status' => 'processing']);
            
            // Get the file content based on its type
            $content = $this->extractTextFromFile($file);
            
            if (empty($content)) {
                throw new \Exception('Could not extract text from file');
            }

            // Split content into chunks (you can adjust the chunk size)
            $chunks = $this->splitIntoChunks($content, 1000);

            // Generate embeddings for each chunk
            foreach ($chunks as $chunk) {
                $this->generateEmbedding($chunk, $file);
            }

            $file->update(['embedding_status' => 'completed']);
            return redirect()->route('files.index')->with('success', 'Embeddings generated successfully.');
        } catch (\Exception $e) {
            Log::error('Embedding generation failed: ' . $e->getMessage());
            $file->update(['embedding_status' => 'failed']);
            return redirect()->route('files.index')->with('error', 'Failed to generate embeddings: ' . $e->getMessage());
        }
    }

    private function extractTextFromFile(File $file)
    {
        $path = Storage::disk('public')->path($file->path);
        
        switch ($file->mime_type) {
            case 'application/pdf':
                $parser = new PdfParser();
                $pdf = $parser->parseFile($path);
                return $pdf->getText();
            
            case 'application/msword':
            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
                $phpWord = WordIOFactory::load($path);
                $text = '';
                foreach ($phpWord->getSections() as $section) {
                    foreach ($section->getElements() as $element) {
                        if (method_exists($element, 'getText')) {
                            $text .= $element->getText() . "\n";
                        }
                    }
                }
                return $text;
            
            case 'text/plain':
                return file_get_contents($path);
            
            default:
                throw new \Exception('Unsupported file type');
        }
    }

    private function splitIntoChunks($text, $chunkSize)
    {
        // Split text into sentences
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

    private function generateEmbedding($text, File $file)
    {
        $openAIService = app(OpenAIService::class);
        $elasticsearchService = app(ElasticsearchService::class);
        
        try {
            // Generate embedding using OpenAI
            $embedding = $openAIService->generateEmbedding($text);
            
            // Store in Elasticsearch
            $elasticsearchService->indexDocument(
                $file->id,
                $file->chunks()->count() + 1,
                $text,
                $embedding
            );
            
            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to generate or store embedding: ' . $e->getMessage());
            throw $e;
        }
    }
} 