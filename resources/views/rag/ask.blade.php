@extends('layouts.app')

@section('content')

{{-- Static Navigation for this page --}}
<div class="bg-white border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-start h-16">
            <!-- Navigation Links -->
            <div class="flex space-x-8">
                <a href="{{ route('rag.index') }}"
                   class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 text-sm font-medium leading-5 focus:outline-none transition duration-150 ease-in-out">
                    Upload Files
                </a>
                <a href="{{ route('rag.ask.show') }}"
                   class="inline-flex items-center px-1 pt-1 border-b-2 border-indigo-400 text-gray-900 text-sm font-medium leading-5 focus:outline-none transition duration-150 ease-in-out">
                    Ask Question
                </a>
            </div>
        </div>
    </div>
</div>
{{-- End Static Navigation --}}

<div class="py-12 px-4">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        
        <div class="flex flex-col lg:flex-row gap-6">
            <!-- Left Sidebar: Project List in Card View -->
            <div class="w-full lg:w-1/4">
                <h3 class="text-lg font-semibold mb-4">Select Project</h3>
                <div id="project-list" class="space-y-4">
                    @foreach($projects as $project)
                        <div class="bg-white border rounded-xl shadow-sm hover:shadow-md transition cursor-pointer select-project p-3"
                            data-project-id="{{ $project->id }}"
                            data-project-name="{{ $project->name }}">
                            <h4 class="text-md font-semibold text-gray-800">{{ $project->name }}</h4>
                        </div>
                    @endforeach
                </div>

                <!-- Pagination Links -->
                <div class="mt-4 text-sm text-gray-600">
                    {{ $projects->links('pagination::tailwind') }}
                </div>
            </div>

            <!-- Right Content: Chat Interface -->
            <div class="w-full lg:w-3/4">
                <div class="chat-container hidden" id="chat-container">
                    <div class="chat-header">
                        <h2 class="text-xl font-semibold">RAG Assistant</h2>
                        <p class="text-sm text-gray-500" id="selected-project-label"></p>
                    </div>

                    <div class="chat-messages" id="chat-messages">
                        <div class="bot-message message">Hello! I'm your RAG assistant. Ask me anything about the selected project's documents.</div>
                    </div>

                    <div class="typing-indicator hidden" id="typing-indicator">
                        <div class="typing-dots">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                        <span class="typing-text">Assistant is typing...</span>
                    </div>

                    <div class="chat-input">
                        <form id="chat-form" class="input-group">
                            <input type="hidden" id="selectedProjectId" name="project_id">
                            <input type="text" id="user-input" placeholder="Type your question here..." required autocomplete="off">
                            <button type="submit" id="send-button">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z" />
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Chat CSS --}}
<style>
    .chat-container {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        height: 75vh;
        display: flex;
        flex-direction: column;
    }

    @media (max-width: 1024px) {
        .chat-container {
            height: auto;
            min-height: 70vh;
        }
    }

    .chat-header {
        padding: 1rem;
        background: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
        border-radius: 10px 10px 0 0;
    }

    .chat-messages {
        flex-grow: 1;
        padding-top: 1rem;
        padding-right: 1rem;
        padding-bottom: 1rem;
        padding-left: 0.25rem;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .message {
        max-width: 80%;
        padding: 0.75rem 1rem;
        border-radius: 15px;
        margin: 0.5rem 0;
        line-height: 1.5;
        white-space: pre-wrap;
        word-wrap: break-word;
    }

    .user-message {
        background: #007bff;
        color: white;
        align-self: flex-end;
        border-bottom-right-radius: 5px;
    }

    .bot-message {
        background: #f8f9fa;
        color: #212529;
        align-self: flex-start;
        border-bottom-left-radius: 5px;
        border: 1px solid #e9ecef;
        text-align: left;
        margin-left: 0 !important;
        margin-right: auto;
    }

    .chat-input {
        padding: 1rem;
        background: #f8f9fa;
        border-top: 1px solid #e9ecef;
        border-radius: 0 0 10px 10px;
    }

    .input-group {
        display: flex;
        gap: 0.5rem;
    }

    .chat-input input {
        flex-grow: 1;
        padding: 0.75rem 1rem;
        border: 1px solid #dee2e6;
        border-radius: 20px;
        outline: none;
        transition: border-color 0.2s;
    }

    .chat-input input:focus {
        border-color: #007bff;
    }

    .chat-input button {
        padding: 0.75rem;
        background: #007bff;
        color: white;
        border: none;
        border-radius: 50%;
        cursor: pointer;
        transition: background-color 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .chat-input button:hover {
        background: #0056b3;
    }

    .chat-input button:disabled {
        background: #6c757d;
        cursor: not-allowed;
    }

    .typing-indicator {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        background: #f8f9fa;
        border-radius: 15px;
        margin: 0.5rem 0;
        color: #6c757d;
        align-self: flex-start;
    }

    .typing-dots {
        display: flex;
        gap: 0.25rem;
    }

    .typing-dots span {
        width: 8px;
        height: 8px;
        background: #6c757d;
        border-radius: 50%;
        animation: typing 1s infinite ease-in-out;
    }

    .typing-dots span:nth-child(2) {
        animation-delay: 0.2s;
    }

    .typing-dots span:nth-child(3) {
        animation-delay: 0.4s;
    }

    @keyframes typing {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-5px); }
    }

    .active-project {
        border-color: #007bff !important;
        background-color: #ebf5ff !important;
    }

    .error-message {
        background: #dc3545;
        color: white;
        padding: 0.75rem 1rem;
        border-radius: 15px;
        margin: 0.5rem 0;
        align-self: center;
        text-align: center;
    }

    /* Ensure hidden class hides elements */
    .hidden {
        display: none !important;
    }
</style>

{{-- Chat Script --}}
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Initialize elements
        const chatContainer = document.getElementById('chat-container');
        const messagesContainer = document.getElementById('chat-messages');
        const typingIndicator = document.getElementById('typing-indicator');
        const projectIdInput = document.getElementById('selectedProjectId');
        const projectLabel = document.getElementById('selected-project-label');
        const form = document.getElementById('chat-form');
        const input = document.getElementById('user-input');
        const sendButton = document.getElementById('send-button');

        // Initialize project selection
        const projectButtons = document.querySelectorAll('.select-project');
        
        // Select Project
        projectButtons.forEach(btn => {
            btn.addEventListener('click', function () {
                const projectId = this.dataset.projectId;
                const projectName = this.dataset.projectName;

                if (!projectId || !projectName) {
                    showError('Invalid project data');
                    return;
                }

                // Save project ID
                projectIdInput.value = projectId;

                // Show chat
                chatContainer.classList.remove('hidden');

                // Show project name
                projectLabel.textContent = `Chatting about: ${projectName}`;

                // Enable input
                input.disabled = false;
                sendButton.disabled = false;

                // Highlight selected project
                projectButtons.forEach(b => b.classList.remove('active-project'));
                this.classList.add('active-project');

                // Reset previous messages
                messagesContainer.innerHTML = `
                    <div class="bot-message message">Hello! You're now chatting about <strong>${projectName}</strong>. Ask me anything about its documents.</div>
                `;
            });
        });

        function scrollToBottom() {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        function addMessage(content, isUser = false) {
            if (!content) return;

            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${isUser ? 'user-message' : 'bot-message'}`;
            messageDiv.textContent = content;
            messagesContainer.appendChild(messageDiv);
            scrollToBottom();
        }

        function showError(message) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            errorDiv.textContent = message;
            messagesContainer.appendChild(errorDiv);
            scrollToBottom();
        }

        function showTypingIndicator() {
            typingIndicator.classList.remove('hidden');
            scrollToBottom();
        }

        function hideTypingIndicator() {
            typingIndicator.classList.add('hidden');
        }

        form.addEventListener('submit', async function (e) {
            e.preventDefault();

            const message = input.value.trim();
            const projectId = projectIdInput.value;

            if (!message) return;
            if (!projectId) {
                showError('Please select a project first');
                return;
            }

            // Add user message
            addMessage(message, true);
            input.value = '';
            showTypingIndicator();

            try {
                const response = await fetch('{{ route("rag.ask") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ 
                        message: message, 
                        project_id: projectId 
                    })
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                hideTypingIndicator();
                
                if (data.error) {
                    showError(data.error);
                } else {
                    addMessage(data.response || data.answer);
                }
            } catch (error) {
                console.error('Error in chat submission:', error);
                hideTypingIndicator();
                showError('Sorry, something went wrong. Please try again.');
            }
        });

        // Initial state
        input.disabled = true;
        sendButton.disabled = true;
    });
</script>
@endsection
