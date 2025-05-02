<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\RagService;

class ChatController extends Controller
{
    protected $ragService;

    public function __construct(RagService $ragService)
    {
        $this->ragService = $ragService;
    }

    public function index()
    {
        return view('chat');
    }

    public function sendMessage(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        try {
            $response = $this->ragService->answerQuestion($request->message);
            return response()->json(['response' => $response]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while processing your request.',
                'details' => $e->getMessage()
            ], 500);
        }
    }
} 