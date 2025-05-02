document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('uploadForm');
    const fileInput = document.getElementById('fileInput');
    const uploadButton = document.getElementById('uploadButton');
    const fileList = document.getElementById('fileList');
    const statusMessage = document.getElementById('statusMessage');
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');

    // Initially disable upload button
    uploadButton.disabled = true;

    // Handle file selection
    fileInput.addEventListener('change', function() {
        const files = Array.from(this.files);
        updateFileList(files);
        uploadButton.disabled = files.length === 0;
    });

    // Display selected files
    function updateFileList(files) {
        fileList.innerHTML = '';
        files.forEach(file => {
            const li = document.createElement('li');
            li.className = 'flex items-center justify-between p-2 bg-gray-50 rounded mb-2';
            li.innerHTML = `
                <span class="text-sm text-gray-700">${file.name}</span>
                <span class="text-sm text-gray-500">${formatFileSize(file.size)}</span>
            `;
            fileList.appendChild(li);
        });
    }

    // Format file size
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Show status message
    function showStatus(message, type = 'info') {
        statusMessage.textContent = message;
        statusMessage.className = `status-message ${type}`;
        statusMessage.style.display = 'block';
    }

    // Update progress
    function updateProgress(percent) {
        progressBar.style.width = `${percent}%`;
        progressText.textContent = `${percent}%`;
    }

    // Handle file upload
    uploadButton.addEventListener('click', async function() {
        const files = fileInput.files;
        if (files.length === 0) {
            showStatus('Please select at least one file', 'error');
            return;
        }

        // Disable the upload button and show progress
        uploadButton.disabled = true;
        updateProgress(0);
        showStatus('Uploading files...', 'info');

        const formData = new FormData(form);
        
        try {
            const response = await fetch('/rag/upload', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.error || 'Upload failed');
            }

            // Update the file list with results
            fileList.innerHTML = '';
            result.results.forEach(file => {
                const li = document.createElement('li');
                li.className = `flex items-center justify-between p-2 rounded mb-2 ${file.status === 'success' ? 'bg-green-50' : 'bg-red-50'}`;
                li.innerHTML = `
                    <span class="text-sm ${file.status === 'success' ? 'text-green-700' : 'text-red-700'}">${file.name}</span>
                    <span class="text-sm ${file.status === 'success' ? 'text-green-600' : 'text-red-600'}">${file.message}</span>
                `;
                fileList.appendChild(li);
            });

            showStatus(result.message, 'success');
            updateProgress(100);
        } catch (error) {
            console.error('Error:', error);
            showStatus(error.message, 'error');
            updateProgress(0);
        } finally {
            // Clear the file input and reset button
            fileInput.value = '';
            uploadButton.disabled = true;
        }
    });
}); 