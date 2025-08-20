<?php

namespace App\Controllers;

use App\Database;
use App\ActivityLogger;
use App\Security\CSRFToken;

class PaymentController extends BaseController {
    private $db;
    private $logger;
    
    public function __construct() {
        $this->db = new Database();
        $this->logger = new ActivityLogger();
    }
    
    public function initiatePayment($galleryId) {
        // Get gallery information
        $stmt = $this->db->getPdo()->prepare("
            SELECT * FROM galleries 
            WHERE id = ? AND has_paywall = 1
        ");
        $stmt->execute([$galleryId]);
        $gallery = $stmt->fetch();
        
        if (!$gallery) {
            http_response_code(404);
            echo 'Gallery not found or no paywall configured';
            return;
        }
        
        // Get user email (if logged in) or require email input
        $email = null;
        if (isset($_SESSION['user_id'])) {
            $stmt = $this->db->getPdo()->prepare("SELECT email FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            $email = $user ? $user['email'] : null;
        }
        
        // Check if user has already paid for this gallery
        if ($email) {
            $stmt = $this->db->getPdo()->prepare("
                SELECT * FROM gallery_payments 
                WHERE gallery_id = ? AND email = ? AND payment_status = 'verified'
            ");
            $stmt->execute([$galleryId, $email]);
            if ($stmt->fetch()) {
                // User has already paid, redirect to gallery
                $this->redirect('/gallery/' . $galleryId);
                return;
            }
        }
        
        // Generate unique payment reference
        $paymentReference = 'GAL' . $galleryId . '_' . time() . '_' . bin2hex(random_bytes(4));
        
        // Create payment record
        $stmt = $this->db->getPdo()->prepare("
            INSERT INTO gallery_payments (gallery_id, user_id, email, amount, currency, payment_reference, payment_status)
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");
        
        $userId = $_SESSION['user_id'] ?? null;
        $stmt->execute([
            $galleryId,
            $userId,
            $email,
            $gallery['price_amount'],
            $gallery['price_currency'],
            $paymentReference
        ]);
        
        // Store payment reference in session for later verification
        $_SESSION['pending_payment_ref'] = $paymentReference;
        $_SESSION['pending_gallery_id'] = $galleryId;
        
        // Generate PayPal.me URL
        $amount = number_format($gallery['price_amount'], 2);
        $currency = strtoupper($gallery['price_currency']);
        $description = urlencode("Gallery: " . $gallery['name'] . " (Ref: " . $paymentReference . ")");
        
        $paypalUrl = "https://paypal.me/juliusschade/{$amount}{$currency}";
        
        // Log payment initiation
        $this->logger->log('payment_initiated', 'gallery', $galleryId, "Payment initiated for gallery: {$gallery['name']}, Amount: {$amount} {$currency}");
        
        $this->render('payment/paypal_redirect', [
            'gallery' => $gallery,
            'paypalUrl' => $paypalUrl,
            'paymentReference' => $paymentReference,
            'amount' => $amount,
            'currency' => $currency,
            'returnUrl' => $this->getBaseUrl() . '/payment/verify/' . $paymentReference
        ]);
    }
    
    public function verifyPayment($paymentReference) {
        // Get payment record
        $stmt = $this->db->getPdo()->prepare("
            SELECT gp.*, g.name as gallery_name 
            FROM gallery_payments gp
            JOIN galleries g ON gp.gallery_id = g.id
            WHERE gp.payment_reference = ?
        ");
        $stmt->execute([$paymentReference]);
        $payment = $stmt->fetch();
        
        if (!$payment) {
            http_response_code(404);
            echo 'Payment not found';
            return;
        }
        
        // For now, we'll create a manual verification page
        // In a real implementation, you'd integrate with PayPal's IPN or webhooks
        $this->render('payment/verify', [
            'payment' => $payment,
            'verifyUrl' => '/payment/confirm/' . $paymentReference
        ]);
    }
    
    public function confirmPayment($paymentReference) {
        $this->validateSession();
        CSRFToken::validateRequest();
        
        // This is a simplified verification process
        // In production, you should verify the payment with PayPal's API
        
        $stmt = $this->db->getPdo()->prepare("
            SELECT gp.*, g.name as gallery_name 
            FROM gallery_payments gp
            JOIN galleries g ON gp.gallery_id = g.id
            WHERE gp.payment_reference = ? AND gp.payment_status = 'pending'
        ");
        $stmt->execute([$paymentReference]);
        $payment = $stmt->fetch();
        
        if (!$payment) {
            $this->redirect('/payment/verify/' . $paymentReference, ['error' => 'Payment not found or already processed']);
            return;
        }
        
        // Mark payment as verified
        $stmt = $this->db->getPdo()->prepare("
            UPDATE gallery_payments 
            SET payment_status = 'verified', 
                payment_verified_at = CURRENT_TIMESTAMP,
                paypal_transaction_id = ?
            WHERE payment_reference = ?
        ");
        $transactionId = $_POST['transaction_id'] ?? 'MANUAL_' . time();
        $stmt->execute([$transactionId, $paymentReference]);
        
        // Grant access to the gallery
        if ($payment['user_id']) {
            $stmt = $this->db->getPdo()->prepare("
                INSERT OR IGNORE INTO user_galleries (user_id, gallery_id) 
                VALUES (?, ?)
            ");
            $stmt->execute([$payment['user_id'], $payment['gallery_id']]);
        }
        
        // Log payment completion
        $this->logger->log('payment_verified', 'gallery', $payment['gallery_id'], 
            "Payment verified for gallery: {$payment['gallery_name']}, Amount: {$payment['amount']} {$payment['currency']}");
        
        // Clear session
        unset($_SESSION['pending_payment_ref']);
        unset($_SESSION['pending_gallery_id']);
        
        // Redirect to gallery
        $this->redirect('/gallery/' . $payment['gallery_id'], ['success' => 'Payment successful! You now have access to this gallery.']);
    }
    
    public function checkPaymentStatus($galleryId) {
        // Check if current user has paid for this gallery
        $email = null;
        if (isset($_SESSION['user_id'])) {
            $stmt = $this->db->getPdo()->prepare("SELECT email FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            $email = $user ? $user['email'] : null;
        }
        
        if (!$email) {
            echo json_encode(['paid' => false, 'message' => 'No user email found']);
            return;
        }
        
        $stmt = $this->db->getPdo()->prepare("
            SELECT payment_status FROM gallery_payments 
            WHERE gallery_id = ? AND email = ? AND payment_status = 'verified'
        ");
        $stmt->execute([$galleryId, $email]);
        $payment = $stmt->fetch();
        
        header('Content-Type: application/json');
        echo json_encode(['paid' => (bool)$payment]);
    }
    
    private function getBaseUrl() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        return $protocol . $_SERVER['HTTP_HOST'];
    }
}