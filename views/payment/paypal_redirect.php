<div class="admin-container">
    <div class="payment-container">
        <div class="payment-card">
            <h2>💳 Zahlung erforderlich</h2>
            
            <div class="gallery-info">
                <h3><?php echo htmlspecialchars($gallery['name']); ?></h3>
                <div class="price-display">
                    <span class="amount"><?php echo $amount; ?></span>
                    <span class="currency"><?php echo $currency; ?></span>
                </div>
            </div>
            
            <div class="payment-instructions">
                <p>Um Zugang zu dieser Galerie zu erhalten, ist eine Zahlung über PayPal erforderlich.</p>
                
                <div class="payment-steps">
                    <div class="step">
                        <span class="step-number">1</span>
                        <span>Klicken Sie auf "Mit PayPal bezahlen"</span>
                    </div>
                    <div class="step">
                        <span class="step-number">2</span>
                        <span>Führen Sie die Zahlung über PayPal durch</span>
                    </div>
                    <div class="step">
                        <span class="step-number">3</span>
                        <span>Kehren Sie hierher zurück und bestätigen Sie die Zahlung</span>
                    </div>
                </div>
                
                <div class="payment-reference">
                    <strong>Zahlungsreferenz:</strong> <?php echo htmlspecialchars($paymentReference); ?>
                    <br><small>Bitte notieren Sie sich diese Referenz für Ihre Unterlagen</small>
                </div>
            </div>
            
            <div class="payment-actions">
                <a href="<?php echo htmlspecialchars($paypalUrl); ?>" 
                   target="_blank" 
                   class="btn btn-primary paypal-btn" 
                   onclick="showPaymentInProgress()">
                    🔗 Mit PayPal bezahlen
                </a>
                
                <div id="payment-in-progress" style="display: none;">
                    <div class="alert" style="background-color: rgba(13, 110, 253, 0.1); border-left: 4px solid var(--primary); margin-top: 1rem;">
                        <strong>Zahlung läuft...</strong><br>
                        Nachdem Sie die PayPal-Zahlung abgeschlossen haben, klicken Sie auf "Zahlung bestätigen".
                    </div>
                    
                    <a href="<?php echo htmlspecialchars($returnUrl); ?>" class="btn btn-success" style="margin-top: 1rem;">
                        ✅ Zahlung bestätigen
                    </a>
                </div>
            </div>
            
            <div class="payment-security">
                <small>
                    🔒 Sichere Zahlung über PayPal<br>
                    💎 Sofortiger Zugang nach Zahlungsbestätigung<br>
                    📧 Zahlungsbestätigung per E-Mail
                </small>
            </div>
        </div>
    </div>
</div>

<style>
.payment-container {
    display: flex;
    justify-content: center;
    align-items: center;
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

.gallery-info {
    margin-bottom: 2rem;
    padding: 1rem;
    background-color: var(--bg-tertiary);
    border-radius: var(--border-radius-sm);
}

.gallery-info h3 {
    margin-bottom: 0.5rem;
    color: var(--text-primary);
}

.price-display {
    font-size: 2rem;
    font-weight: bold;
    color: var(--primary);
}

.price-display .currency {
    font-size: 1.2rem;
    margin-left: 0.25rem;
}

.payment-instructions {
    text-align: left;
    margin-bottom: 2rem;
}

.payment-steps {
    margin: 1.5rem 0;
}

.step {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.step-number {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    background-color: var(--primary);
    color: white;
    border-radius: 50%;
    font-size: 0.875rem;
    font-weight: bold;
}

.payment-reference {
    margin-top: 1.5rem;
    padding: 1rem;
    background-color: var(--bg-primary);
    border-radius: var(--border-radius-sm);
    font-size: 0.9rem;
}

.payment-actions {
    margin-bottom: 1.5rem;
}

.paypal-btn {
    background-color: #0070ba;
    border-color: #0070ba;
    font-size: 1.1rem;
    padding: 0.75rem 2rem;
}

.paypal-btn:hover {
    background-color: #005ea6;
    border-color: #005ea6;
}

.payment-security {
    color: var(--text-muted);
    font-size: 0.875rem;
    line-height: 1.4;
}

@media (max-width: 480px) {
    .payment-card {
        padding: 1.5rem 1rem;
    }
    
    .price-display {
        font-size: 1.5rem;
    }
    
    .step {
        flex-direction: column;
        text-align: center;
        gap: 0.5rem;
    }
}
</style>

<script>
function showPaymentInProgress() {
    setTimeout(function() {
        document.getElementById('payment-in-progress').style.display = 'block';
    }, 3000); // Show after 3 seconds to give time for PayPal redirect
}

// Check payment status periodically
let checkCount = 0;
const maxChecks = 20; // Check for 10 minutes (30s intervals)

function checkPaymentStatus() {
    if (checkCount >= maxChecks) {
        return; // Stop checking after max attempts
    }
    
    checkCount++;
    
    fetch('/payment/status/<?php echo $gallery['id']; ?>')
        .then(response => response.json())
        .then(data => {
            if (data.paid) {
                // Payment confirmed, redirect to gallery
                window.location.href = '/gallery/<?php echo $gallery['id']; ?>';
            } else if (checkCount < maxChecks) {
                // Continue checking
                setTimeout(checkPaymentStatus, 30000); // Check every 30 seconds
            }
        })
        .catch(error => {
            console.log('Payment status check failed:', error);
            if (checkCount < maxChecks) {
                setTimeout(checkPaymentStatus, 30000);
            }
        });
}

// Start checking payment status after 30 seconds
setTimeout(checkPaymentStatus, 30000);
</script>