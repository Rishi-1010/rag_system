@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Success Message Notification -->
    <div id="successMessage" class="hidden fixed top-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded shadow-lg z-50 transition-all duration-500 transform translate-x-full" role="alert">
        <div class="flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span class="font-medium">Success!</span>
            <span class="ml-2">Files have been uploaded and processed successfully.</span>
            <button class="ml-auto" onclick="hideSuccessMessage()">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>
    <div id="alert-container" class="mt-4"></div>
    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-2xl font-bold mb-6">Upload Documents</h2>
            
            <form id="uploadForm" class="space-y-4">
                @csrf
                <!-- Drag & Drop Zone -->
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-8 transition-all duration-200" 
                     id="dropZone">
                    <div class="text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <div class="flex items-center justify-center mt-4">
                            <label class="relative cursor-pointer">
                                <span class="inline-flex items-center px-4 py-2 text-sm font-semibold text-blue-700 bg-blue-50 rounded-full hover:bg-blue-100">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Choose Files
                                </span>
                                <input type="file" name="files[]" multiple accept=".pdf,.txt,.doc,.docx" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                            </label>
                            <button type="button" id="uploadButton" class="ml-4 px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition duration-200 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                                Upload
                            </button>
                        </div>
                        <p class="mt-2 text-sm text-gray-500">
                            Drag and drop files here or click to select
                        </p>
                        <p class="text-xs text-gray-400 mt-1">
                            Supported formats: PDF, TXT, DOC, DOCX (Max 10MB per file)
                        </p>
                    </div>
                </div>

                <!-- File List -->
                <div id="fileList" class="mt-4 space-y-2 max-h-60 overflow-y-auto">
                    <p class="text-gray-500 text-sm">No files selected</p>
                </div>

                <!-- Progress Container -->
                <div id="progressContainer" class="hidden mt-6 bg-gray-50 rounded-lg p-4">
                    <div class="mb-3">
                        <div class="flex justify-between items-center mb-2">
                            <div class="flex items-center space-x-2">
                                <div id="statusIndicator" class="w-3 h-3 rounded-full bg-gray-400"></div>
                                <span id="progressStage" class="text-sm font-medium text-gray-700">Initializing...</span>
                            </div>
                            <span id="progressPercentage" class="text-sm font-medium text-gray-700">0%</span>
                        </div>
                        <div class="progress-container w-full">
                            <div id="progressBar" class="progress-bar-animated bg-gradient-to-r from-blue-500 via-blue-600 to-blue-500 bg-[length:200%_100%] transition-all duration-300 ease-out" style="width: 0%"></div>
                        </div>
                    </div>
                    <div class="flex justify-between text-xs text-gray-500">
                        <span id="progressDetails" class="flex items-center">
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Processing...
                        </span>
                        <span id="estimatedTime"></span>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<style>
    @keyframes progressBarAnimation {
        0% {
            background-position: 100% 0;
        }
        100% {
            background-position: -100% 0;
        }
    }

    .progress-bar-animated {
        animation: progressBarAnimation 2s linear infinite;
        height: 8px !important;
        border-radius: 4px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        min-width: 2%; /* Ensure minimum width for visibility */
    }

    /* Pause animation when upload is complete */
    .progress-bar-animated.complete {
        animation: none;
        background: theme('colors.blue.600');
    }

    /* Status indicator colors */
    .status-initializing { background-color: theme('colors.gray.400'); }
    .status-processing { background-color: theme('colors.blue.500'); }
    .status-completed { background-color: theme('colors.green.500'); }
    .status-error { background-color: theme('colors.red.500'); }

    /* Progress bar container */
    .progress-container {
        background-color: theme('colors.gray.100');
        border-radius: 4px;
        padding: 2px;
        box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.1);
        min-height: 8px; /* Ensure minimum height */
    }

    /* Ensure progress container is visible when active */
    #progressContainer:not(.hidden) {
        display: block !important;
        opacity: 1 !important;
        visibility: visible !important;
    }
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('uploadForm');
    const fileInput = document.querySelector('input[type="file"]');
    const fileList = document.getElementById('fileList');
    const uploadButton = document.getElementById('uploadButton');
    const dropZone = document.getElementById('dropZone');
    const progressContainer = document.getElementById('progressContainer');
    const progressBar = document.getElementById('progressBar');
    const progressStage = document.getElementById('progressStage');
    const progressPercentage = document.getElementById('progressPercentage');
    const progressDetails = document.getElementById('progressDetails');
    const estimatedTime = document.getElementById('estimatedTime');

    const stageDescriptions = {
        'initializing': 'Preparing files for processing',
        'reading': 'Reading file contents',
        'extracting': 'Extracting text from documents',
        'cleaning': 'Cleaning and preparing text',
        'chunking': 'Splitting text into chunks',
        'processing': 'Generating embeddings',
        'completed': 'Processing completed'
    };

    // Drag and drop handlers
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
        document.body.addEventListener(eventName, preventDefaults, false);
    });

    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, highlight, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, unhighlight, false);
    });

    dropZone.addEventListener('drop', handleDrop, false);

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    function highlight(e) {
        dropZone.classList.add('border-blue-500', 'bg-blue-50');
    }

    function unhighlight(e) {
        dropZone.classList.remove('border-blue-500', 'bg-blue-50');
    }

    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        fileInput.files = files;
        updateFileList();
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    function getFileIcon(fileType) {
        const icons = {
            'pdf': `<svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>`,
            'txt': `<svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>`,
            'doc': `<svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>`
        };
        
        if (fileType.includes('pdf')) return icons.pdf;
        if (fileType.includes('text')) return icons.txt;
        if (fileType.includes('word') || fileType.includes('doc')) return icons.doc;
        return icons.txt;
    }

    function updateFileList() {
        const files = fileInput.files;
        
        fileList.innerHTML = '';
        if (!files || files.length === 0) {
            fileList.innerHTML = '<p class="text-gray-500 text-sm">No files selected</p>';
            uploadButton.disabled = true;
            return;
        }

        let totalSize = 0;
        let hasInvalidFiles = false;

        Array.from(files).forEach((file, index) => {
            totalSize += file.size;
            const fileSize = formatFileSize(file.size);
            const isValid = file.size <= 10 * 1024 * 1024; // 10MB limit
            
            fileList.innerHTML += `
                <div class="flex items-center justify-between p-3 ${isValid ? 'bg-gray-50' : 'bg-red-50'} rounded border ${isValid ? 'border-gray-200' : 'border-red-200'} group hover:bg-gray-100 transition-colors duration-200">
                    <div class="flex items-center space-x-3">
                        ${getFileIcon(file.type)}
                        <div>
                            <p class="text-sm font-medium ${isValid ? 'text-gray-700' : 'text-red-700'}">${file.name}</p>
                            <p class="text-xs ${isValid ? 'text-gray-500' : 'text-red-500'}">${fileSize} • ${file.type || 'Unknown type'}</p>
                            ${!isValid ? `<p class="text-xs text-red-600 mt-1">File too large (max 10MB)</p>` : ''}
                        </div>
                    </div>
                    <button type="button" onclick="removeFile('${file.name}')" class="text-gray-400 hover:text-red-500 transition-colors duration-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            `;

            if (!isValid) hasInvalidFiles = true;
        });

        if (files.length > 1) {
            fileList.innerHTML += `
                <div class="mt-2 pt-2 border-t border-gray-200">
                    <p class="text-sm text-gray-600">Total size: ${formatFileSize(totalSize)}</p>
                    <p class="text-xs text-gray-500">${files.length} files selected</p>
                </div>
            `;
        }

        uploadButton.disabled = hasInvalidFiles || files.length === 0;
    }

    // File input change handler
    fileInput.addEventListener('change', updateFileList);

    // Upload button click handler
    uploadButton.addEventListener('click', async function() {
        const files = fileInput.files;
        if (!files || files.length === 0) {
            alert('Please select at least one file');
            return;
        }

        console.log('Upload started with files:', Array.from(files).map(f => f.name));

        // Hide any existing success message
        hideSuccessMessage();

        // Force show progress container
        progressContainer.classList.remove('hidden');
        progressContainer.style.display = 'block';
        progressContainer.style.opacity = '1';
        progressContainer.style.visibility = 'visible';
        
        console.log('Progress container state:', {
            hidden: progressContainer.classList.contains('hidden'),
            display: progressContainer.style.display,
            opacity: progressContainer.style.opacity,
            visibility: progressContainer.style.visibility,
            height: progressContainer.offsetHeight,
            width: progressContainer.offsetWidth
        });

        uploadButton.disabled = true;
        progressBar.style.width = '2%'; // Start with minimum visible width
        progressStage.textContent = 'Initializing...';
        progressPercentage.textContent = '0%';

        const formData = new FormData();
        Array.from(files).forEach(file => {
            formData.append('files[]', file);
        });

        try {
            console.log('Sending upload request to /rag/upload');
            const response = await fetch('/rag/upload', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            console.log('Upload response received:', {
                status: response.status,
                statusText: response.statusText,
                headers: Object.fromEntries(response.headers.entries())
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            // Create EventSource for SSE
            const eventSource = new EventSource('/rag/progress');
            
            eventSource.onmessage = function(event) {
                console.log('SSE message received:', event.data);
                try {
                    const data = JSON.parse(event.data);
                    console.log('Parsed SSE data:', data);
                    updateProgress(data);
                } catch (e) {
                    console.error('Error parsing SSE data:', e);
                }
            };

            eventSource.onerror = function(error) {
                console.error('SSE error:', error);
                eventSource.close();
            };

            // Read the response
            const reader = response.body.getReader();
            const decoder = new TextDecoder();

            while (true) {
                const {value, done} = await reader.read();
                if (done) {
                    console.log('Stream reading completed');
                    eventSource.close();
                    break;
                }
                
                const chunk = decoder.decode(value);
                console.log('Received chunk:', chunk);
                const lines = chunk.split('\n');
                
                lines.forEach(line => {
                    if (line.startsWith('data: ')) {
                        try {
                            const data = JSON.parse(line.slice(6));
                            console.log('Parsed progress data:', data);
                            updateProgress(data);
                        } catch (e) {
                            console.error('Error parsing progress data:', e, 'Raw line:', line);
                        }
                    }
                });
            }

            // Reset form and show success message
            form.reset();
            fileList.innerHTML = '<p class="text-gray-500 text-sm">No files selected</p>';
            progressContainer.classList.add('hidden');
            uploadButton.disabled = false;
            showSuccessMessage();

        } catch (error) {
            console.error('Upload error:', error);
            alert('An error occurred while uploading the files');
            uploadButton.disabled = false;
            progressContainer.classList.add('hidden');
        }
    });

    function updateProgress(data) {
        console.log('Updating progress with data:', data);
        const statusIndicator = document.getElementById('statusIndicator');
        const progressBar = document.getElementById('progressBar');
        
        // Log current state
        console.log('Progress elements state:', {
            container: {
                hidden: progressContainer.classList.contains('hidden'),
                display: progressContainer.style.display,
                height: progressContainer.offsetHeight,
                width: progressContainer.offsetWidth
            },
            progressBar: {
                width: progressBar.style.width,
                height: progressBar.offsetHeight,
                visible: progressBar.offsetParent !== null
            }
        });
        
        if (data.status === 'processing' || data.status === 'success') {
            const stage = data.stage || 'processing';
            const progress = typeof data.progress === 'number' ? Math.round(data.progress) : 0;
            
            console.log('Processing stage:', stage, 'Progress:', progress);
            
            // Update status indicator
            statusIndicator.className = 'w-3 h-3 rounded-full status-processing';
            
            // Ensure minimum width for visibility
            const newWidth = Math.max(2, progress);
            progressBar.style.width = `${newWidth}%`;
            console.log('New progress bar width:', progressBar.style.width);
            
            progressBar.classList.remove('complete');
            progressPercentage.textContent = `${progress}%`;
            progressStage.textContent = stageDescriptions[stage] || stage;

            // Force progress bar visibility
            progressBar.style.display = 'block';
            progressBar.style.opacity = '1';
            progressBar.style.visibility = 'visible';

            if (data.current && data.total) {
                const currentChunk = parseInt(data.current) || 0;
                const totalChunks = parseInt(data.total) || 1;
                const percentage = Math.round((currentChunk / totalChunks) * 100);
                console.log('Processing chunk:', currentChunk, 'of', totalChunks, 'Percentage:', percentage);
                progressDetails.innerHTML = `
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Processing chunk ${currentChunk} of ${totalChunks}
                `;
            }

            if (data.estimated_time) {
                const timeInSeconds = parseInt(data.estimated_time) || 0;
                estimatedTime.textContent = timeInSeconds > 0 ? `${formatTime(timeInSeconds)} remaining` : '';
            }
        } else if (data.status === 'completed' || data.status === 'success') {
            console.log('Processing completed');
            // Update status indicator
            statusIndicator.className = 'w-3 h-3 rounded-full status-completed';
            
            progressBar.style.width = '100%';
            progressBar.classList.add('complete');
            progressPercentage.textContent = '100%';
            progressStage.textContent = 'Processing completed';
            progressDetails.innerHTML = `
                <svg class="w-4 h-4 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                ${data.message || 'Processing completed successfully'}
            `;
            estimatedTime.textContent = '';
        } else if (data.status === 'error') {
            console.error('Processing error:', data.message);
            // Update status indicator
            statusIndicator.className = 'w-3 h-3 rounded-full status-error';
            
            progressStage.textContent = 'Error occurred';
            progressDetails.innerHTML = `
                <svg class="w-4 h-4 mr-2 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                ${data.message || 'An error occurred during processing'}
            `;
        }
    }

    function formatTime(seconds) {
        if (typeof seconds !== 'number' || isNaN(seconds)) return '';
        if (seconds < 60) return `${Math.round(seconds)}s`;
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = Math.round(seconds % 60);
        return `${minutes}m ${remainingSeconds}s`;
    }

    function showSuccessMessage() {
        const successMessage = document.getElementById('successMessage');
        successMessage.classList.remove('hidden', 'translate-x-full');
        successMessage.classList.add('translate-x-0');
        
        // Auto-hide after 5 seconds
        setTimeout(hideSuccessMessage, 5000);
    }

    function hideSuccessMessage() {
        const successMessage = document.getElementById('successMessage');
        successMessage.classList.add('translate-x-full');
        setTimeout(() => {
            successMessage.classList.add('hidden');
        }, 500);
    }
});
</script>
@endpush
@endsection 