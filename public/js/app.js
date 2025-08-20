// Upload functionality
document.addEventListener('DOMContentLoaded', function() {
    const uploadArea = document.getElementById('upload-area');
    const fileInput = document.getElementById('file-input');
    const uploadProgress = document.getElementById('upload-progress');
    
    if (uploadArea && fileInput) {
        // Drag and drop
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            const files = e.dataTransfer.files;
            handleFiles(files);
        });
        
        // File input change
        fileInput.addEventListener('change', function(e) {
            handleFiles(e.target.files);
        });
    }
    
    function handleFiles(files) {
        if (!files.length) return;
        
        const formData = new FormData();
        for (let i = 0; i < files.length; i++) {
            formData.append('files[]', files[i]);
        }
        
        uploadFiles(formData);
    }
    
    function uploadFiles(formData) {
        uploadProgress.style.display = 'block';
        uploadProgress.innerHTML = '<div class="upload-item"><div class="progress-bar"><div class="progress-fill"></div></div><span>Wird hochgeladen...</span></div>';
        
        const xhr = new XMLHttpRequest();
        
        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                const percentComplete = (e.loaded / e.total) * 100;
                const progressFill = uploadProgress.querySelector('.progress-fill');
                if (progressFill) {
                    progressFill.style.width = percentComplete + '%';
                }
            }
        });
        
        xhr.addEventListener('load', function() {
            if (xhr.status === 200) {
                const results = JSON.parse(xhr.responseText);
                displayUploadResults(results);
                setTimeout(() => {
                    location.reload();
                }, 2000);
            } else {
                uploadProgress.innerHTML = '<div class="alert alert-error">Upload-Fehler aufgetreten</div>';
            }
        });
        
        xhr.addEventListener('error', function() {
            uploadProgress.innerHTML = '<div class="alert alert-error">Upload-Fehler aufgetreten</div>';
        });
        
        xhr.open('POST', `/admin/galleries/${galleryId}/upload`);
        xhr.send(formData);
    }
    
    function displayUploadResults(response) {
        let html = '';
        
        // Show summary if available
        if (response.summary) {
            const summary = response.summary;
            html += `<div class="upload-summary">
                <h4>Upload-Zusammenfassung</h4>
                <p><strong>${summary.success}</strong> von <strong>${summary.total}</strong> Dateien erfolgreich hochgeladen</p>
                <p>Gesamtgröße: <strong>${summary.totalSize}</strong></p>
            </div>`;
        }
        
        // Show individual results
        const results = response.results || response;
        results.forEach(result => {
            if (result.success) {
                let details = '';
                if (result.size) details += ` (${result.size})`;
                if (result.dimensions) details += ` - ${result.dimensions}`;
                html += `<div class="upload-result success">
                    <strong>${result.file}</strong> - Erfolgreich hochgeladen${details}
                </div>`;
            } else {
                html += `<div class="upload-result error">
                    <strong>${result.file}</strong> - <span class="error-msg">${result.error}</span>
                </div>`;
            }
        });
        
        uploadProgress.innerHTML = html;
    }
});

// Gallery lightbox functionality
function openLightbox(mediaItem) {
    const lightbox = document.getElementById('lightbox');
    const lightboxMedia = document.querySelector('.lightbox-media');
    const lightboxTitle = document.getElementById('lightbox-title');
    const lightboxDate = document.getElementById('lightbox-date');
    
    currentMediaIndex = mediaItems.findIndex(item => item.id === mediaItem.id);
    
    if (mediaItem.type === 'photo') {
        lightboxMedia.innerHTML = `<img src="/uploads/${mediaItem.filename}" alt="${mediaItem.title}">`;
    } else {
        lightboxMedia.innerHTML = `<video controls><source src="/uploads/${mediaItem.filename}" type="${mediaItem.mime_type}"></video>`;
    }
    
    lightboxTitle.textContent = mediaItem.title || 'Unbenannt';
    lightboxDate.textContent = new Date(mediaItem.uploaded_at).toLocaleDateString('de-DE');
    
    lightbox.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeLightbox() {
    const lightbox = document.getElementById('lightbox');
    lightbox.style.display = 'none';
    document.body.style.overflow = '';
}

function navigateLightbox(direction) {
    currentMediaIndex += direction;
    
    if (currentMediaIndex < 0) {
        currentMediaIndex = mediaItems.length - 1;
    } else if (currentMediaIndex >= mediaItems.length) {
        currentMediaIndex = 0;
    }
    
    openLightbox(mediaItems[currentMediaIndex]);
}

// Keyboard navigation
document.addEventListener('keydown', function(e) {
    const lightbox = document.getElementById('lightbox');
    if (lightbox && lightbox.style.display === 'flex') {
        switch(e.key) {
            case 'Escape':
                closeLightbox();
                break;
            case 'ArrowLeft':
                navigateLightbox(-1);
                break;
            case 'ArrowRight':
                navigateLightbox(1);
                break;
        }
    }
});

// Copy to clipboard functionality
function copyToClipboard(elementId) {
    const element = document.getElementById(elementId);
    element.select();
    document.execCommand('copy');
    
    // Show feedback
    const button = element.nextElementSibling;
    const originalText = button.textContent;
    button.textContent = 'Kopiert!';
    setTimeout(() => {
        button.textContent = originalText;
    }, 2000);
}