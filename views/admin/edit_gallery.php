<div class="admin-container">
    <div class="admin-header">
        <h2>Galerie bearbeiten</h2>
        <div class="header-actions">
            <a href="/admin/galleries/<?php echo $gallery['id']; ?>" class="btn btn-secondary">Zurück zur Galerie</a>
            <a href="/admin" class="btn btn-outline">Admin Dashboard</a>
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
    
    <form method="POST" action="/admin/galleries/<?php echo $gallery['id']; ?>/update" class="gallery-form">
        <?php echo \App\Security\CSRFToken::getHiddenField(); ?>
        
        <div class="form-group">
            <label for="name">Galeriename:</label>
            <input type="text" id="name" name="name" required maxlength="255" value="<?php echo htmlspecialchars($gallery['name']); ?>">
        </div>
        
        <div class="form-group">
            <label for="client_email">Kunden E-Mail (optional):</label>
            <input type="email" id="client_email" name="client_email" maxlength="255" value="<?php echo htmlspecialchars($gallery['client_email'] ?? ''); ?>">
            <small>Nur dieser Kunde kann die Galerie sehen</small>
        </div>
        
        <div class="form-group">
            <label for="access_code">Zugangscode (optional):</label>
            <input type="text" id="access_code" name="access_code" maxlength="50" pattern="[a-zA-Z0-9]+" value="<?php echo htmlspecialchars($gallery['access_code'] ?? ''); ?>">
            <small>Leer lassen für automatische Generierung</small>
        </div>
        
        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="is_public" <?php echo $gallery['is_public'] ? 'checked' : ''; ?>>
                Öffentlich (ohne Code zugänglich)
            </label>
        </div>
        
        <?php if (!empty($users)): ?>
        <div class="form-group">
            <label>Benutzer-Zugriff:</label>
            <small>Wählen Sie Benutzer aus, die Zugriff auf diese Galerie haben sollen</small>
            <div class="checkbox-grid">
                <?php foreach ($users as $user): ?>
                <label class="checkbox-label">
                    <input type="checkbox" name="user_ids[]" value="<?php echo $user['id']; ?>" 
                           <?php echo in_array($user['id'], $assignedUserIds) ? 'checked' : ''; ?>>
                    <?php echo htmlspecialchars($user['email']); ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="has_paywall" id="paywall_checkbox" onchange="togglePaywallFields()" 
                       <?php echo $gallery['has_paywall'] ? 'checked' : ''; ?>>
                Paywall aktivieren (Bezahlung erforderlich)
            </label>
        </div>
        
        <div id="paywall_fields" style="display: <?php echo $gallery['has_paywall'] ? 'block' : 'none'; ?>;">
            <div class="form-group">
                <label for="price_amount">Preis:</label>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <input type="number" id="price_amount" name="price_amount" min="0.01" max="999.99" step="0.01" 
                           placeholder="0.00" style="flex: 1;" value="<?php echo $gallery['price_amount'] ? number_format($gallery['price_amount'], 2) : ''; ?>"
                           <?php echo $gallery['has_paywall'] ? 'required' : ''; ?>>
                    <select name="price_currency" style="width: 80px;">
                        <option value="EUR" <?php echo $gallery['price_currency'] === 'EUR' ? 'selected' : ''; ?>>EUR</option>
                        <option value="USD" <?php echo $gallery['price_currency'] === 'USD' ? 'selected' : ''; ?>>USD</option>
                        <option value="GBP" <?php echo $gallery['price_currency'] === 'GBP' ? 'selected' : ''; ?>>GBP</option>
                    </select>
                </div>
                <small>Mindestbetrag: 0.01, Maximalbetrag: 999.99</small>
            </div>
            
            <div class="form-group">
                <div class="alert" style="background-color: rgba(13, 110, 253, 0.1); border-left: 4px solid var(--primary); padding: 12px;">
                    <strong>PayPal Integration:</strong><br>
                    Zahlungen werden über PayPal.me/juliusschade abgewickelt.<br>
                    Benutzer werden nach der Zahlung automatisch zur Galerie weitergeleitet.
                </div>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Galerie aktualisieren</button>
            <a href="/admin/galleries/<?php echo $gallery['id']; ?>" class="btn btn-secondary">Abbrechen</a>
        </div>
    </form>
</div>

<script>
function togglePaywallFields() {
    const checkbox = document.getElementById('paywall_checkbox');
    const fields = document.getElementById('paywall_fields');
    const priceInput = document.getElementById('price_amount');
    
    if (checkbox.checked) {
        fields.style.display = 'block';
        priceInput.required = true;
    } else {
        fields.style.display = 'none';
        priceInput.required = false;
    }
}
</script>