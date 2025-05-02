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

    private function checkFileExists($fileName)
    {
        try {
            $result = $this->ragService->checkFileExists($fileName);
            return $result;
        } catch (\Exception $e) {
            Log::error('Error checking file existence: ' . $e->getMessage());
            return false;
        }
    }

    public function index()
    {
        return view('rag.index');
    }

    public function upload(Request $request)
    {
        try {
            if (!$request->hasFile('files')) {
                return response()->json(['error' => 'No files uploaded'], 400);
            }

            ini_set('memory_limit', '512M');
            ini_set('max_execution_time', '900');
            set_time_limit(900);

            $files = $request->file('files');
            if (!is_array($files)) {
                $files = [$files];
            }

            $maxFileSize = 50 * 1024 * 1024; // 50MB in bytes
            $results = [];

            return response()->stream(function () use ($files, $maxFileSize, &$results) {
                foreach ($files as $file) {
                    try {
                        if (!$file->isValid()) {
                            $results[] = [
                                'name' => $file->getClientOriginalName(),
                                'status' => 'error',
                                'message' => 'Invalid file'
                            ];
                            echo 'data: ' . json_encode(['status' => 'error', 'stage' => 'validation', 'message' => 'Invalid file']) . "\n\n";
                            ob_flush(); flush();
                            continue;
                        }

                        if ($file->getSize() > $maxFileSize) {
                            $results[] = [
                                'name' => $file->getClientOriginalName(),
                                'status' => 'error',
                                'message' => 'File size exceeds limit of 50MB'
                            ];
                            echo 'data: ' . json_encode(['status' => 'error', 'stage' => 'validation', 'message' => 'File size exceeds limit of 50MB']) . "\n\n";
                            ob_flush(); flush();
                            continue;
                        }

                        if ($this->checkFileExists($file->getClientOriginalName())) {
                            $results[] = [
                                'name' => $file->getClientOriginalName(),
                                'status' => 'duplicate',
                                'message' => 'This file has already been processed and indexed. Please upload a different file.',
                                'type' => 'error'
                            ];
                            echo 'data: ' . json_encode(['status' => 'duplicate', 'stage' => 'validation', 'message' => 'This file has already been processed and indexed. Please upload a different file.']) . "\n\n";
                            ob_flush(); flush();
                            continue;
                        }

                        $tempPath = storage_path('app' . DIRECTORY_SEPARATOR . 'temp');
                        if (!file_exists($tempPath)) {
                            mkdir($tempPath, 0755, true);
                        }
                        $filename = uniqid() . '_' . $file->getClientOriginalName();
                        $fullPath = $tempPath . DIRECTORY_SEPARATOR . $filename;
                        if (!$file->move($tempPath, $filename)) {
                            throw new Exception("Failed to move uploaded file");
                        }

                        $startTime = microtime(true);
                        $progressCallback = function ($progress) use ($file) {
                            $progress['file'] = $file->getClientOriginalName();
                            echo 'data: ' . json_encode($progress) . "\n\n";
                            ob_flush(); flush();
                        };
                        $this->ragService->indexDocument($fullPath, $progressCallback);
                        if (file_exists($fullPath)) {
                            unlink($fullPath);
                        }
                        $processingTime = round(microtime(true) - $startTime, 2);
                        $results[] = [
                            'name' => $file->getClientOriginalName(),
                            'status' => 'success',
                            'message' => "File processed successfully in {$processingTime} seconds"
                        ];
                        echo 'data: ' . json_encode(['status' => 'success', 'stage' => 'completed', 'file' => $file->getClientOriginalName(), 'message' => "File processed successfully in {$processingTime} seconds"]) . "\n\n";
                        ob_flush(); flush();
                    } catch (Exception $e) {
                        $results[] = [
                            'name' => $file->getClientOriginalName(),
                            'status' => 'error',
                            'message' => $e->getMessage()
                        ];
                        echo 'data: ' . json_encode(['status' => 'error', 'stage' => 'exception', 'file' => $file->getClientOriginalName(), 'message' => $e->getMessage()]) . "\n\n";
                        ob_flush(); flush();
                    }
                }
                // Final completion event
                echo 'data: ' . json_encode(['status' => 'completed', 'results' => $results]) . "\n\n";
                ob_flush(); flush();
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
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