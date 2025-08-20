<?php

namespace App\Security;

class CSRFToken {
    const TOKEN_NAME = 'csrf_token';
    const SESSION_KEY = '_csrf_tokens';
    
    public static function generate() {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $timestamp = time();
        
        // Store token with timestamp for expiration
        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }
        
        $_SESSION[self::SESSION_KEY][$token] = $timestamp;
        
        // Clean up old tokens (older than 1 hour)
        self::cleanup();
        
        return $token;
    }
    
    public static function validate($token) {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        if (empty($token) || !isset($_SESSION[self::SESSION_KEY][$token])) {
            return false;
        }
        
        $timestamp = $_SESSION[self::SESSION_KEY][$token];
        
        // Check if token is expired (1 hour)
        if (time() - $timestamp > 3600) {
            unset($_SESSION[self::SESSION_KEY][$token]);
            return false;
        }
        
        // Remove token after use (one-time use)
        unset($_SESSION[self::SESSION_KEY][$token]);
        return true;
    }
    
    public static function getHiddenField() {
        $token = self::generate();
        return '<input type="hidden" name="' . self::TOKEN_NAME . '" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
    
    public static function getMetaTag() {
        $token = self::generate();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
    
    private static function cleanup() {
        if (!isset($_SESSION[self::SESSION_KEY])) {
            return;
        }
        
        $currentTime = time();
        foreach ($_SESSION[self::SESSION_KEY] as $token => $timestamp) {
            if ($currentTime - $timestamp > 3600) { // 1 hour
                unset($_SESSION[self::SESSION_KEY][$token]);
            }
        }
    }
    
    public static function validateRequest() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST[self::TOKEN_NAME] ?? '';
            if (!self::validate($token)) {
                http_response_code(403);
                die('CSRF token validation failed');
            }
        }
    }
}