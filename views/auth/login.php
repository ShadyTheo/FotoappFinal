<div class="auth-container">
    <div class="auth-card">
        <h2>Anmelden</h2>
        
        <?php if ($flash = $this->getFlash('error')): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($flash); ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="/login" class="auth-form">
            <?php echo \App\Security\CSRFToken::getHiddenField(); ?>
            
            <div class="form-group">
                <label for="email">E-Mail:</label>
                <input type="email" id="email" name="email" required maxlength="255" autocomplete="email">
            </div>
            
            <div class="form-group">
                <label for="password">Passwort:</label>
                <input type="password" id="password" name="password" required maxlength="255" autocomplete="current-password">
            </div>
            
            <button type="submit" class="btn btn-primary">Anmelden</button>
        </form>
    </div>
</div>