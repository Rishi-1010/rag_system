// Enhanced debug logging function with visual styling
function debugLog(message, data = null) {
    const timestamp = new Date().toISOString();
    const style = 'background: #2c3e50; color: #ecf0f1; padding: 2px 5px; border-radius: 3px;';
    const dataStyle = 'background: #34495e; color: #ecf0f1; padding: 2px 5px; border-radius: 3px;';
    
    console.log(`%c[${timestamp}] ${message}`, style);
    if (data) {
        console.log('%cData:', dataStyle, data);
    }
}

// Add visual separator in console
function logSeparator() {
    console.log('%c' + '='.repeat(50), 'color: #e74c3c; font-weight: bold;');
}

// Initialize file upload functionality
let retryCount = 0;
const MAX_RETRIES = 10; // Maximum number of retries

function initializeFileUpload() {
    debugLog('🔍 Looking for upload form and button...');
    debugLog('📝 Current document state:', {
        readyState: document.readyState,
        forms: Array.from(document.forms).map(f => ({ id: f.id, action: f.action })),
        body: document.body.innerHTML
    });
    
    const uploadForm = document.getElementById('uploadForm');
    
    if (!uploadForm) {
        retryCount++;
        if (retryCount >= MAX_RETRIES) {
            debugLog('❌ Upload form not found after maximum retries. Please check if the form exists with ID "uploadForm"');
            return;
        }
        debugLog(`❌ Upload form not found! Retry ${retryCount}/${MAX_RETRIES}...`);
        setTimeout(initializeFileUpload, 500); // Increased delay to 500ms
        return;
    }
    
    const uploadButton = document.getElementById('uploadButton');
    if (!uploadButton) {
        debugLog('❌ Upload button not found');
        return;
    }

    // Add project select change handler
    const projectSelect = document.getElementById('projectSelect');
    const selectedProjectId = document.getElementById('selectedProjectId');
    if (projectSelect && selectedProjectId) {
        projectSelect.addEventListener('change', function() {
            const selectedValue = this.value;
            debugLog('Project selected:', selectedValue);
            selectedProjectId.value = selectedValue;
            uploadButton.disabled = !selectedValue;
        });
    }
    
    debugLog('✅ Upload form and button found!');
    
    // Update form properties
    uploadForm.action = '/rag/upload';
    uploadForm.method = 'POST';
    uploadForm.enctype = 'multipart/form-data';
    
    debugLog('📝 Form details:', {
        id: uploadForm.id,
        action: uploadForm.action,
        method: uploadForm.method,
        enctype: uploadForm.enctype
    });
    debugLog('📝 Button details:', {
        id: uploadButton.id,
        type: uploadButton.type,
        disabled: uploadButton.disabled
    });
    
    retryCount = 0; // Reset retry count for future initializations
    
    // Remove any existing event listeners
    const newForm = uploadForm.cloneNode(true);
    uploadForm.parentNode.replaceChild(newForm, uploadForm);
    
    // Re-select the upload button from the new form
    const newUploadButton = newForm.querySelector('#uploadButton');
    
    debugLog('🔄 Attaching form submit event listener');
    
    newForm.addEventListener('submit', async function(e) {
        debugLog('🎯 Form submit event triggered');
        e.preventDefault();
        logSeparator();
        debugLog('🚀 Form submission started');
        
        const formData = new FormData(this);
        const alertContainer = document.getElementById('alert-container');
        
        // Log form data
        debugLog('📝 Form data contents:');
        for (let pair of formData.entries()) {
            console.log(`%c${pair[0]}:`, 'color: #3498db; font-weight: bold;', pair[1]);
        }
        
        // Disable submit button to prevent double submission
        if (newUploadButton) {
            newUploadButton.disabled = true;
            newUploadButton.innerHTML = 'Uploading...';
            debugLog('🔒 Upload button disabled');
        }
        
        try {
            debugLog('📤 Sending fetch request');
            const response = await fetch(this.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            debugLog('📥 Response received', {
                status: response.status,
                statusText: response.statusText,
                headers: Object.fromEntries(response.headers.entries())
            });

            // Check for HTTP errors before attempting to read the body
            if (!response.ok) {
                const errorBody = await response.text(); // Attempt to read error response body
                debugLog('❌ HTTP Error detected', { status: response.status, statusText: response.statusText, body: errorBody });
                throw new Error(`HTTP error! status: ${response.status}, statusText: ${response.statusText}, body: ${errorBody}`);
            }

            // Handle streaming SSE
            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';
            let completedResults = null;

            while (true) {
                const { value, done } = await reader.read();
                if (done) break;
                buffer += decoder.decode(value, { stream: true });

                // Split on double newlines (SSE event delimiter)
                let parts = buffer.split('\n\n');
                buffer = parts.pop(); // Save incomplete part for next chunk

                for (let part of parts) {
                    if (part.startsWith('data: ')) {
                        try {
                            const json = JSON.parse(part.slice(6));
                            debugLog('📦 SSE Progress Event', json);
                            // Handle progress event
                            updateProgress(json);
                            // If this is the final completion event, store results
                            if (json.status === 'completed' && json.results) {
                                completedResults = json.results;
                            }
                        } catch (e) {
                            console.error('Error parsing SSE JSON:', e, part);
                        }
                    }
                }
            }

            // Clear existing alerts
            if (alertContainer) {
                alertContainer.innerHTML = '';
                debugLog('🧹 Cleared existing alerts');
            } else {
                debugLog('⚠️ Alert container not found');
            }

            // If we have final results, process them for alerts
            if (completedResults && completedResults.length > 0) {
                debugLog(`📊 Processing ${completedResults.length} results`);
                completedResults.forEach((result, index) => {
                    debugLog(`📄 Processing result ${index + 1}`, result);
                    let alertClass, message;
                    if (result.status === 'duplicate') {
                        debugLog('⚠️ Duplicate file detected', result);
                        alertClass = 'bg-red-100 border-red-400 text-red-700';
                        message = result.message;
                    } else if (result.status === 'error') {
                        debugLog('❌ Error detected', result);
                        alertClass = 'bg-red-100 border-red-400 text-red-700';
                        message = result.message;
                    } else if (result.status === 'success') {
                        debugLog('✅ Success detected', result);
                        alertClass = 'bg-green-100 border-green-400 text-green-700';
                        message = result.message;
                    }
                    debugLog('🎨 Creating alert', { alertClass, message });
                    const alertDiv = document.createElement('div');
                    alertDiv.className = `${alertClass} border px-4 py-3 rounded relative mb-4`;
                    alertDiv.setAttribute('role', 'alert');
                    alertDiv.style.opacity = '1';
                    alertDiv.style.transition = 'opacity 0.5s ease-in-out';
                    // Add close button
                    const closeButton = document.createElement('button');
                    closeButton.className = 'absolute top-0 bottom-0 right-0 px-4 py-3';
                    closeButton.innerHTML = `
                        <svg class="fill-current h-6 w-6" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                            <title>Close</title>
                            <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
                        </svg>
                    `;
                    closeButton.onclick = function() {
                        debugLog('🔕 Alert closed by user');
                        alertDiv.style.opacity = '0';
                        setTimeout(() => alertDiv.remove(), 500);
                    };
                    const messageSpan = document.createElement('span');
                    messageSpan.className = 'block sm:inline';
                    messageSpan.textContent = message;
                    alertDiv.appendChild(messageSpan);
                    alertDiv.appendChild(closeButton);
                    if (alertContainer) {
                        alertContainer.appendChild(alertDiv);
                        debugLog('📌 Alert added to container');
                    } else {
                        debugLog('⚠️ Could not add alert - container not found');
                    }
                    // Only auto-remove success messages after 5 seconds
                    if (result.status === 'success') {
                        debugLog('⏱️ Setting auto-remove timer for success message');
                        setTimeout(() => {
                            debugLog('🗑️ Auto-removing success message');
                            alertDiv.style.opacity = '0';
                            setTimeout(() => alertDiv.remove(), 500);
                        }, 5000);
                    }
                });
            }

            if (completedResults && completedResults.length > 0 && completedResults.every(r => r.status === 'success')) {
                debugLog('🔄 All operations successful, resetting form');
                refreshUploadedFiles();
                this.reset();
            } else {
                debugLog('⚠️ Not resetting form due to errors or non-success status');
            }
        } catch (error) {
            debugLog('❌ Fetch error', error);
            console.error('Error:', error);
            const alertDiv = document.createElement('div');
            alertDiv.className = 'bg-red-100 border-red-400 text-red-700 px-4 py-3 rounded relative mb-4';
            alertDiv.setAttribute('role', 'alert');
            alertDiv.style.opacity = '1';
            alertDiv.style.transition = 'opacity 0.5s ease-in-out';
            // Add close button
            const closeButton = document.createElement('button');
            closeButton.className = 'absolute top-0 bottom-0 right-0 px-4 py-3';
            closeButton.innerHTML = `
                <svg class="fill-current h-6 w-6" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                    <title>Close</title>
                    <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
                </svg>
            `;
            closeButton.onclick = function() {
                debugLog('🔕 Network error alert closed by user');
                alertDiv.style.opacity = '0';
                setTimeout(() => alertDiv.remove(), 500);
            };
            const messageSpan = document.createElement('span');
            messageSpan.className = 'block sm:inline';
            messageSpan.textContent = 'An error occurred while uploading the file. Please try again.';
            alertDiv.appendChild(messageSpan);
            alertDiv.appendChild(closeButton);
            if (alertContainer) {
                alertContainer.appendChild(alertDiv);
                debugLog('📌 Network error alert added to container');
            } else {
                debugLog('⚠️ Could not add network error alert - container not found');
            }
        } finally {
            // Re-enable upload button
            if (newUploadButton) {
                newUploadButton.disabled = false;
                newUploadButton.innerHTML = 'Upload';
                debugLog('🔓 Upload button re-enabled');
            }
            logSeparator();
        }
    });
    
    debugLog('✅ Form submit event listener attached successfully');
    
    // Get file input and file list elements
    const fileInput = newForm.querySelector('input[type="file"]');
    const fileList = document.getElementById('fileList');
    
    if (!fileInput || !fileList) {
        debugLog('❌ File input or file list not found');
        return;
    }
    
    debugLog('📝 File input found:', {
        id: fileInput.id,
        name: fileInput.name,
        multiple: fileInput.multiple,
        accept: fileInput.accept
    });
    
    // Function to format file size
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    // Function to get file icon based on type
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
    
    // Function to update file list
    function updateFileList() {
        const files = fileInput.files;
        debugLog('📝 Updating file list with files:', files);
        
        fileList.innerHTML = '';
        if (!files || files.length === 0) {
            fileList.innerHTML = '<p class="text-gray-500 text-sm">No files selected</p>';
            newUploadButton.disabled = true;
            debugLog('🔒 Upload button disabled - no files selected');
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
        
        // Enable button if there are valid files and a project is selected
        const projectSelect = document.getElementById('projectSelect');
        newUploadButton.disabled = hasInvalidFiles || !projectSelect || !projectSelect.value;
        debugLog(`🔓 Upload button ${newUploadButton.disabled ? 'disabled' : 'enabled'} - ${hasInvalidFiles ? 'invalid files present' : 'all files valid'} and project ${projectSelect && projectSelect.value ? 'selected' : 'not selected'}`);
    }
    
    // Add file input change handler
    fileInput.addEventListener('change', updateFileList);
    
    // Add drag and drop handlers
    const dropZone = document.getElementById('dropZone');
    if (dropZone) {
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
    }
    
    // Add click handler for upload button
    newUploadButton.addEventListener('click', async function() {
        debugLog('🎯 Upload button clicked');
        
        const files = fileInput.files;
        if (!files || files.length === 0) {
            debugLog('⚠️ No files selected');
            return;
        }

        // Get project ID from the select element
        const projectSelect = document.getElementById('projectSelect');
        if (!projectSelect) {
            debugLog('⚠️ Project select element not found');
            alert('Please select a project first');
            return;
        }

        const projectId = projectSelect.value;
        if (!projectId) {
            debugLog('⚠️ No project selected');
            alert('Please select a project first');
            return;
        }

        debugLog('📤 Sending fetch request with project ID:', projectId);
        const formData = new FormData();
        
        // Add files to FormData
        Array.from(files).forEach(file => {
            formData.append('files[]', file);
        });
        
        // Add project_id to FormData
        formData.append('project_id', projectId);
        
        // Add CSRF token
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        
        try {
            // Show progress container
            const progressContainer = document.getElementById('progressContainer');
            if (progressContainer) {
                progressContainer.classList.remove('hidden');
                progressContainer.style.display = 'block';
                progressContainer.style.opacity = '1';
                progressContainer.style.visibility = 'visible';
            }

            // Update progress elements
            const progressBar = document.getElementById('progressBar');
            const progressStage = document.getElementById('progressStage');
            const progressPercentage = document.getElementById('progressPercentage');
            const progressDetails = document.getElementById('progressDetails');

            if (progressBar) progressBar.style.width = '2%';
            if (progressStage) progressStage.textContent = 'Initializing...';
            if (progressPercentage) progressPercentage.textContent = '0%';
            if (progressDetails) progressDetails.textContent = 'Starting upload...';

            // Disable upload button
            newUploadButton.disabled = true;

            const response = await fetch('/rag/upload', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                }
            });

            debugLog('📥 Response received');
            debugLog('Data:', {
                status: response.status,
                statusText: response.statusText,
                headers: Object.fromEntries(response.headers.entries())
            });

            if (!response.ok) {
                // Try to get detailed error message from response
                let errorMessage = `Server error (${response.status})`;
                try {
                    const errorData = await response.json();
                    errorMessage = errorData.message || errorData.error || errorMessage;
                } catch (e) {
                    // If we can't parse JSON, try to get text
                    try {
                        const text = await response.text();
                        if (text) errorMessage = text;
                    } catch (e2) {
                        // If all else fails, use status text
                        errorMessage = response.statusText || errorMessage;
                    }
                }
                throw new Error(errorMessage);
            }

            // Handle streaming response
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
                            debugLog('📦 SSE Progress Event', data);
                            
                            if (data.status === 'error') {
                                if (progressStage) progressStage.textContent = 'Error';
                                if (progressDetails) progressDetails.textContent = data.message;
                                throw new Error(data.message);
                            } else if (data.status === 'duplicate') {
                                if (progressStage) progressStage.textContent = 'Duplicate File';
                                if (progressDetails) progressDetails.textContent = data.message;
                            } else if (data.stage === 'validation') {
                                if (progressStage) progressStage.textContent = 'Validating...';
                                if (progressDetails) progressDetails.textContent = data.message;
                            } else if (data.stage === 'processing') {
                                if (progressStage) progressStage.textContent = 'Processing...';
                                if (progressDetails) progressDetails.textContent = data.message;
                                if (progressBar && data.progress) {
                                    progressBar.style.width = `${data.progress}%`;
                                }
                                if (progressPercentage && data.progress) {
                                    progressPercentage.textContent = `${data.progress}%`;
                                }
                            } else if (data.status === 'completed') {
                                if (progressStage) progressStage.textContent = 'Completed';
                                if (progressDetails) progressDetails.textContent = data.message;
                                if (progressBar) progressBar.style.width = '100%';
                                if (progressPercentage) progressPercentage.textContent = '100%';
                                
                                // Reset form and show success message
                                fileInput.value = '';
                                if (fileList) fileList.innerHTML = '<p class="text-gray-500 text-sm">No files selected</p>';
                                if (progressContainer) progressContainer.classList.add('hidden');
                                
                                // Show success message
                                const successMessage = document.getElementById('successMessage');
                                if (successMessage) {
                                    successMessage.classList.remove('hidden', 'translate-x-full');
                                    successMessage.classList.add('translate-x-0');
                                    setTimeout(() => {
                                        successMessage.classList.add('translate-x-full');
                                        setTimeout(() => {
                                            successMessage.classList.add('hidden');
                                        }, 500);
                                    }, 5000);
                                }
                                
                                refreshUploadedFiles();
                            }
                        } catch (e) {
                            console.error('Error parsing progress data:', e, 'Raw line:', line);
                            // Don't throw here, just log the error and continue
                        }
                    }
                }
            }

            debugLog('✅ Upload successful');
        } catch (error) {
            debugLog('❌ Upload failed:', error);
            
            // Show error message
            const errorMessage = document.getElementById('errorMessage');
            if (errorMessage) {
                const messageSpan = errorMessage.querySelector('span.ml-2');
                if (messageSpan) {
                    messageSpan.textContent = error.message || 'An error occurred while uploading the files.';
                }
                errorMessage.classList.remove('hidden', 'translate-x-full');
                errorMessage.classList.add('translate-x-0');
                setTimeout(() => {
                    errorMessage.classList.add('translate-x-full');
                    setTimeout(() => {
                        errorMessage.classList.add('hidden');
                    }, 500);
                }, 5000);
            } else {
                alert(error.message || 'An error occurred while uploading the files.');
            }

            // Reset progress container
            if (progressContainer) {
                progressContainer.classList.add('hidden');
            }
            if (progressBar) {
                progressBar.style.width = '0%';
            }
            if (progressStage) {
                progressStage.textContent = 'Error';
            }
            if (progressDetails) {
                progressDetails.textContent = error.message || 'Upload failed';
            }
        } finally {
            newUploadButton.disabled = false;
        }
    });
    
    // Add remove file function to window scope
    window.removeFile = function(fileName) {
        debugLog(`🗑️ Removing file: ${fileName}`);
        const dt = new DataTransfer();
        const files = fileInput.files;
        
        for (let i = 0; i < files.length; i++) {
            if (files[i].name !== fileName) {
                dt.items.add(files[i]);
            }
        }
        
        fileInput.files = dt.files;
        updateFileList();
    };
}

// Wait for both DOM and Vite to be ready
function waitForReady() {
    debugLog('⏳ Waiting for page to be ready...');
    
    // Check if we're in a browser environment
    if (typeof window === 'undefined') {
        debugLog('❌ Not in browser environment');
        return;
    }
    
    // Check if DOM is ready
    if (document.readyState === 'loading') {
        debugLog('⏳ DOM still loading, waiting for DOMContentLoaded event');
        document.addEventListener('DOMContentLoaded', () => {
            debugLog('✅ DOM loaded, initializing');
            setTimeout(initializeFileUpload, 1000); // Increased delay to 1 second
        });
    } else {
        debugLog('⚡ DOM already loaded, initializing with delay');
        setTimeout(initializeFileUpload, 1000); // Increased delay to 1 second
    }
}

// Start the initialization process
waitForReady();

// Add this function near the top or bottom of the file
function updateProgress(progress) {
    debugLog('🔄 Progress update:', progress);
    // Update progress stage text
    const progressStage = document.getElementById('progressStage');
    const progressPercentage = document.getElementById('progressPercentage');
    const progressBar = document.getElementById('progressBar');
    if (progressStage && progress.stage) {
        // Capitalize and prettify the stage
        progressStage.textContent = progress.stage.charAt(0).toUpperCase() + progress.stage.slice(1).replace(/_/g, ' ');
    }
    if (progressPercentage && typeof progress.progress === 'number') {
        progressPercentage.textContent = `${progress.progress}%`;
    }
    if (progressBar && typeof progress.progress === 'number') {
        progressBar.style.width = `${progress.progress}%`;
    }
} 

function refreshUploadedFiles() {
    fetch('/rag/files/data')
        .then(res => res.text())
        .then(html => {
            // Update the uploaded files section
            document.getElementById('uploadedFiles').innerHTML = html;
        });
}