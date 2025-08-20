<div class="auth-container">
    <div class="auth-card">
        <h2>Galerie-Zugang</h2>
        <p>Diese Galerie ist geschützt. Bitte geben Sie den Zugangscode ein.</p>
        
        <?php if ($flash = $this->getFlash('error')): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($flash); ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="/gallery/<?php echo $gallery['id']; ?>/access" class="auth-form">
            <div class="form-group">
                <label for="access_code">Zugangscode:</label>
                <input type="text" id="access_code" name="access_code" required autocomplete="off">
            </div>
            
            <button type="submit" class="btn btn-primary">Zugang gewähren</button>
        </form>
    </div>
</div>