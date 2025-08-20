<?php

namespace App;

class Database {
    private $pdo;
    
    public function __construct() {
        $dbPath = __DIR__ . '/../data/app.sqlite';
        
        // Create data directory if it doesn't exist
        $dataDir = dirname($dbPath);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        
        $this->pdo = new \PDO('sqlite:' . $dbPath);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }
    
    public function getPdo() {
        return $this->pdo;
    }
    
    public function initializeSchema() {
        $sql = "
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL CHECK(role IN ('admin', 'client')),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS galleries (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            client_email TEXT,
            access_code TEXT,
            is_public INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS media (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            gallery_id INTEGER NOT NULL,
            type TEXT NOT NULL CHECK(type IN ('photo', 'video')),
            filename TEXT NOT NULL,
            mime_type TEXT NOT NULL,
            title TEXT,
            file_size INTEGER DEFAULT 0,
            duration_seconds INTEGER,
            poster_filename TEXT,
            uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (gallery_id) REFERENCES galleries (id) ON DELETE CASCADE
        );
        
        CREATE TABLE IF NOT EXISTS user_galleries (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            gallery_id INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
            FOREIGN KEY (gallery_id) REFERENCES galleries (id) ON DELETE CASCADE,
            UNIQUE(user_id, gallery_id)
        );
        
        CREATE TABLE IF NOT EXISTS activity_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            action TEXT NOT NULL,
            entity_type TEXT NOT NULL,
            entity_id INTEGER,
            details TEXT,
            ip_address TEXT,
            user_agent TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
        );
        
        CREATE INDEX IF NOT EXISTS idx_media_gallery_id ON media(gallery_id);
        CREATE INDEX IF NOT EXISTS idx_user_galleries_user_id ON user_galleries(user_id);
        CREATE INDEX IF NOT EXISTS idx_user_galleries_gallery_id ON user_galleries(gallery_id);
        CREATE INDEX IF NOT EXISTS idx_activity_log_user_id ON activity_log(user_id);
        CREATE INDEX IF NOT EXISTS idx_activity_log_created_at ON activity_log(created_at);
        ";
        
        $this->pdo->exec($sql);
        
        // Create default admin user if it doesn't exist
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        $stmt->execute();
        $adminCount = $stmt->fetchColumn();
        
        if ($adminCount == 0) {
            $stmt = $this->pdo->prepare("INSERT INTO users (email, password_hash, role) VALUES (?, ?, ?)");
            $stmt->execute([
                'admin@example.com',
                password_hash('admin123', PASSWORD_DEFAULT),
                'admin'
            ]);
        }
    }
}