<?php

namespace App;

class ActivityLogger {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function log($action, $entityType, $entityId = null, $details = null, $userId = null) {
        try {
            // Create a new database connection for logging to avoid locks
            $logDb = new Database();
            $pdo = $logDb->getPdo();
            $pdo->exec("PRAGMA busy_timeout = 1000"); // 1 second timeout
            
            $userId = $userId ?: ($_SESSION['user_id'] ?? null);
            $ipAddress = $this->getRealIpAddress();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            $stmt = $pdo->prepare("
                INSERT INTO activity_log (user_id, action, entity_type, entity_id, details, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $userId,
                $action,
                $entityType, 
                $entityId,
                $details,
                $ipAddress,
                substr($userAgent, 0, 500) // Limit user agent length
            ]);
        } catch (\Exception $e) {
            // Silently fail logging to avoid breaking the application
            error_log("Activity logging failed: " . $e->getMessage());
        }
    }
    
    public function getRecentActivity($limit = 50, $userId = null) {
        $sql = "
            SELECT al.*, u.email as user_email
            FROM activity_log al
            LEFT JOIN users u ON al.user_id = u.id
        ";
        
        $params = [];
        if ($userId) {
            $sql .= " WHERE al.user_id = ?";
            $params[] = $userId;
        }
        
        $sql .= " ORDER BY al.created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->db->getPdo()->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    public function getActivityStats($days = 30) {
        $stmt = $this->db->getPdo()->prepare("
            SELECT 
                action,
                COUNT(*) as count,
                entity_type,
                DATE(created_at) as date
            FROM activity_log 
            WHERE created_at >= datetime('now', '-{$days} days')
            GROUP BY action, entity_type, DATE(created_at)
            ORDER BY created_at DESC
        ");
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function cleanOldLogs($days = 90) {
        $stmt = $this->db->getPdo()->prepare("
            DELETE FROM activity_log 
            WHERE created_at < datetime('now', '-{$days} days')
        ");
        
        $stmt->execute();
        return $stmt->rowCount();
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