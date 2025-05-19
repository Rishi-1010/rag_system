<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\Project;
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

    public function index(Request $request)
    {
        $projects = Project::all();
        // $uploadedFiles = File::with('project')->latest()->paginate(10);
         $query = File::with('project')->latest();

        if ($request->filled('search')) {
            $searchTerm = $request->input('search');
            $query->where('original_name', 'like', "%{$searchTerm}%");
        }

        $uploadedFiles = $query->paginate(10);

        // Keep search query in pagination links
        $uploadedFiles->appends($request->only('search'));
        return view('rag.index', compact('projects', 'uploadedFiles'));
    }

    public function upload(Request $request)
    {
        try {
            if (!$request->hasFile('files')) {
                return response()->json(['error' => 'No files uploaded'], 400);
            }
            $projectId = $request->project_id ?? null;
            ini_set('memory_limit', '512M');
            ini_set('max_execution_time', '900');
            set_time_limit(900);

            $files = $request->file('files');
            if (!is_array($files)) {
                $files = [$files];
            }

            $maxFileSize = 50 * 1024 * 1024; // 50MB in bytes
            $results = [];

            return response()->stream(function () use ($files, $maxFileSize, &$results , $projectId) {
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
                        $originalSize = $file->getSize();
                        $filename = uniqid() . '_' . $file->getClientOriginalName();
                        $fullPath = $tempPath . DIRECTORY_SEPARATOR . $filename;
                        if (!$file->move($tempPath, $filename)) {
                            throw new Exception("Failed to move uploaded file");
                        }

                        // Save file info to DB
                        $fileModel = File::create([
                            'filename' => $filename,
                            'original_name' => $file->getClientOriginalName(),
                            'path' => $fullPath,
                            'size' => $originalSize,
                            'mime_type' => $file->getClientMimeType(),
                            'embedding_status' => 'processing',
                            'project_id' => $projectId,
                        ]);

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

                        // Update status in DB
                        $fileModel->update(['embedding_status' => 'completed']);
                        
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

    public function ask()
    {
        $projects = Project::paginate(10);
        return view('rag.ask', compact('projects'));
    }
    public function storeask(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        try {
            $fileNames = File::where('project_id', $request->project_id)->pluck('original_name')->values()->toArray();

            if (empty($fileNames)) {
                return response()->json([
                    'answer' => "The provided context does not contain relevant information to answer the question."
                ]);
            }
                            // dd($fileNames);
            $response = $this->ragService->answerQuestion($request->message, $fileNames);
            // dd($response);
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

            // Find the file in the database
            $file = File::where('original_name', $fileName)->first();

            if (!$file) {
                return response()->json(['error' => 'File not found'], 404);
            }

            // Optionally delete the physical file
            if (\Storage::exists($file->path)) {
                \Storage::delete($file->path);
            }

            // Delete from RAG vector DB or internal system
            $result = $this->ragService->deleteDocumentsByFileName($fileName);

            // Delete from database
            $file->delete();

            // $result = $this->ragService->deleteDocumentsByFileName($fileName);
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

    public function getUploadedFiles()
    {
            $uploadedFiles = File::with('project')->latest()->get();

            // Build the HTML string manually here if you're avoiding partials
            $html = '';

            $html .= '<h3 class="text-lg font-semibold mb-4">Previously Uploaded Files</h3>';
            if ($uploadedFiles->isEmpty()) {
                $html .= '<p class="text-sm text-gray-500">No files have been uploaded yet.</p>';
            } else {
                $html .= '<ul class="space-y-2">';
                foreach ($uploadedFiles as $file) {
                    $html .= '<li class="flex items-center justify-between bg-gray-100 rounded px-4 py-2 shadow-sm">
                                <div>
                                    <p class="text-base font-medium">' . e($file->original_name) . '</p>
                                    <p class="text-sm text-gray-500">
                                        Size: ' . number_format($file->size / 1024, 2) . ' KB |
                                        Uploaded: ' . $file->created_at->format('Y-m-d H:i') . ' |
                                        <span class="font-semibold text-gray-700">Project:</span> ' . e(optional($file->project)->name ?? 'N/A') . '
                                    </p>
                                </div>
                                <div class="flex gap-2">
                                    <button 
                                        class="text-red-600 text-sm hover:underline delete-file-btn"
                                        data-filename="' . e($file->original_name) . '">
                                        Delete
                                    </button>
                                </div>
                            </li>';
                }
                $html .= '</ul>';
            }

            return response($html);
    }

    public function storeProject(Request $request)
    {
        $request->validate(['name' => 'required']);

        // Check if the project already exists
        $existingProject = Project::where('name', $request->name)->first();

        if ($existingProject) {
            return response()->json([
                'error' => 'A project with this name already exists.',
            ], 400);
        }

        // Create a new project if it doesn't exist
        $project = Project::create(['name' => $request->name]);

        return response()->json(['project' => $project]);
    }
}