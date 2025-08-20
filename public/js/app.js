// Mobile menu functionality
function toggleMobileMenu() {
    const navMenu = document.getElementById('nav-menu');
    if (navMenu) {
        navMenu.classList.toggle('active');
    }
}

// Close mobile menu when clicking outside
document.addEventListener('click', function(event) {
    const navMenu = document.getElementById('nav-menu');
    const menuToggle = document.querySelector('.mobile-menu-toggle');
    
    if (navMenu && menuToggle && 
        !navMenu.contains(event.target) && 
        !menuToggle.contains(event.target)) {
        navMenu.classList.remove('active');
    }
});

// Enhanced touch support for media items
document.addEventListener('DOMContentLoaded', function() {
    // Add touch event handling for better mobile experience
    const mediaItems = document.querySelectorAll('.media-item');
    
    mediaItems.forEach(item => {
        // Add touch feedback
        item.addEventListener('touchstart', function() {
            this.style.opacity = '0.8';
        });
        
        item.addEventListener('touchend', function() {
            this.style.opacity = '1';
        });
        
        item.addEventListener('touchcancel', function() {
            this.style.opacity = '1';
        });
    });
    
    // Prevent zoom on double tap for upload area
    const uploadArea = document.getElementById('upload-area');
    if (uploadArea) {
        uploadArea.addEventListener('touchend', function(event) {
            event.preventDefault();
        });
    }
});

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
        
        // Attach CSRF token
        let csrfToken = '';
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta) {
            csrfToken = csrfMeta.getAttribute('content');
        }
        if (csrfToken) {
            formData.append('csrf_token', csrfToken);
        }
        
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
                try {
                    const results = JSON.parse(xhr.responseText);
                    displayUploadResults(results);
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } catch (e) {
                    console.error('JSON Parse Error:', e);
                    console.error('Response Text:', xhr.responseText);
                    uploadProgress.innerHTML = '<div class="alert alert-error">Upload-Fehler: Ungültige Antwort vom Server</div>';
                }
            } else {
                console.error('HTTP Status:', xhr.status);
                console.error('Response Text:', xhr.responseText);
                let errorMsg = 'Upload-Fehler aufgetreten';
                if (xhr.status === 413) {
                    errorMsg = 'Datei(en) zu groß';
                } else if (xhr.status === 403) {
                    errorMsg = 'Keine Berechtigung';
                } else if (xhr.status === 404) {
                    errorMsg = 'Upload-Endpunkt nicht gefunden';
                }
                uploadProgress.innerHTML = '<div class="alert alert-error">' + errorMsg + ' (Status: ' + xhr.status + ')</div>';
            }
        });
        
        xhr.addEventListener('error', function() {
            console.error('Network Error occurred');
            uploadProgress.innerHTML = '<div class="alert alert-error">Netzwerk-Fehler: Verbindung zum Server fehlgeschlagen</div>';
        });
        
        const uploadUrl = `/admin/galleries/${galleryId}/upload`;
        console.log('Upload URL:', uploadUrl);
        console.log('CSRF Token:', csrfToken);
        
        xhr.open('POST', uploadUrl);
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

// Touch gesture support for lightbox
document.addEventListener('DOMContentLoaded', function() {
    const lightbox = document.getElementById('lightbox');
    if (!lightbox) return;
    
    let startX = 0;
    let startY = 0;
    let currentX = 0;
    let currentY = 0;
    let isDragging = false;
    
    lightbox.addEventListener('touchstart', function(e) {
        if (e.touches.length === 1) {
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
            isDragging = true;
        }
    });
    
    lightbox.addEventListener('touchmove', function(e) {
        if (!isDragging || e.touches.length !== 1) return;
        
        currentX = e.touches[0].clientX;
        currentY = e.touches[0].clientY;
        
        const diffX = currentX - startX;
        const diffY = currentY - startY;
        
        // Prevent scrolling during swipe
        if (Math.abs(diffX) > Math.abs(diffY)) {
            e.preventDefault();
        }
    });
    
    lightbox.addEventListener('touchend', function(e) {
        if (!isDragging) return;
        
        const diffX = currentX - startX;
        const diffY = currentY - startY;
        const minSwipeDistance = 50;
        
        // Horizontal swipe for navigation
        if (Math.abs(diffX) > minSwipeDistance && Math.abs(diffX) > Math.abs(diffY)) {
            if (diffX > 0) {
                navigateLightbox(-1); // Swipe right = previous
            } else {
                navigateLightbox(1);  // Swipe left = next
            }
        }
        // Vertical swipe down to close
        else if (diffY > minSwipeDistance && diffY > Math.abs(diffX)) {
            closeLightbox();
        }
        
        isDragging = false;
        startX = 0;
        startY = 0;
        currentX = 0;
        currentY = 0;
    });
    
    // Pinch to zoom (basic implementation)
    let initialDistance = 0;
    let currentScale = 1;
    
    lightbox.addEventListener('touchstart', function(e) {
        if (e.touches.length === 2) {
            const touch1 = e.touches[0];
            const touch2 = e.touches[1];
            initialDistance = Math.hypot(
                touch2.clientX - touch1.clientX,
                touch2.clientY - touch1.clientY
            );
        }
    });
    
    lightbox.addEventListener('touchmove', function(e) {
        if (e.touches.length === 2) {
            e.preventDefault();
            const touch1 = e.touches[0];
            const touch2 = e.touches[1];
            const currentDistance = Math.hypot(
                touch2.clientX - touch1.clientX,
                touch2.clientY - touch1.clientY
            );
            
            if (initialDistance > 0) {
                const scale = currentDistance / initialDistance;
                currentScale = Math.min(Math.max(0.5, scale), 3);
                
                const lightboxMedia = lightbox.querySelector('.lightbox-media img, .lightbox-media video');
                if (lightboxMedia) {
                    lightboxMedia.style.transform = `scale(${currentScale})`;
                }
            }
        }
    });
    
    lightbox.addEventListener('touchend', function(e) {
        if (e.touches.length === 0) {
            initialDistance = 0;
            currentScale = 1;
            const lightboxMedia = lightbox.querySelector('.lightbox-media img, .lightbox-media video');
            if (lightboxMedia) {
                lightboxMedia.style.transform = 'scale(1)';
            }
        }
    });
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

// Delete media functionality
function deleteMedia(mediaId) {
    if (!confirm('Sind Sie sicher, dass Sie diese Datei löschen möchten? Diese Aktion kann nicht rückgängig gemacht werden.')) {
        return;
    }
    
    // Get CSRF token from meta tag or form
    let csrfToken = '';
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (csrfMeta) {
        csrfToken = csrfMeta.getAttribute('content');
    } else {
        // Try to get from existing form
        const csrfInput = document.querySelector('input[name="csrf_token"]');
        if (csrfInput) {
            csrfToken = csrfInput.value;
        }
    }
    
    // Create form data
    const formData = new FormData();
    formData.append('csrf_token', csrfToken);
    
    // Show loading state
    const mediaItem = document.querySelector(`[data-media-id="${mediaId}"]`);
    const deleteButton = mediaItem.querySelector('.btn-danger');
    const originalText = deleteButton.textContent;
    deleteButton.textContent = 'Wird gelöscht...';
    deleteButton.disabled = true;
    
    // Send delete request
    fetch(`/media/${mediaId}/delete`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove the media item from the DOM
            mediaItem.remove();
            
            // Update media count in header
            const mediaSection = document.querySelector('.media-section h3');
            if (mediaSection) {
                const currentCount = parseInt(mediaSection.textContent.match(/\d+/)[0]);
                mediaSection.textContent = `Medien (${currentCount - 1})`;
            }
            
            // Show success message if upload limits exist
            const limitInfo = document.querySelector('.upload-limits-info');
            if (limitInfo && data.freed_size_formatted) {
                showTemporaryMessage(`Datei gelöscht! ${data.freed_size_formatted} Speicherplatz wurde freigegeben.`, 'success');
                
                // Reload page after 2 seconds to update limits
                setTimeout(() => {
                    location.reload();
                }, 2000);
            }
        } else {
            // Show error message
            showTemporaryMessage(data.error || 'Fehler beim Löschen der Datei', 'error');
            
            // Restore button state
            deleteButton.textContent = originalText;
            deleteButton.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showTemporaryMessage('Netzwerkfehler beim Löschen der Datei', 'error');
        
        // Restore button state
        deleteButton.textContent = originalText;
        deleteButton.disabled = false;
    });
}

// Show temporary message
function showTemporaryMessage(message, type) {
    const messageDiv = document.createElement('div');
    messageDiv.className = `alert alert-${type}`;
    messageDiv.textContent = message;
    messageDiv.style.position = 'fixed';
    messageDiv.style.top = '20px';
    messageDiv.style.right = '20px';
    messageDiv.style.zIndex = '9999';
    messageDiv.style.maxWidth = '400px';
    
    document.body.appendChild(messageDiv);
    
    setTimeout(() => {
        messageDiv.remove();
    }, 5000);
}