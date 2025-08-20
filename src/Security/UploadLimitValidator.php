<?php

namespace App\Security;

use App\Database;

class UploadLimitValidator {
    private $db;
    private const MAX_FILES_PER_USER = 5;
    private const MAX_TOTAL_SIZE_PER_USER = 15 * 1024 * 1024; // 15MB in bytes
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function validateUserUpload($userId, $galleryId, $newFiles) {
        $errors = [];
        
        // Get current user upload statistics for this gallery
        $currentStats = $this->getUserGalleryUploadStats($userId, $galleryId);
        
        // Calculate new file count and size
        $newFileCount = count($newFiles);
        $newTotalSize = 0;
        
        foreach ($newFiles as $file) {
            if (isset($file['size']) && $file['size'] > 0) {
                $newTotalSize += $file['size'];
            }
        }
        
        // Check file count limit
        $newTotalFiles = $currentStats['file_count'] + $newFileCount;
        if ($newTotalFiles > self::MAX_FILES_PER_USER) {
            $remaining = self::MAX_FILES_PER_USER - $currentStats['file_count'];
            $errors[] = "Sie können maximal " . self::MAX_FILES_PER_USER . " Dateien pro Galerie hochladen. " . 
                       "Sie haben bereits {$currentStats['file_count']} Dateien in dieser Galerie hochgeladen. " .
                       "Sie können noch {$remaining} Dateien in diese Galerie hochladen.";
        }
        
        // Check total size limit
        $newTotalSizeForUser = $currentStats['total_size'] + $newTotalSize;
        if ($newTotalSizeForUser > self::MAX_TOTAL_SIZE_PER_USER) {
            $used = $this->formatFileSize($currentStats['total_size']);
            $limit = $this->formatFileSize(self::MAX_TOTAL_SIZE_PER_USER);
            $remaining = $this->formatFileSize(self::MAX_TOTAL_SIZE_PER_USER - $currentStats['total_size']);
            $errors[] = "Sie können maximal {$limit} pro Galerie hochladen. " .
                       "Sie haben bereits {$used} in dieser Galerie verwendet. " .
                       "Verbleibendes Limit: {$remaining}.";
        }
        
        // Check individual file sizes
        foreach ($newFiles as $file) {
            if (isset($file['size']) && $file['size'] > self::MAX_TOTAL_SIZE_PER_USER) {
                $fileLimit = $this->formatFileSize(self::MAX_TOTAL_SIZE_PER_USER);
                $errors[] = "Einzelne Dateien dürfen nicht größer als {$fileLimit} sein.";
                break;
            }
        }
        
        return $errors;
    }
    
    public function getUserGalleryUploadStats($userId, $galleryId) {
        $stmt = $this->db->getPdo()->prepare("
            SELECT 
                COUNT(*) as file_count,
                COALESCE(SUM(file_size), 0) as total_size
            FROM media 
            WHERE user_id = ? AND gallery_id = ?
        ");
        $stmt->execute([$userId, $galleryId]);
        
        $result = $stmt->fetch();
        return [
            'file_count' => (int)$result['file_count'],
            'total_size' => (int)$result['total_size']
        ];
    }
    
    public function getUserUploadStats($userId) {
        $stmt = $this->db->getPdo()->prepare("
            SELECT 
                COUNT(*) as file_count,
                COALESCE(SUM(file_size), 0) as total_size
            FROM media 
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        
        $result = $stmt->fetch();
        return [
            'file_count' => (int)$result['file_count'],
            'total_size' => (int)$result['total_size']
        ];
    }
    
    public function getAllUserGalleryStats($userId) {
        $stmt = $this->db->getPdo()->prepare("
            SELECT 
                g.id as gallery_id,
                g.name as gallery_name,
                COUNT(m.id) as file_count,
                COALESCE(SUM(m.file_size), 0) as total_size
            FROM galleries g
            LEFT JOIN media m ON g.id = m.gallery_id AND m.user_id = ?
            LEFT JOIN user_galleries ug ON g.id = ug.gallery_id AND ug.user_id = ?
            WHERE ug.user_id IS NOT NULL
            GROUP BY g.id, g.name
            ORDER BY g.name
        ");
        $stmt->execute([$userId, $userId]);
        
        return $stmt->fetchAll();
    }
    
    public function getUserUploadLimits() {
        return [
            'max_files' => self::MAX_FILES_PER_USER,
            'max_total_size' => self::MAX_TOTAL_SIZE_PER_USER,
            'max_total_size_formatted' => $this->formatFileSize(self::MAX_TOTAL_SIZE_PER_USER)
        ];
    }
    
    public function getUserGalleryRemainingLimits($userId, $galleryId) {
        $stats = $this->getUserGalleryUploadStats($userId, $galleryId);
        $limits = $this->getUserUploadLimits();
        
        return [
            'remaining_files' => max(0, $limits['max_files'] - $stats['file_count']),
            'remaining_size' => max(0, $limits['max_total_size'] - $stats['total_size']),
            'remaining_size_formatted' => $this->formatFileSize(max(0, $limits['max_total_size'] - $stats['total_size'])),
            'used_files' => $stats['file_count'],
            'used_size' => $stats['total_size'],
            'used_size_formatted' => $this->formatFileSize($stats['total_size'])
        ];
    }
    
    public function getUserRemainingLimits($userId) {
        $stats = $this->getUserUploadStats($userId);
        $limits = $this->getUserUploadLimits();
        
        return [
            'remaining_files' => max(0, $limits['max_files'] - $stats['file_count']),
            'remaining_size' => max(0, $limits['max_total_size'] - $stats['total_size']),
            'remaining_size_formatted' => $this->formatFileSize(max(0, $limits['max_total_size'] - $stats['total_size'])),
            'used_files' => $stats['file_count'],
            'used_size' => $stats['total_size'],
            'used_size_formatted' => $this->formatFileSize($stats['total_size'])
        ];
    }
    
    public function canUserUpload($userId) {
        $stats = $this->getUserUploadStats($userId);
        
        return [
            'can_upload' => $stats['file_count'] < self::MAX_FILES_PER_USER && 
                          $stats['total_size'] < self::MAX_TOTAL_SIZE_PER_USER,
            'reason' => $this->getUploadBlockReason($stats)
        ];
    }
    
    private function getUploadBlockReason($stats) {
        if ($stats['file_count'] >= self::MAX_FILES_PER_USER) {
            return 'Maximale Anzahl Dateien erreicht (' . self::MAX_FILES_PER_USER . ')';
        }
        
        if ($stats['total_size'] >= self::MAX_TOTAL_SIZE_PER_USER) {
            $limit = $this->formatFileSize(self::MAX_TOTAL_SIZE_PER_USER);
            return "Maximale Speichergröße erreicht ({$limit})";
        }
        
        return null;
    }
    
    private function formatFileSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    public function deleteUserFile($userId, $mediaId) {
        try {
            // Verify the file belongs to the user
            $stmt = $this->db->getPdo()->prepare("
                SELECT filename, file_size 
                FROM media 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$mediaId, $userId]);
            $media = $stmt->fetch();
            
            if (!$media) {
                return ['success' => false, 'error' => 'Datei nicht gefunden oder keine Berechtigung'];
            }
            
            // Delete from database
            $stmt = $this->db->getPdo()->prepare("DELETE FROM media WHERE id = ? AND user_id = ?");
            $stmt->execute([$mediaId, $userId]);
            
            // Delete physical file
            $filePath = __DIR__ . '/../../public/uploads/' . $media['filename'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            return [
                'success' => true, 
                'freed_size' => $media['file_size'],
                'freed_size_formatted' => $this->formatFileSize($media['file_size'])
            ];
            
        } catch (\Exception $e) {
            error_log("Error deleting user file: " . $e->getMessage());
            return ['success' => false, 'error' => 'Fehler beim Löschen der Datei'];
        }
    }
}