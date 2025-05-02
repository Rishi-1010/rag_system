document.addEventListener('DOMContentLoaded', () => {
    const fileInput = document.getElementById('file-upload');
    const fileList = document.getElementById('file-list');
    const uploadBtn = document.getElementById('upload-btn');
    const progressSection = document.getElementById('progress-section');
    const progressBar = document.getElementById('progress-bar');
    const progressText = document.getElementById('progress-text');
    const statusMessages = document.getElementById('status-messages');

    let selectedFiles = [];

    // Handle file selection
    fileInput.addEventListener('change', (e) => {
        selectedFiles = Array.from(e.target.files);
        updateFileList();
        updateUploadButton();
    });

    // Update the file list display
    function updateFileList() {
        fileList.innerHTML = '';
        selectedFiles.forEach((file, index) => {
            const li = document.createElement('li');
            li.className = 'file-item';
            li.innerHTML = `
                <span class="file-name">${file.name}</span>
                <button class="remove-btn" data-index="${index}">×</button>
            `;
            fileList.appendChild(li);
        });
    }

    // Handle file removal
    fileList.addEventListener('click', (e) => {
        if (e.target.classList.contains('remove-btn')) {
            const index = parseInt(e.target.dataset.index);
            selectedFiles.splice(index, 1);
            updateFileList();
            updateUploadButton();
        }
    });

    // Update upload button state
    function updateUploadButton() {
        uploadBtn.disabled = selectedFiles.length === 0;
    }

    // Add status message
    function addStatusMessage(message, type = 'processing') {
        const div = document.createElement('div');
        div.className = `status-message ${type}`;
        div.textContent = message;
        statusMessages.appendChild(div);
        statusMessages.scrollTop = statusMessages.scrollHeight;
    }

    // Update progress
    function updateProgress(percent) {
        progressBar.style.width = `${percent}%`;
        progressText.textContent = `${percent}%`;
    }

    // Handle file upload
    uploadBtn.addEventListener('click', async () => {
        if (selectedFiles.length === 0) return;

        // Show progress section
        progressSection.classList.remove('hidden');
        statusMessages.innerHTML = '';
        updateProgress(0);

        try {
            for (let i = 0; i < selectedFiles.length; i++) {
                const file = selectedFiles[i];
                addStatusMessage(`Processing ${file.name}...`);

                // Create FormData
                const formData = new FormData();
                formData.append('file', file);
                formData.append('document_id', `doc_${Date.now()}_${i}`);

                // Send file to backend
                const response = await fetch('api/upload.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                
                if (!result.success) {
                    throw new Error(result.message || `Failed to process ${file.name}`);
                }

                addStatusMessage(`Successfully processed ${file.name}`, 'success');
                updateProgress(((i + 1) / selectedFiles.length) * 100);
            }

            addStatusMessage('All files processed successfully!', 'success');
        } catch (error) {
            addStatusMessage(`Error: ${error.message}`, 'error');
        }
    });
}); 