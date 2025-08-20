<div class="admin-container">
    <div class="payment-container">
        <div class="payment-card">
            <h2>✅ Zahlung bestätigen</h2>
            
            <div class="payment-details">
                <h3><?php echo htmlspecialchars($payment['gallery_name']); ?></h3>
                <div class="payment-info">
                    <div class="info-row">
                        <span>Betrag:</span>
                        <strong><?php echo number_format($payment['amount'], 2); ?> <?php echo $payment['currency']; ?></strong>
                    </div>
                    <div class="info-row">
                        <span>Referenz:</span>
                        <code><?php echo htmlspecialchars($payment['payment_reference']); ?></code>
                    </div>
                    <div class="info-row">
                        <span>Status:</span>
                        <span class="status <?php echo $payment['payment_status']; ?>">
                            <?php echo ucfirst($payment['payment_status']); ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <?php if ($payment['payment_status'] === 'pending'): ?>
            <div class="verification-section">
                <div class="alert" style="background-color: rgba(253, 126, 20, 0.1); border-left: 4px solid var(--warning); margin-bottom: 1.5rem;">
                    <strong>Zahlungsbestätigung erforderlich</strong><br>
                    Bitte bestätigen Sie, dass Sie die PayPal-Zahlung erfolgreich durchgeführt haben.
                    <br><br>
                    <small>
                        <strong>Wichtig:</strong> Klicken Sie nur auf "Zahlung bestätigen", wenn Sie die Zahlung 
                        tatsächlich über PayPal durchgeführt haben. Falsche Bestätigungen werden geprüft und 
                        können zum Ausschluss führen.
                    </small>
                </div>
                
                <form method="POST" action="<?php echo htmlspecialchars($verifyUrl); ?>">
                    <?php echo \App\Security\CSRFToken::getHiddenField(); ?>
                    
                    <div class="form-group">
                        <label for="transaction_id">PayPal Transaktions-ID (optional):</label>
                        <input type="text" id="transaction_id" name="transaction_id" 
                               placeholder="z.B. 1ABC23456D789012E" maxlength="50">
                        <small>Falls Sie die Transaktions-ID haben, geben Sie sie hier ein.</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="confirm_payment" required>
                            Ich bestätige, dass ich die Zahlung über PayPal durchgeführt habe
                        </label>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">
                            ✅ Zahlung bestätigen
                        </button>
                        <a href="/gallery/<?php echo $payment['gallery_id']; ?>/payment" class="btn btn-secondary">
                            ← Zurück zur Zahlung
                        </a>
                    </div>
                </form>
            </div>
            <?php elseif ($payment['payment_status'] === 'verified'): ?>
            <div class="success-section">
                <div class="alert alert-success">
                    <strong>Zahlung erfolgreich!</strong><br>
                    Ihre Zahlung wurde bestätigt. Sie haben jetzt Zugang zur Galerie.
                </div>
                
                <a href="/gallery/<?php echo $payment['gallery_id']; ?>" class="btn btn-primary">
                    📸 Zur Galerie
                </a>
            </div>
            <?php else: ?>
            <div class="error-section">
                <div class="alert alert-error">
                    <strong>Zahlungsproblem</strong><br>
                    Es gab ein Problem mit Ihrer Zahlung. Status: <?php echo htmlspecialchars($payment['payment_status']); ?>
                </div>
                
                <a href="/gallery/<?php echo $payment['gallery_id']; ?>/payment" class="btn btn-primary">
                    🔄 Erneut versuchen
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.payment-container {
    display: flex;
    justify-content: center;
    align-items: flex-start;
    min-height: calc(100vh - 200px);
    padding: 2rem 1rem;
}

.payment-card {
    max-width: 500px;
    width: 100%;
    background-color: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    padding: 2rem;
    box-shadow: 0 4px 12px var(--shadow);
    text-align: center;
}

.payment-card h2 {
    margin-bottom: 1.5rem;
    color: var(--text-primary);
}

.payment-details {
    margin-bottom: 2rem;
    text-align: left;
}

.payment-details h3 {
    text-align: center;
    margin-bottom: 1rem;
    color: var(--text-primary);
}

.payment-info {
    background-color: var(--bg-tertiary);
    padding: 1rem;
    border-radius: var(--border-radius-sm);
}

.info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
    padding: 0.25rem 0;
}

.info-row:last-child {
    margin-bottom: 0;
    border-top: 1px solid var(--border-color);
    padding-top: 0.75rem;
    margin-top: 0.75rem;
}

.status.pending {
    color: var(--warning);
    font-weight: bold;
}

.status.verified {
    color: var(--success);
    font-weight: bold;
}

.status.failed {
    color: var(--danger);
    font-weight: bold;
}

.verification-section,
.success-section,
.error-section {
    text-align: left;
}

.form-actions {
    text-align: center;
    margin-top: 2rem;
}

@media (max-width: 480px) {
    .payment-card {
        padding: 1.5rem 1rem;
    }
    
    .info-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }
}
</style>