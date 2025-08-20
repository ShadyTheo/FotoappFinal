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
        <?php echo \App\Security\CSRFToken::getHiddenField(); ?>
        
        <div class="form-group">
            <label for="name">Galeriename:</label>
            <input type="text" id="name" name="name" required maxlength="255">
        </div>
        
        <div class="form-group">
            <label for="client_email">Kunden E-Mail (optional):</label>
            <input type="email" id="client_email" name="client_email" maxlength="255">
            <small>Nur dieser Kunde kann die Galerie sehen</small>
        </div>
        
        <div class="form-group">
            <label for="access_code">Zugangscode (optional):</label>
            <input type="text" id="access_code" name="access_code" maxlength="50" pattern="[a-zA-Z0-9]+">
            <small>Leer lassen für automatische Generierung</small>
        </div>
        
        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="is_public">
                Öffentlich (ohne Code zugänglich)
            </label>
        </div>
        
        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="has_paywall" id="paywall_checkbox" onchange="togglePaywallFields()">
                Paywall aktivieren (Bezahlung erforderlich)
            </label>
        </div>
        
        <div id="paywall_fields" style="display: none;">
            <div class="form-group">
                <label for="price_amount">Preis:</label>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <input type="number" id="price_amount" name="price_amount" min="0.01" max="999.99" step="0.01" placeholder="0.00" style="flex: 1;">
                    <select name="price_currency" style="width: 80px;">
                        <option value="EUR">EUR</option>
                        <option value="USD">USD</option>
                        <option value="GBP">GBP</option>
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
            <button type="submit" class="btn btn-primary">Galerie erstellen</button>
            <a href="/admin" class="btn btn-secondary">Abbrechen</a>
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
        priceInput.value = '';
    }
}
</script>
    </form>
</div>