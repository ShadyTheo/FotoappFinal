<div class="admin-container">
    <div class="admin-header">
        <h2>Benutzer bearbeiten</h2>
        <a href="/admin/users" class="btn btn-secondary">Zurück</a>
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
    
    <form method="POST" action="/admin/users/<?php echo $user['id']; ?>" class="user-form">
        <div class="form-group">
            <label for="email">E-Mail:</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="password">Neues Passwort (optional):</label>
            <input type="password" id="password" name="password">
            <small>Leer lassen, um das bestehende Passwort zu behalten</small>
        </div>
        
        <div class="form-group">
            <label>Galerien zuweisen:</label>
            <div class="checkbox-grid">
                <?php if (empty($galleries)): ?>
                <p class="text-muted">Keine Galerien verfügbar. <a href="/admin/galleries/create">Erstellen Sie zuerst eine Galerie</a>.</p>
                <?php else: ?>
                <?php foreach ($galleries as $gallery): ?>
                <label class="checkbox-label">
                    <input type="checkbox" name="gallery_ids[]" value="<?php echo $gallery['id']; ?>" 
                           <?php echo in_array($gallery['id'], $assignedGalleryIds) ? 'checked' : ''; ?>>
                    <?php echo htmlspecialchars($gallery['name']); ?>
                    <span class="gallery-meta">
                        <?php if ($gallery['is_public']): ?>
                        <span class="status-public">Öffentlich</span>
                        <?php elseif ($gallery['access_code']): ?>
                        <span class="status-code">Mit Code</span>
                        <?php endif; ?>
                    </span>
                </label>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <small>Zugewiesene Galerien sind für den Benutzer nach der Anmeldung automatisch zugänglich</small>
        </div>
        
        <!-- Upload Statistics Section -->
        <?php if (!empty($userGalleryStats)): ?>
        <div class="form-group">
            <h3>Upload-Statistiken</h3>
            <div class="upload-stats-grid">
                <?php foreach ($userGalleryStats as $galleryStat): ?>
                <div class="gallery-stat-card">
                    <h4><?php echo htmlspecialchars($galleryStat['gallery_name']); ?></h4>
                    <div class="stat-row">
                        <div class="stat-item">
                            <span class="stat-label">Dateien:</span>
                            <span class="stat-value"><?php echo $galleryStat['file_count']; ?> / 5</span>
                            <span class="stat-remaining">(<?php echo $galleryStat['remaining_files']; ?> verbleibend)</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Speicher:</span>
                            <span class="stat-value"><?php echo $galleryStat['total_size_formatted']; ?> / 15 MB</span>
                            <span class="stat-remaining">(<?php echo $galleryStat['remaining_size_formatted']; ?> verbleibend)</span>
                        </div>
                    </div>
                    <?php if ($galleryStat['file_count'] >= 5 || $galleryStat['remaining_size'] <= 0): ?>
                    <div class="limit-warning">
                        <span class="status-warning">Limit erreicht</span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Benutzer aktualisieren</button>
            <a href="/admin/users" class="btn btn-secondary">Abbrechen</a>
        </div>
    </form>
</div>