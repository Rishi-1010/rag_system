<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RAG Chat Interface</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .chat-container {
            max-width: 1000px;
            margin: 2rem auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            height: 80vh;
            display: flex;
            flex-direction: column;
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
    </style>
</head>
<body class="bg-gray-100">
    <div class="chat-container">
        <div class="chat-header">
            <h2 class="text-xl font-semibold">RAG Assistant</h2>
        </div>
        
        <div class="chat-messages" id="chat-messages">
            <div class="bot-message message">
                Hello! I'm your RAG assistant. How can I help you today?
            </div>
        </div>

        <div class="typing-indicator" id="typing-indicator">
            Bot is typing...
        </div>

        <div class="chat-input">
            <form id="chat-form" class="input-group">
                <input type="text" 
                       id="user-input" 
                       placeholder="Type your message here..." 
                       required
                       autocomplete="off">
                <button type="submit">Send</button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('chat-form');
            const input = document.getElementById('user-input');
            const messagesContainer = document.getElementById('chat-messages');
            const typingIndicator = document.getElementById('typing-indicator');

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

            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const message = input.value.trim();
                if (!message) return;

                // Add user message to chat
                addMessage(message, true);
                input.value = '';

                // Show typing indicator
                showTypingIndicator();

                try {
                    const response = await fetch('/chat/send', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ message })
                    });

                    const data = await response.json();
                    
                    // Hide typing indicator
                    hideTypingIndicator();

                    // Add bot response to chat
                    addMessage(data.response);
                } catch (error) {
                    console.error('Error:', error);
                    hideTypingIndicator();
                    addMessage('Sorry, I encountered an error processing your request.', false);
                }
            });
        });
    </script>
</body>
</html> 