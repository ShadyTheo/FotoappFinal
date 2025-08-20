<?php

namespace App\Security;

class FileValidator {
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/jpg', 
        'image/png',
        'image/gif',
        'image/webp',
        'video/mp4',
        'video/mov',
        'video/avi',
        'video/wmv'
    ];
    
    private const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        'mp4', 'mov', 'avi', 'wmv'
    ];
    
    private const MAX_FILE_SIZE = 100 * 1024 * 1024; // 100MB
    
    // Suspicious file signatures that should be blocked
    private const MALICIOUS_SIGNATURES = [
        '<?php',
        '<?=',
        '<script',
        'javascript:',
        'vbscript:',
        'onload=',
        'onerror=',
        'eval(',
        'exec(',
        'system(',
        'shell_exec(',
        'passthru(',
        'base64_decode(',
        '\x00', // Null bytes
        '../', // Path traversal
        '..\\', // Windows path traversal
    ];
    
    public static function validateFile($file) {
        $errors = [];
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = self::getUploadErrorMessage($file['error']);
            return $errors;
        }
        
        // Check file size
        if ($file['size'] > self::MAX_FILE_SIZE) {
            $errors[] = 'Datei zu groß (max. ' . self::formatBytes(self::MAX_FILE_SIZE) . ')';
        }
        
        if ($file['size'] == 0) {
            $errors[] = 'Leere Datei';
        }
        
        // Validate file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
            $errors[] = 'Ungültiger Dateityp: .' . $extension;
        }
        
        // Validate MIME type using finfo
        if (isset($file['tmp_name']) && file_exists($file['tmp_name'])) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $realMimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($realMimeType, self::ALLOWED_MIME_TYPES)) {
                $errors[] = 'Ungültiger MIME-Typ: ' . $realMimeType;
            }
            
            // Additional validation for images
            if (strpos($realMimeType, 'image/') === 0) {
                $imageInfo = @getimagesize($file['tmp_name']);
                if ($imageInfo === false) {
                    $errors[] = 'Ungültige Bilddatei';
                }
            }
            
            // Scan file content for malicious patterns
            $contentErrors = self::scanFileContent($file['tmp_name']);
            $errors = array_merge($errors, $contentErrors);
        }
        
        // Validate filename
        $filenameErrors = self::validateFilename($file['name']);
        $errors = array_merge($errors, $filenameErrors);
        
        return $errors;
    }
    
    private static function scanFileContent($filePath) {
        $errors = [];
        
        try {
            // Read first 8KB of file for signature scanning
            $handle = fopen($filePath, 'rb');
            if ($handle) {
                $content = fread($handle, 8192);
                fclose($handle);
                
                // Convert to lowercase for case-insensitive matching
                $contentLower = strtolower($content);
                
                foreach (self::MALICIOUS_SIGNATURES as $signature) {
                    if (strpos($contentLower, strtolower($signature)) !== false) {
                        $errors[] = 'Verdächtige Datei erkannt - Upload blockiert';
                        break; // Stop at first suspicious content
                    }
                }
                
                // Check for embedded PHP in image metadata
                if (strpos($contentLower, 'php') !== false && preg_match('/\<\?php|\<\?=/', $content)) {
                    $errors[] = 'PHP-Code in Datei erkannt - Upload blockiert';
                }
            }
        } catch (Exception $e) {
            $errors[] = 'Fehler beim Scannen der Datei';
        }
        
        return $errors;
    }
    
    private static function validateFilename($filename) {
        $errors = [];
        
        // Check for dangerous characters
        if (preg_match('/[<>:"|?*\x00-\x1f]/', $filename)) {
            $errors[] = 'Ungültige Zeichen im Dateinamen';
        }
        
        // Check for path traversal attempts
        if (strpos($filename, '../') !== false || strpos($filename, '..\\') !== false) {
            $errors[] = 'Ungültiger Dateiname';
        }
        
        // Check for hidden files or system files
        if (substr($filename, 0, 1) === '.' || substr($filename, 0, 1) === '_') {
            $errors[] = 'Versteckte Dateien nicht erlaubt';
        }
        
        // Check filename length
        if (strlen($filename) > 255) {
            $errors[] = 'Dateiname zu lang';
        }
        
        // Check for double extensions (e.g., file.jpg.php)
        $parts = explode('.', $filename);
        if (count($parts) > 2) {
            $errors[] = 'Mehrfache Dateierweiterungen nicht erlaubt';
        }
        
        return $errors;
    }
    
    public static function generateSecureFilename($originalName) {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        
        // Generate secure filename with timestamp and random string
        $timestamp = date('Y-m-d_H-i-s');
        $random = bin2hex(random_bytes(8));
        
        return $timestamp . '_' . $random . '.' . $extension;
    }
    
    private static function getUploadErrorMessage($error) {
        switch ($error) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'Datei zu groß';
            case UPLOAD_ERR_PARTIAL:
                return 'Upload unvollständig';
            case UPLOAD_ERR_NO_FILE:
                return 'Keine Datei ausgewählt';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Temporärer Ordner fehlt';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Schreibfehler';
            case UPLOAD_ERR_EXTENSION:
                return 'Upload durch Erweiterung gestoppt';
            default:
                return 'Unbekannter Upload-Fehler';
        }
    }
    
    private static function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    public static function quarantineFile($filePath, $reason) {
        $quarantineDir = __DIR__ . '/../../quarantine/';
        if (!is_dir($quarantineDir)) {
            mkdir($quarantineDir, 0700, true);
        }
        
        $quarantineFile = $quarantineDir . date('Y-m-d_H-i-s') . '_' . basename($filePath) . '.quarantine';
        
        if (move_uploaded_file($filePath, $quarantineFile)) {
            error_log("File quarantined: {$quarantineFile} - Reason: {$reason}");
            return true;
        }
        
        return false;
    }
}