<?php

namespace App\Security;

use App\Database;

class RateLimiter {
    private $db;
    private $maxAttempts;
    private $timeWindow;
    
    public function __construct($maxAttempts = 5, $timeWindow = 900) { // 5 attempts per 15 minutes
        $this->db = new Database();
        $this->maxAttempts = $maxAttempts;
        $this->timeWindow = $timeWindow;
        $this->createTable();
    }
    
    private function createTable() {
        $this->db->getPdo()->exec("
            CREATE TABLE IF NOT EXISTS rate_limits (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                identifier TEXT NOT NULL,
                action TEXT NOT NULL,
                attempts INTEGER DEFAULT 1,
                first_attempt DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_attempt DATETIME DEFAULT CURRENT_TIMESTAMP,
                blocked_until DATETIME NULL,
                UNIQUE(identifier, action)
            )
        ");
    }
    
    public function isAllowed($identifier, $action = 'login') {
        $this->cleanupExpired();
        
        $stmt = $this->db->getPdo()->prepare("
            SELECT attempts, blocked_until, first_attempt
            FROM rate_limits 
            WHERE identifier = ? AND action = ?
        ");
        $stmt->execute([$identifier, $action]);
        $record = $stmt->fetch();
        
        if (!$record) {
            return true;
        }
        
        // Check if currently blocked
        if ($record['blocked_until'] && strtotime($record['blocked_until']) > time()) {
            return false;
        }
        
        // Check if within time window
        if (strtotime($record['first_attempt']) + $this->timeWindow < time()) {
            // Reset counter if time window has passed
            $this->resetCounter($identifier, $action);
            return true;
        }
        
        return $record['attempts'] < $this->maxAttempts;
    }
    
    public function recordAttempt($identifier, $action = 'login', $success = false) {
        if ($success) {
            $this->resetCounter($identifier, $action);
            return;
        }
        
        $stmt = $this->db->getPdo()->prepare("
            INSERT INTO rate_limits (identifier, action, attempts, first_attempt, last_attempt)
            VALUES (?, ?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON CONFLICT(identifier, action) DO UPDATE SET
                attempts = attempts + 1,
                last_attempt = CURRENT_TIMESTAMP,
                blocked_until = CASE 
                    WHEN attempts + 1 >= ? THEN datetime('now', '+' || ? || ' seconds')
                    ELSE blocked_until
                END
        ");
        
        $stmt->execute([$identifier, $action, $this->maxAttempts, $this->timeWindow]);
    }
    
    public function getRemainingTime($identifier, $action = 'login') {
        $stmt = $this->db->getPdo()->prepare("
            SELECT blocked_until FROM rate_limits 
            WHERE identifier = ? AND action = ? AND blocked_until > datetime('now')
        ");
        $stmt->execute([$identifier, $action]);
        $record = $stmt->fetch();
        
        if ($record) {
            return max(0, strtotime($record['blocked_until']) - time());
        }
        
        return 0;
    }
    
    private function resetCounter($identifier, $action) {
        $stmt = $this->db->getPdo()->prepare("
            DELETE FROM rate_limits WHERE identifier = ? AND action = ?
        ");
        $stmt->execute([$identifier, $action]);
    }
    
    private function cleanupExpired() {
        $this->db->getPdo()->exec("
            DELETE FROM rate_limits 
            WHERE blocked_until IS NOT NULL 
            AND blocked_until < datetime('now', '-1 hour')
        ");
    }
    
    public function getClientIdentifier() {
        $ip = $this->getRealIpAddress();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return hash('sha256', $ip . '|' . $userAgent);
    }
    
    private function getRealIpAddress() {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 
                   'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}