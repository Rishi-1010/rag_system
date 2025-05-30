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

    <!-- Error Message Notification -->
    <div id="errorMessage" class="hidden fixed top-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded shadow-lg z-50 transition-all duration-500 transform translate-x-full" role="alert">
    <div class="flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            <span class="font-medium">Error!</span>
            <span class="ml-2">An error occurred while processing your request.</span>
            <button class="ml-auto" onclick="hideErrorMessage()">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>
    <div id="alert-container" class="mt-4"></div>
    <div class="max-w-6xl mx-auto mt-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

            <!-- Left Column: Project Select/Add -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-bold mb-4">Manage Project</h2>

                <div class="mb-4">
                    <label for="projectSelect" class="block text-sm font-medium text-gray-700">Select Project</label>
                    <div class="flex items-center space-x-4 mt-2">
                        <select id="projectSelect" name="project_id" class="form-select block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">-- Select a Project --</option>
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}" data-project-name="{{ $project->name }}">{{ $project->name }}</option>
                            @endforeach
                        </select>
                        <button type="button" id="addProjectButton" class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition duration-200">
                            Add
                        </button>
                        <button type="button" id="removeProjectButton" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition duration-200 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                            Remove
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right Column: Document Upload -->
            <div class="md:col-span-2 bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-2xl font-bold mb-6">Upload Documents</h2>
                
                <form id="uploadForm" class="space-y-4">
                    @csrf
                    <input type="hidden" id="selectedProjectId" name="project_id" value="">
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

        <!-- Uploaded Files List -->
        <div id="uploadedFiles" class="bg-white rounded-lg shadow-lg p-6 mt-8">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold">Previously Uploaded Files</h3>

                {{-- <input
                    type="text"
                    id="searchInput"
                    placeholder="Search files..."
                    class="border border-gray-300 rounded-full px-4 py-2 w-64
                        focus:outline-none focus:ring-2 focus:ring-blue-500
                        transition duration-200 ease-in-out"
                    autocomplete="off"
                /> --}}
            </div>

            <div id="filesList">
                @if($uploadedFiles->isEmpty())
                    <p class="text-sm text-gray-500">No files have been uploaded yet.</p>
                @else
                    <ul class="space-y-2">
                        @foreach($uploadedFiles as $file)
                            <li class="flex items-center justify-between bg-gray-100 rounded px-4 py-2 shadow-sm">
                                <div>
                                    <p class="text-base font-medium">{{ $file->original_name }}</p>
                                    <p class="text-sm text-gray-500">
                                        Size: {{ number_format($file->size / 1024, 2) }} KB |
                                        Uploaded: {{ $file->created_at->format('Y-m-d H:i') }} |
                                        <span class="font-semibold text-gray-700">Project:</span>
                                        {{ $file->project->name ?? 'N/A' }}
                                    </p>
                                </div>
                                <div class="flex gap-2">
                                    <button class="text-red-600 text-sm hover:underline delete-file-btn" data-filename="{{ $file->original_name }}">
                                        Delete
                                    </button>
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    {{-- Pagination links --}}
                    <div class="mt-4">
                        {{ $uploadedFiles->links() }}
                    </div>
                @endif
            </div>
        </div>


    </div>
</div>

<!-- Modal for Adding a New Project -->
<div id="addProjectModal" class="hidden fixed inset-0 bg-gray-800 bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-lg p-6 w-96">
        <h3 class="text-lg font-semibold mb-4">Add New Project</h3>
        <form id="addProjectForm">
            @csrf
            <div class="mb-4">
                <label for="projectName" class="block text-sm font-medium text-gray-700">Project Name</label>
                <input type="text" id="projectName" name="name" class="form-input mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" id="cancelAddProject" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition duration-200">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition duration-200">Save</button>
            </div>
        </form>
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
    // Initialize form elements
    const form = document.getElementById('uploadForm');
    const fileInput = form.querySelector('input[type="file"]');
    const uploadButton = document.getElementById('uploadButton');
    const fileList = document.getElementById('fileList');
    const progressContainer = document.getElementById('progressContainer');
    const progressBar = document.getElementById('progressBar');
    const progressStage = document.getElementById('progressStage');
    const progressPercentage = document.getElementById('progressPercentage');
    const progressDetails = document.getElementById('progressDetails');
    const projectSelect = document.getElementById('projectSelect');
    const selectedProjectId = document.getElementById('selectedProjectId');

    // Initialize project selection
    if (projectSelect) {
        projectSelect.addEventListener('change', function() {
            const selectedValue = this.value;
            selectedProjectId.value = selectedValue;
            uploadButton.disabled = !selectedValue || !fileInput.files.length;
        });
    }

    // Handle file selection
    fileInput.addEventListener('change', function() {
        const files = this.files;
        updateFileList(files);
        uploadButton.disabled = !files.length || !selectedProjectId.value;
    });

    // Update file list display
    function updateFileList(files) {
        fileList.innerHTML = '';
        if (!files || files.length === 0) {
            fileList.innerHTML = '<p class="text-gray-500 text-sm">No files selected</p>';
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

        uploadButton.disabled = hasInvalidFiles || !selectedProjectId.value;
    }

    // Handle file upload
    uploadButton.addEventListener('click', async function() {
        const files = fileInput.files;
        if (!files || files.length === 0) {
            alert('Please select at least one file');
            return;
        }

        const projectId = selectedProjectId.value;
        if (!projectId) {
            alert('Please select a project first');
            return;
        }

        // Show progress container
        progressContainer.classList.remove('hidden');
        progressContainer.style.display = 'block';
        progressContainer.style.opacity = '1';
        progressContainer.style.visibility = 'visible';

        uploadButton.disabled = true;
        progressBar.style.width = '2%';
        progressStage.textContent = 'Initializing...';
        progressPercentage.textContent = '0%';

        const formData = new FormData();
        Array.from(files).forEach(file => {
            formData.append('files[]', file);
        });
        formData.append('project_id', projectId);

        try {
            const response = await fetch('/rag/upload', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';

            while (true) {
                const {value, done} = await reader.read();
                if (done) break;
                
                buffer += decoder.decode(value, { stream: true });
                const lines = buffer.split('\n\n');
                buffer = lines.pop() || '';

                for (const line of lines) {
                    if (line.startsWith('data: ')) {
                        try {
                            const data = JSON.parse(line.slice(6));
                            
                            if (data.status === 'error') {
                                progressStage.textContent = 'Error';
                                progressDetails.textContent = data.message;
                                throw new Error(data.message);
                            } else if (data.status === 'duplicate') {
                                progressStage.textContent = 'Duplicate File';
                                progressDetails.textContent = data.message;
                            } else if (data.stage === 'validation') {
                                progressStage.textContent = 'Validating...';
                                progressDetails.textContent = data.message;
                            } else if (data.stage === 'processing') {
                                progressStage.textContent = 'Processing...';
                                progressDetails.textContent = data.message;
                                progressBar.style.width = `${data.progress || 0}%`;
                                progressPercentage.textContent = `${data.progress || 0}%`;
                            } else if (data.status === 'completed') {
                                progressStage.textContent = 'Completed';
                                progressDetails.textContent = data.message;
                                progressBar.style.width = '100%';
                                progressPercentage.textContent = '100%';
                                
                                fileInput.value = '';
                                fileList.innerHTML = '<p class="text-gray-500 text-sm">No files selected</p>';
                                progressContainer.classList.add('hidden');
                                uploadButton.disabled = false;
                                showSuccessMessage();
                                refreshUploadedFiles();
                            }
                        } catch (e) {
                            console.error('Error parsing progress data:', e, 'Raw line:', line);
                        }
                    }
                }
            }
        } catch (error) {
            console.error('Upload error:', error);
            progressStage.textContent = 'Error';
            progressDetails.textContent = error.message;
            alert('An error occurred while uploading the files: ' + error.message);
            uploadButton.disabled = false;
            progressContainer.classList.add('hidden');
        }
    });

    // Helper functions
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

    // Add remove file function to window scope
    window.removeFile = function(fileName) {
        const dt = new DataTransfer();
        const files = fileInput.files;
        
        for (let i = 0; i < files.length; i++) {
            if (files[i].name !== fileName) {
                dt.items.add(files[i]);
            }
        }
        
        fileInput.files = dt.files;
        updateFileList(fileInput.files);
    };

    // Add form submit handler
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        uploadButton.click();
    });

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

    function showErrorMessage(message) {
        const errorMessage = document.getElementById('errorMessage');
        errorMessage.querySelector('span.ml-2').textContent = message || 'An error occurred.';
        errorMessage.classList.remove('hidden', 'translate-x-full');
        errorMessage.classList.add('translate-x-0');

        // Auto-hide after 5 seconds
        setTimeout(hideErrorMessage, 5000);
    }

    function hideErrorMessage() {
        const errorMessage = document.getElementById('errorMessage');
        errorMessage.classList.add('translate-x-full');
        setTimeout(() => {
            errorMessage.classList.add('hidden');
        }, 500);
    }

    function refreshUploadedFiles() {
        fetch('/rag/files/data')
            .then(res => res.text())
            .then(html => {
                // Update the uploaded files section
                document.getElementById('uploadedFiles').innerHTML = html;
            })
            .catch(error => {
                console.error('Error refreshing files:', error);
            });
    }
    
    document.addEventListener('click', function (e) {
        if (e.target && e.target.classList.contains('delete-file-btn')) {
            const button = e.target;
            const fileName = button.getAttribute('data-filename');

            Swal.fire({
                title: 'Are you sure?',
                text: `This will permanently delete "${fileName}".`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e3342f',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    deleteFile(fileName);
                }
            });
        }
    });

    function deleteFile(fileName) {
        fetch('/rag/delete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ fileName: fileName })
        })
        .then(res => res.json())
        .then(data => {
            if (data.message) {
                Swal.fire('Deleted!', data.message, 'success');
                refreshUploadedFiles();
            } else {
                Swal.fire('Error', data.error || 'Something went wrong.', 'error');
            }
        })
        .catch(error => {
            console.error(error);
            Swal.fire('Error', 'Something went wrong.', 'error');
        });
    }

    addProjectButton.addEventListener('click', () => {
        addProjectModal.classList.remove('hidden');
    });

    cancelAddProject.addEventListener('click', () => {
        addProjectModal.classList.add('hidden');
    });

    addProjectForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(addProjectForm);

        try {
            const response = await fetch('/rag/projects', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            });

            const data = await response.json();
            if (data.project) {
                const option = document.createElement('option');
                option.value = data.project.id;
                option.textContent = data.project.name;
                projectSelect.appendChild(option);
                projectSelect.value = data.project.id;
                // Clear the form fields
                addProjectForm.reset();
                addProjectModal.classList.add('hidden');

                // / Update success message content
                const successMessage = document.getElementById('successMessage');
                successMessage.querySelector('span.ml-2').textContent = `Project "${data.project.name}" has been added successfully.`;

                // Show success message
                showSuccessMessage();
            } else if (data.error) {
                // Clear the form fields
                addProjectForm.reset();
                addProjectModal.classList.add('hidden');
                // Show error message
                showErrorMessage(data.error);
            }
        } catch (error) {
            console.error('Error adding project:', error);
            showErrorMessage('An unexpected error occurred. Please try again.');
        }
    });

    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            let query = this.value;
            
            fetch(`{{ route('home') }}?search=${encodeURIComponent(query)}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('filesList').innerHTML = html;
                })
                .catch(error => {
                    console.error('Search failed:', error);
                });
        });
    }

    if (removeProjectButton) {
        removeProjectButton.addEventListener('click', async function() {
            const projectSelect = document.getElementById('projectSelect');
            const selectedOption = projectSelect.options[projectSelect.selectedIndex];
            
            if (!selectedOption || !selectedOption.value) {
                showErrorMessage('Please select a project to remove');
                return;
            }

            const projectId = selectedOption.value;
            const projectName = selectedOption.textContent;

            // Show confirmation dialog
            const result = await Swal.fire({
                title: 'Are you sure?',
                text: `This will permanently delete the project "${projectName}" and all its associated files.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e3342f',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!'
            });

            if (result.isConfirmed) {
                try {
                    const response = await fetch(`/rag/projects/${projectId}`, {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    });

                    const data = await response.json();

                    if (response.ok) {
                        // Remove the option from select
                        selectedOption.remove();
                        
                        // Reset the select if no options left
                        if (projectSelect.options.length <= 1) {
                            projectSelect.value = '';
                            removeProjectButton.disabled = true;
                        }

                        // Show success message
                        showSuccessMessage(`Project "${projectName}" has been deleted successfully.`);
                        
                        // Refresh the page after a short delay
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        throw new Error(data.message || 'Failed to delete project');
                    }
                } catch (error) {
                    console.error('Error deleting project:', error);
                    showErrorMessage(error.message || 'Failed to delete project. Please try again.');
                }
            }
        });
    }

    // Update remove button state when project selection changes
    if (projectSelect) {
        projectSelect.addEventListener('change', function() {
            if (removeProjectButton) {
                removeProjectButton.disabled = !this.value;
            }
        });
    }
});
</script>
@endpush
@endsection 