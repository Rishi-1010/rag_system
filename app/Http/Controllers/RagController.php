<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\RagService;
use Illuminate\Support\Facades\Log;
use Exception;

class RagController extends Controller
{
    protected $ragService;

    public function __construct(RagService $ragService)
    {
        $this->ragService = $ragService;
    }

    public function index()
    {
        return view('rag.index');
    }

    public function upload(Request $request)
    {
        // Create a simple debug file in the public directory
        $debugFile = public_path('debug.txt');
        
        try {
            // Ensure we can write to the debug file
            if (!is_writable(dirname($debugFile))) {
                chmod(dirname($debugFile), 0777);
            }
            
            // Clear the debug file for this request
            file_put_contents($debugFile, date('Y-m-d H:i:s') . " - New upload request started\n");
            
            // Log basic request info
            $requestInfo = [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'headers' => $request->headers->all(),
                'all' => $request->all(),
                'files' => $request->allFiles(),
                'content_type' => $request->header('Content-Type'),
                'accept' => $request->header('Accept')
            ];
            
            file_put_contents($debugFile, date('Y-m-d H:i:s') . " - Request info: " . json_encode($requestInfo, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
            
            // If this is a GET request, it's the SSE connection
            if ($request->isMethod('get')) {
                file_put_contents($debugFile, date('Y-m-d H:i:s') . " - SSE connection established\n", FILE_APPEND);
                
                return response()->stream(function() use ($debugFile) {
                    // Keep the connection open
                    while (true) {
                        if (connection_aborted()) {
                            file_put_contents($debugFile, date('Y-m-d H:i:s') . " - SSE connection closed\n", FILE_APPEND);
                            break;
                        }
                        echo "data: " . json_encode(['status' => 'connected']) . "\n\n";
                        ob_flush();
                        flush();
                        sleep(1);
                    }
                }, 200, [
                    'Cache-Control' => 'no-cache',
                    'Content-Type' => 'text/event-stream',
                    'X-Accel-Buffering' => 'no',
                    'Connection' => 'keep-alive'
                ]);
            }
            
            if (!$request->hasFile('files')) {
                file_put_contents($debugFile, date('Y-m-d H:i:s') . " - No files in request\n", FILE_APPEND);
                return response()->json(['error' => 'No files uploaded'], 400);
            }

            // Increase limits for this request
            ini_set('memory_limit', '512M');
            ini_set('max_execution_time', '900'); // 15 minutes
            set_time_limit(900); // 15 minutes

            // Enable implicit flush for real-time progress
            if (function_exists('apache_setenv')) {
                apache_setenv('no-gzip', '1');
            }
            ini_set('zlib.output_compression', '0');
            ini_set('implicit_flush', '1');
            ob_implicit_flush(1);

            $files = $request->file('files');
            if (!is_array($files)) {
                $files = [$files];
            }

            file_put_contents($debugFile, date('Y-m-d H:i:s') . " - Processing " . count($files) . " files\n", FILE_APPEND);

            $maxFileSize = 50 * 1024 * 1024; // 50MB in bytes
            $results = [];
            
            foreach ($files as $file) {
                try {
                    $fileInfo = [
                        'name' => $file->getClientOriginalName(),
                        'size' => $file->getSize(),
                        'type' => $file->getMimeType()
                    ];
                    file_put_contents($debugFile, date('Y-m-d H:i:s') . " - Processing file: " . json_encode($fileInfo) . "\n", FILE_APPEND);

                    if (!$file->isValid()) {
                        file_put_contents($debugFile, date('Y-m-d H:i:s') . " - Invalid file: " . $file->getClientOriginalName() . "\n", FILE_APPEND);
                        $results[] = [
                            'name' => $file->getClientOriginalName(),
                            'status' => 'error',
                            'message' => 'Invalid file'
                        ];
                        continue;
                    }

                    if ($file->getSize() > $maxFileSize) {
                        file_put_contents($debugFile, date('Y-m-d H:i:s') . " - File too large: " . $file->getClientOriginalName() . " (" . $file->getSize() . " bytes)\n", FILE_APPEND);
                        $results[] = [
                            'name' => $file->getClientOriginalName(),
                            'status' => 'error',
                            'message' => 'File size exceeds limit of 50MB'
                        ];
                        continue;
                    }

                    // Ensure temp directory exists
                    $tempPath = storage_path('app' . DIRECTORY_SEPARATOR . 'temp');
                    if (!file_exists($tempPath)) {
                        file_put_contents($debugFile, date('Y-m-d H:i:s') . " - Creating temp directory\n", FILE_APPEND);
                        mkdir($tempPath, 0755, true);
                    }

                    // Generate a unique filename
                    $filename = uniqid() . '_' . $file->getClientOriginalName();
                    $fullPath = $tempPath . DIRECTORY_SEPARATOR . $filename;

                    file_put_contents($debugFile, date('Y-m-d H:i:s') . " - Moving file to: " . $fullPath . "\n", FILE_APPEND);

                    // Move the file directly
                    if (!$file->move($tempPath, $filename)) {
                        throw new Exception("Failed to move uploaded file");
                    }

                    // Process the file and send progress updates
                    $startTime = microtime(true);
                    file_put_contents($debugFile, date('Y-m-d H:i:s') . " - Starting document indexing\n", FILE_APPEND);
                    
                    $this->ragService->indexDocument($fullPath, function($progress) use ($file, $debugFile) {
                        $response = [
                            'status' => 'processing',
                            'file' => $file->getClientOriginalName(),
                            'progress' => $progress
                        ];
                        file_put_contents($debugFile, date('Y-m-d H:i:s') . " - Progress update: " . json_encode($response) . "\n", FILE_APPEND);
                        echo "data: " . json_encode($response) . "\n\n";
                        ob_flush();
                        flush();
                    });

                    // Clean up
                    if (file_exists($fullPath)) {
                        file_put_contents($debugFile, date('Y-m-d H:i:s') . " - Cleaning up temp file\n", FILE_APPEND);
                        unlink($fullPath);
                    }

                    $processingTime = round(microtime(true) - $startTime, 2);
                    file_put_contents($debugFile, date('Y-m-d H:i:s') . " - File processing completed in {$processingTime} seconds\n", FILE_APPEND);

                    $results[] = [
                        'name' => $file->getClientOriginalName(),
                        'status' => 'success',
                        'message' => "File processed successfully in {$processingTime} seconds"
                    ];

                    // Send final progress update
                    $response = [
                        'status' => 'completed',
                        'file' => $file->getClientOriginalName(),
                        'progress' => 100
                    ];
                    file_put_contents($debugFile, date('Y-m-d H:i:s') . " - Sending completion update\n", FILE_APPEND);
                    echo "data: " . json_encode($response) . "\n\n";
                    ob_flush();
                    flush();

                } catch (Exception $e) {
                    $errorMessage = 'Error processing file ' . $file->getClientOriginalName() . ': ' . $e->getMessage();
                    file_put_contents($debugFile, date('Y-m-d H:i:s') . " - " . $errorMessage . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
                    $results[] = [
                        'name' => $file->getClientOriginalName(),
                        'status' => 'error',
                        'message' => $e->getMessage()
                    ];
                }
            }

            file_put_contents($debugFile, date('Y-m-d H:i:s') . " - Upload process completed\n", FILE_APPEND);
            return response()->json([
                'message' => 'Files processed',
                'results' => $results
            ]);
        } catch (Exception $e) {
            $errorMessage = 'Upload error: ' . $e->getMessage();
            file_put_contents($debugFile, date('Y-m-d H:i:s') . " - " . $errorMessage . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
            
            // Return a proper JSON error response
            return response()->json([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function ask(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        try {
            $response = $this->ragService->answerQuestion($request->message);
            return response()->json(['answer' => $response]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while processing your request.',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteDocument(Request $request)
    {
        try {
            $fileName = $request->input('fileName');
            
            if (empty($fileName)) {
                return response()->json(['error' => 'File name is required'], 400);
            }

            $result = $this->ragService->deleteDocumentsByFileName($fileName);
            return response()->json(['message' => 'Documents deleted successfully', 'result' => $result]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function listDocuments()
    {
        try {
            $result = $this->ragService->listAllDocuments();
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
} 