<div class="admin-container">
    <div class="admin-header">
        <h2><?php echo htmlspecialchars($gallery['name']); ?></h2>
        <div class="header-actions">
            <a href="/gallery/<?php echo $gallery['id']; ?>" class="btn btn-outline" target="_blank">Ansehen</a>
            <a href="/admin" class="btn btn-secondary">Zurück</a>
        </div>
    </div>
    
    <?php if ($flash = $this->getFlash('success')): ?>
    <div class="alert alert-success">
        <?php echo htmlspecialchars($flash); ?>
    </div>
    <?php endif; ?>
    
    <?php if ($flash = $this->getFlash('error')): ?>
    <div class="alert alert-error">
        <?php echo htmlspecialchars($flash); ?>
    </div>
    <?php endif; ?>
    
    <div class="gallery-info">
        <div class="info-card">
            <h3>Galerie-Informationen</h3>
            <div class="info-grid">
                <div class="info-item">
                    <label>Status:</label>
                    <span><?php echo $gallery['is_public'] ? 'Öffentlich' : 'Privat'; ?></span>
                </div>
                <?php if ($gallery['client_email']): ?>
                <div class="info-item">
                    <label>Kunde:</label>
                    <span><?php echo htmlspecialchars($gallery['client_email']); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($gallery['access_code']): ?>
                <div class="info-item">
                    <label>Zugangscode:</label>
                    <span class="access-code"><?php echo htmlspecialchars($gallery['access_code']); ?></span>
                </div>
                <?php endif; ?>
                <div class="info-item">
                    <label>Link:</label>
                    <div class="share-link">
                        <input type="text" readonly value="<?php echo $_SERVER['HTTP_HOST']; ?>/gallery/<?php echo $gallery['id']; ?><?php echo $gallery['access_code'] ? '?code=' . $gallery['access_code'] : ''; ?>" id="share-link">
                        <button type="button" onclick="copyToClipboard('share-link')" class="btn btn-small">Kopieren</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="upload-section">
        <h3>Medien hochladen</h3>
        <div class="upload-area" id="upload-area">
            <input type="file" id="file-input" multiple accept="image/*,video/*" style="display: none;">
            <div class="upload-prompt">
                <div class="upload-icon">📁</div>
                <p>Dateien hier hinziehen oder <button type="button" onclick="document.getElementById('file-input').click()" class="btn-link">durchsuchen</button></p>
                <small>Unterstützt: JPG, PNG, GIF, WebP, MP4, MOV, AVI, WMV (max. 100MB pro Datei)</small>
            </div>
        </div>
        <div id="upload-progress" class="upload-progress" style="display: none;"></div>
        <div class="upload-tips">
            <h4>Upload-Tipps:</h4>
            <ul>
                <li>Sie können mehrere Dateien gleichzeitig hochladen</li>
                <li>Große Dateien werden automatisch komprimiert</li>
                <li>Videos werden für bessere Kompatibilität optimiert</li>
            </ul>
        </div>
    </div>
    
    <div class="media-section">
        <h3>Medien (<?php echo count($media); ?>)</h3>
        <?php if (empty($media)): ?>
        <div class="empty-state">
            <p>Noch keine Medien hochgeladen.</p>
        </div>
        <?php else: ?>
        <div class="media-grid">
            <?php foreach ($media as $item): ?>
            <div class="media-item">
                <?php if ($item['type'] === 'photo'): ?>
                <img src="/uploads/<?php echo htmlspecialchars($item['filename']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>">
                <?php else: ?>
                <video controls poster="<?php echo $item['poster_filename'] ? '/uploads/' . htmlspecialchars($item['poster_filename']) : ''; ?>">
                    <source src="/uploads/<?php echo htmlspecialchars($item['filename']); ?>" type="<?php echo htmlspecialchars($item['mime_type']); ?>">
                </video>
                <?php endif; ?>
                <div class="media-info">
                    <p><?php echo htmlspecialchars($item['title']); ?></p>
                    <small><?php echo date('d.m.Y H:i', strtotime($item['uploaded_at'])); ?></small>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
const galleryId = <?php echo $gallery['id']; ?>;
</script>