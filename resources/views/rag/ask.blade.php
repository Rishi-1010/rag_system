@extends('layouts.app')

@section('content')
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
                        <div class="bot-message message">
                            Hello! I'm your RAG assistant. Ask me anything about the selected project's documents.
                        </div>
                    </div>

                    <div class="typing-indicator" id="typing-indicator">Bot is typing...</div>

                    <div class="chat-input">
                        <form id="chat-form" class="input-group">
                            <input type="hidden" id="selectedProjectId" name="project_id">
                            <input type="text" id="user-input" placeholder="Type your question here..." required autocomplete="off">
                            <button type="submit">Send</button>
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
        padding: 1rem;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .message {
        max-width: 70%;
        padding: 0.75rem 1rem;
        border-radius: 15px;
        margin: 0.5rem 0;
    }

    .user-message {
        background: #007bff;
        color: white;
        align-self: flex-end;
        border-bottom-right-radius: 5px;
    }

    .bot-message {
        background: #e9ecef;
        color: #212529;
        align-self: flex-start;
        border-bottom-left-radius: 5px;
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
        padding: 0.5rem 1rem;
        border: 1px solid #dee2e6;
        border-radius: 20px;
        outline: none;
    }

    .chat-input button {
        padding: 0.5rem 1.5rem;
        background: #007bff;
        color: white;
        border: none;
        border-radius: 20px;
        cursor: pointer;
        transition: background-color 0.2s;
    }

    .chat-input button:hover {
        background: #0056b3;
    }

    .typing-indicator {
        display: none;
        align-self: flex-start;
        background: #e9ecef;
        padding: 0.5rem 1rem;
        border-radius: 15px;
        margin: 0.5rem 0;
        color: #6c757d;
    }

    .typing-indicator.active {
        display: block;
    }

    .active-project {
        border-color: #007bff !important;
        background-color: #ebf5ff !important;
    }
</style>


{{-- Chat Script --}}
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const chatContainer = document.getElementById('chat-container');
        const messagesContainer = document.getElementById('chat-messages');
        const typingIndicator = document.getElementById('typing-indicator');
        const projectIdInput = document.getElementById('selectedProjectId');
        const projectLabel = document.getElementById('selected-project-label');
        const input = document.getElementById('user-input');
        const form = document.getElementById('chat-form');
        const submitBtn = form.querySelector('button');

        // Select Project
        document.querySelectorAll('.select-project').forEach(btn => {
            btn.addEventListener('click', function () {
                const projectId = this.dataset.projectId;
                const projectName = this.dataset.projectName;

                // Save project ID
                projectIdInput.value = projectId;

                // Show chat
                chatContainer.classList.remove('hidden');

                // Show project name
                projectLabel.textContent = `Chatting about: ${projectName}`;

                // Enable input
                input.disabled = false;
                submitBtn.disabled = false;

                // Highlight selected project
                document.querySelectorAll('.select-project').forEach(b => b.classList.remove('active-project'));
                this.classList.add('active-project');


                // Reset previous messages (optional)
                messagesContainer.innerHTML = `
                    <div class="bot-message message">
                        Hello! You're now chatting about <strong>${projectName}</strong>. Ask me anything about its documents.
                    </div>
                `;
            });
        });

        function scrollToBottom() {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        function addMessage(content, isUser = false) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${isUser ? 'user-message' : 'bot-message'}`;
            messageDiv.textContent = content;
            messagesContainer.appendChild(messageDiv);
            scrollToBottom();
        }

        function showTypingIndicator() {
            typingIndicator.classList.add('active');
            scrollToBottom();
        }

        function hideTypingIndicator() {
            typingIndicator.classList.remove('active');
        }

        form.addEventListener('submit', async function (e) {
            e.preventDefault();

            const message = input.value.trim();
            const projectId = projectIdInput.value;

            if (!message || !projectId) return;

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
                    body: JSON.stringify({ message: message, project_id: projectId })
                });

                const data = await response.json();
                hideTypingIndicator();
                addMessage(data.response || data.answer);
            } catch (error) {
                console.error('Error:', error);
                hideTypingIndicator();
                addMessage('Sorry, something went wrong.');
            }
        });
    });
</script>
@endsection
