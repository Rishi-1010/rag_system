<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\QAController;
use App\Http\Controllers\RagController;
use App\Http\Controllers\ChatController;

// Root route - redirect to login if not authenticated
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('home');
    }
    return redirect()->route('login');
});

// Guest routes (only accessible when not logged in)
Route::middleware('guest')->group(function () {
    Route::get('/login', function () {
        return view('auth.login');
    })->name('login');

    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

// Protected routes (only accessible when logged in)
Route::middleware('auth')->group(function () {
    Route::get('/home', function () {
        return view('rag.index');
    })->name('home');
    
    // RAG System Routes
    Route::prefix('rag')->name('rag.')->group(function () {
        // Test route
        Route::get('/test', function() {
            return response()->json(['message' => 'Test route working']);
        });

        // Get CSRF token
        Route::get('/csrf-token', function() {
            return response()->json(['token' => csrf_token()]);
        });

        Route::get('/', function () {
            return view('rag.index');
        })->name('index');

        Route::get('/ask', function () {
            return view('rag.ask');
        })->name('ask.show');

        Route::post('/ask', [RagController::class, 'ask'])->name('ask');
        Route::match(['get', 'post'], '/upload', [RagController::class, 'upload'])->name('upload');
        Route::post('/delete', [RagController::class, 'deleteDocument'])->name('delete');
        Route::get('/list-documents', [RagController::class, 'listDocuments'])->name('list-documents');
    });
    
    // Files Routes
    Route::get('/files', [FileController::class, 'index'])->name('files.index');
    Route::post('/files/upload', [FileController::class, 'upload'])->name('files.upload');
    Route::post('/files/generate-embeddings', [FileController::class, 'generateEmbeddings'])->name('files.generate-embeddings');
    
    // Logout route
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Profile routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Chat routes
    Route::get('/chat', [ChatController::class, 'index'])->name('chat');
    Route::post('/chat/send', [ChatController::class, 'sendMessage'])->name('chat.send');

    // Test route for embeddings
    Route::get('/test-embedding', function () {
        $openAIService = app(App\Services\OpenAIService::class);
        $elasticsearchService = app(App\Services\ElasticsearchService::class);
        
        try {
            // Create index if it doesn't exist
            $elasticsearchService->createIndex();
            
            // Test text
            $text = "This is a test document for embedding generation and storage in Elasticsearch.";
            
            // Generate embedding
            $embedding = $openAIService->generateEmbedding($text);
            
            // Store in Elasticsearch
            $result = $elasticsearchService->indexDocument(
                'test-1',  // test file id
                1,         // chunk index
                $text,     // content
                $embedding // embedding vector
            );
            
            return response()->json([
                'status' => 'success',
                'message' => 'Test embedding generated and stored',
                'result' => $result
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    });
});

require __DIR__.'/auth.php';
