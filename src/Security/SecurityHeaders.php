<?php

namespace App\Security;

class SecurityHeaders {
    
    public static function apply() {
        // Prevent clickjacking
        header('X-Frame-Options: DENY');
        
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Enable XSS protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Referrer policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Content Security Policy
        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline'", // Allow inline scripts for functionality
            "style-src 'self' 'unsafe-inline'",  // Allow inline styles
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "media-src 'self'",
            "object-src 'none'",
            "frame-src 'none'",
            "base-uri 'self'",
            "form-action 'self'"
        ];
        header('Content-Security-Policy: ' . implode('; ', $csp));
        
        // HTTPS enforcement (if using HTTPS)
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        
        // Remove server information
        header_remove('X-Powered-By');
        header_remove('Server');
        
        // Cache control for security
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
    
    public static function setSecureSessionConfig() {
        // Secure session configuration
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Strict');
        
        // Enable secure cookies if using HTTPS
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            ini_set('session.cookie_secure', 1);
        }
        
        // Session timeout
        ini_set('session.gc_maxlifetime', 7200); // 2 hours
        ini_set('session.cookie_lifetime', 0); // Session cookies
        
        // Use strong session ID
        ini_set('session.entropy_length', 32);
        ini_set('session.hash_function', 'sha256');
        
        // Prevent session fixation
        ini_set('session.use_trans_sid', 0);
        ini_set('session.use_only_cookies', 1);
    }
    
    public static function validateRequest() {
        // Block common attack patterns in URL
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        
        $suspiciousPatterns = [
            '/\.\./i',           // Path traversal
            '/\/etc\/passwd/i',  // System file access
            '/\/proc\//i',       // Process access
            '/script\s*:/i',     // JavaScript protocol
            '/javascript\s*:/i', // JavaScript protocol
            '/vbscript\s*:/i',   // VBScript protocol
            '/on\w+\s*=/i',      // Event handlers
            '/<script/i',        // Script tags
            '/exec\s*\(/i',      // Code execution
            '/system\s*\(/i',    // System calls
            '/eval\s*\(/i',      // Code evaluation
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $requestUri)) {
                self::blockRequest('Suspicious request pattern detected');
            }
        }
        
        // Validate HTTP method
        $allowedMethods = ['GET', 'POST', 'HEAD', 'OPTIONS'];
        if (!in_array($_SERVER['REQUEST_METHOD'], $allowedMethods)) {
            self::blockRequest('Invalid HTTP method');
        }
        
        // Validate content length for POST requests
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $contentLength = $_SERVER['CONTENT_LENGTH'] ?? 0;
            $maxPostSize = 200 * 1024 * 1024; // 200MB for file uploads
            
            if ($contentLength > $maxPostSize) {
                self::blockRequest('Request too large');
            }
        }
        
        // Validate User-Agent (block empty or suspicious ones)
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (empty($userAgent) || strlen($userAgent) > 1000) {
            // Allow some flexibility for legitimate clients
            if (empty($userAgent)) {
                error_log('Empty User-Agent from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            }
        }
        
        // Rate limiting for requests
        $rateLimiter = new RateLimiter(100, 60); // 100 requests per minute
        $clientId = $rateLimiter->getClientIdentifier();
        
        if (!$rateLimiter->isAllowed($clientId, 'request')) {
            self::blockRequest('Rate limit exceeded');
        }
        
        $rateLimiter->recordAttempt($clientId, 'request', true);
    }
    
    private static function blockRequest($reason) {
        error_log("Request blocked: {$reason} - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . " - URI: " . ($_SERVER['REQUEST_URI'] ?? 'unknown'));
        
        http_response_code(403);
        header('Content-Type: text/plain');
        echo 'Access Denied';
        exit;
    }
    
    public static function sanitizeGlobals() {
        // Remove potentially dangerous superglobals
        unset($_ENV);
        
        // Sanitize $_SERVER variables that might be used in code
        $dangerousServerVars = [
            'PHP_SELF',
            'PATH_INFO',
            'PATH_TRANSLATED',
            'SCRIPT_NAME',
            'REQUEST_URI'
        ];
        
        foreach ($dangerousServerVars as $var) {
            if (isset($_SERVER[$var])) {
                $_SERVER[$var] = filter_var($_SERVER[$var], FILTER_SANITIZE_URL);
            }
        }
    }
}