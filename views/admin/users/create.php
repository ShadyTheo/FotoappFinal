<div class="admin-container">
    <div class="admin-header">
        <h2>Neuen Benutzer erstellen</h2>
        <a href="/admin/users" class="btn btn-secondary">Zurück</a>
    </div>
    
    <?php if ($flash = $this->getFlash('error')): ?>
    <div class="alert alert-error">
        <?php echo htmlspecialchars($flash); ?>
    </div>
    <?php endif; ?>
    
    <form method="POST" action="/admin/users" class="user-form">
        <?php echo \App\Security\CSRFToken::getHiddenField(); ?>
        
        <div class="form-group">
            <label for="email">E-Mail:</label>
            <input type="email" id="email" name="email" required maxlength="255" autocomplete="email">
        </div>
        
        <div class="form-group">
            <label for="password">Passwort:</label>
            <input type="password" id="password" name="password" required minlength="8" maxlength="255" autocomplete="new-password">
            <small>Mindestens 8 Zeichen. Der Benutzer kann sich mit diesem Passwort anmelden und automatisch auf zugewiesene Galerien zugreifen</small>
        </div>
        
        <div class="form-group">
            <label>Galerien zuweisen:</label>
            <div class="checkbox-grid">
                <?php if (empty($galleries)): ?>
                <p class="text-muted">Keine Galerien verfügbar. <a href="/admin/galleries/create">Erstellen Sie zuerst eine Galerie</a>.</p>
                <?php else: ?>
                <?php foreach ($galleries as $gallery): ?>
                <label class="checkbox-label">
                    <input type="checkbox" name="gallery_ids[]" value="<?php echo $gallery['id']; ?>">
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
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Benutzer erstellen</button>
            <a href="/admin/users" class="btn btn-secondary">Abbrechen</a>
        </div>
    </form>
</div>