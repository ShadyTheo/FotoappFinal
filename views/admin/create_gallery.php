<div class="admin-container">
    <div class="admin-header">
        <h2>Neue Galerie erstellen</h2>
        <a href="/admin" class="btn btn-secondary">Zurück</a>
    </div>
    
    <?php if ($flash = $this->getFlash('error')): ?>
    <div class="alert alert-error">
        <?php echo htmlspecialchars($flash); ?>
    </div>
    <?php endif; ?>
    
    <form method="POST" action="/admin/galleries" class="gallery-form">
        <div class="form-group">
            <label for="name">Galeriename:</label>
            <input type="text" id="name" name="name" required>
        </div>
        
        <div class="form-group">
            <label for="client_email">Kunden E-Mail (optional):</label>
            <input type="email" id="client_email" name="client_email">
            <small>Nur dieser Kunde kann die Galerie sehen</small>
        </div>
        
        <div class="form-group">
            <label for="access_code">Zugangscode (optional):</label>
            <input type="text" id="access_code" name="access_code">
            <small>Leer lassen für automatische Generierung</small>
        </div>
        
        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="is_public">
                Öffentlich (ohne Code zugänglich)
            </label>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Galerie erstellen</button>
            <a href="/admin" class="btn btn-secondary">Abbrechen</a>
        </div>
    </form>
</div>