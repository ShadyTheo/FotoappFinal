<?php

namespace App\Controllers;

use App\Database;
use App\ActivityLogger;
use App\Security\FileValidator;
use App\Security\CSRFToken;
use App\Security\UploadLimitValidator;

class AdminController extends BaseController {
    private $db;
    private $logger;
    
    public function __construct() {
        $this->db = new Database();
        $this->logger = new ActivityLogger();
    }
    
    public function dashboard() {
        $this->requireAdmin();
        
        // Get galleries with media counts
        $stmt = $this->db->getPdo()->prepare("
            SELECT g.*, 
                   COUNT(m.id) as media_count,
                   SUM(CASE WHEN m.type = 'photo' THEN 1 ELSE 0 END) as photo_count,
                   SUM(CASE WHEN m.type = 'video' THEN 1 ELSE 0 END) as video_count,
                   SUM(m.file_size) as total_size,
                   MAX(m.uploaded_at) as last_upload
            FROM galleries g
            LEFT JOIN media m ON g.id = m.gallery_id
            GROUP BY g.id
            ORDER BY g.created_at DESC
        ");
        $stmt->execute();
        $galleries = $stmt->fetchAll();
        
        // Get overall statistics
        $stats = $this->getDashboardStats();
        
        $this->render('admin/dashboard', [
            'galleries' => $galleries,
            'stats' => $stats,
            'flash' => $this->getFlash()
        ]);
    }
    
    private function getDashboardStats() {
        $pdo = $this->db->getPdo();
        
        // Gallery statistics
        $stmt = $pdo->prepare("SELECT COUNT(*) as total_galleries FROM galleries");
        $stmt->execute();
        $totalGalleries = $stmt->fetchColumn();
        
        // Media statistics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_media,
                SUM(CASE WHEN type = 'photo' THEN 1 ELSE 0 END) as total_photos,
                SUM(CASE WHEN type = 'video' THEN 1 ELSE 0 END) as total_videos,
                SUM(file_size) as total_storage,
                COUNT(CASE WHEN uploaded_at >= datetime('now', '-7 days') THEN 1 END) as recent_uploads
            FROM media
        ");
        $stmt->execute();
        $mediaStats = $stmt->fetch();
        
        // User statistics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_users,
                COUNT(CASE WHEN role = 'admin' THEN 1 END) as admin_count,
                COUNT(CASE WHEN role = 'client' THEN 1 END) as client_count
            FROM users
        ");
        $stmt->execute();
        $userStats = $stmt->fetch();
        
        // Recent activity
        $stmt = $pdo->prepare("
            SELECT m.*, g.name as gallery_name, g.id as gallery_id
            FROM media m
            JOIN galleries g ON m.gallery_id = g.id
            ORDER BY m.uploaded_at DESC
            LIMIT 10
        ");
        $stmt->execute();
        $recentUploads = $stmt->fetchAll();
        
        return [
            'galleries' => $totalGalleries,
            'media' => $mediaStats,
            'users' => $userStats,
            'recent_uploads' => $recentUploads
        ];
    }
    
    public function createGallery() {
        $this->requireAdmin();
        $this->render('admin/create_gallery');
    }
    
    public function storeGallery() {
        $this->requireAdmin();
        $this->validateSession();
        
        // Validate CSRF token
        CSRFToken::validateRequest();
        
        $name = $this->sanitizeInput($_POST['name'] ?? '');
        $clientEmail = $this->sanitizeInput($_POST['client_email'] ?? '') ?: null;
        $accessCode = $this->sanitizeInput($_POST['access_code'] ?? '') ?: null;
        $isPublic = isset($_POST['is_public']) ? 1 : 0;
        
        // Validate email if provided
        if ($clientEmail && !filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
            $this->redirect('/admin/galleries/create', ['error' => 'Ungültige E-Mail-Adresse']);
        }
        
        if (empty($name)) {
            $this->redirect('/admin/galleries/create', ['error' => 'Galeriename ist erforderlich']);
        }
        
        // Generate access code if not provided and gallery is not public
        if (!$isPublic && !$accessCode) {
            $accessCode = bin2hex(random_bytes(8));
        }
        
        $stmt = $this->db->getPdo()->prepare("
            INSERT INTO galleries (name, client_email, access_code, is_public)
            VALUES (?, ?, ?, ?)
        ");
        
        try {
            $stmt->execute([$name, $clientEmail, $accessCode, $isPublic]);
            $galleryId = $this->db->getPdo()->lastInsertId();
            
            // Log gallery creation
            $this->logger->log('create', 'gallery', $galleryId, "Created gallery: {$name}");
            
            $this->redirect('/admin/galleries/' . $galleryId, ['success' => 'Galerie wurde erfolgreich erstellt']);
        } catch (\Exception $e) {
            $this->redirect('/admin/galleries/create', ['error' => 'Fehler beim Erstellen der Galerie']);
        }
    }
    
    public function showGallery($id) {
        $this->requireAuth(); // Allow both admin and regular users
        
        $stmt = $this->db->getPdo()->prepare("SELECT * FROM galleries WHERE id = ?");
        $stmt->execute([$id]);
        $gallery = $stmt->fetch();
        
        if (!$gallery) {
            http_response_code(404);
            echo 'Gallery not found';
            return;
        }
        
        // Check if non-admin users have access to this gallery
        if ($_SESSION['user_role'] !== 'admin') {
            $stmt = $this->db->getPdo()->prepare("
                SELECT 1 FROM user_galleries 
                WHERE user_id = ? AND gallery_id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $id]);
            
            if (!$stmt->fetch()) {
                http_response_code(403);
                echo 'Access denied';
                return;
            }
        }
        
        $stmt = $this->db->getPdo()->prepare("
            SELECT m.*, u.email as uploader_email 
            FROM media m 
            LEFT JOIN users u ON m.user_id = u.id 
            WHERE m.gallery_id = ? 
            ORDER BY m.uploaded_at DESC
        ");
        $stmt->execute([$id]);
        $media = $stmt->fetchAll();
        
        // Get upload limits info for non-admin users
        $uploadLimits = null;
        if ($_SESSION['user_role'] !== 'admin') {
            $uploadLimitValidator = new UploadLimitValidator();
            $uploadLimits = $uploadLimitValidator->getUserGalleryRemainingLimits($_SESSION['user_id'], $id);
        }
        
        $this->render('admin/gallery', [
            'gallery' => $gallery,
            'media' => $media,
            'uploadLimits' => $uploadLimits,
            'flash' => $this->getFlash()
        ]);
    }
    
    public function uploadMedia($id) {
        try {
            $this->requireAuth(); // Allow both admin and regular users
            $this->validateSession();
            
            // Validate CSRF token
            CSRFToken::validateRequest();
        } catch (\Exception $e) {
            error_log("Upload error in validation: " . $e->getMessage());
            http_response_code(400);
            echo json_encode(['error' => 'Validierungsfehler: ' . $e->getMessage()]);
            return;
        }
        
        // Check if non-admin users have access to this gallery
        if ($_SESSION['user_role'] !== 'admin') {
            $stmt = $this->db->getPdo()->prepare("
                SELECT 1 FROM user_galleries 
                WHERE user_id = ? AND gallery_id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $id]);
            
            if (!$stmt->fetch()) {
                echo json_encode(['error' => 'Keine Berechtigung für diese Galerie']);
                return;
            }
        }
        
        if (!isset($_FILES['files'])) {
            echo json_encode(['error' => 'Keine Dateien hochgeladen']);
            return;
        }
        
        $files = $_FILES['files'];
        
        // Check upload limits for non-admin users
        if ($_SESSION['user_role'] !== 'admin') {
            $uploadLimitValidator = new UploadLimitValidator();
            
            // Convert files array to proper format for validation
            $fileArray = [];
            for ($i = 0; $i < count($files['name']); $i++) {
                $fileArray[] = [
                    'name' => $files['name'][$i],
                    'size' => $files['size'][$i],
                    'error' => $files['error'][$i]
                ];
            }
            
            $limitErrors = $uploadLimitValidator->validateUserUpload($_SESSION['user_id'], $id, $fileArray);
            
            if (!empty($limitErrors)) {
                echo json_encode(['error' => implode(' ', $limitErrors)]);
                return;
            }
        }
        $uploadDir = __DIR__ . '/../../public/uploads/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $results = [];
        $totalUploaded = 0;
        $successCount = 0;
        
        for ($i = 0; $i < count($files['name']); $i++) {
            $file = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i]
            ];
            
            // Comprehensive security validation
            $validationErrors = FileValidator::validateFile($file);
            
            if (!empty($validationErrors)) {
                $results[] = [
                    'file' => $this->sanitizeInput($file['name']), 
                    'error' => implode(', ', $validationErrors)
                ];
                
                // Quarantine suspicious files
                if (isset($file['tmp_name']) && file_exists($file['tmp_name'])) {
                    FileValidator::quarantineFile($file['tmp_name'], implode(', ', $validationErrors));
                }
                continue;
            }
            
            // Generate secure filename
            $filename = FileValidator::generateSecureFilename($file['name']);
            $filepath = $uploadDir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Set proper file permissions
                chmod($filepath, 0644);
                
                // Determine file type
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $realMimeType = finfo_file($finfo, $filepath);
                finfo_close($finfo);
                
                $type = strpos($realMimeType, 'image/') === 0 ? 'photo' : 'video';
                
                // Get additional metadata
                $fileSize = filesize($filepath);
                $dimensions = null;
                
                if ($type === 'photo' && function_exists('getimagesize')) {
                    $imageInfo = @getimagesize($filepath);
                    if ($imageInfo) {
                        $dimensions = $imageInfo[0] . 'x' . $imageInfo[1];
                    }
                }
                
                try {
                    $stmt = $this->db->getPdo()->prepare("
                        INSERT INTO media (gallery_id, user_id, type, filename, mime_type, title, file_size)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->execute([
                        $id, 
                        $_SESSION['user_id'], // Track who uploaded the file
                        $type, 
                        $filename, 
                        $realMimeType, 
                        $this->sanitizeInput($file['name']), 
                        $fileSize
                    ]);
                    $mediaId = $this->db->getPdo()->lastInsertId();
                    
                    // Log media upload
                    $this->logger->log('upload', 'media', $mediaId, "Uploaded {$type}: {$file['name']} ({$this->formatFileSize($fileSize)})");
                    
                    $results[] = [
                        'file' => $this->sanitizeInput($file['name']), 
                        'success' => true,
                        'size' => $this->formatFileSize($fileSize),
                        'dimensions' => $dimensions
                    ];
                    $successCount++;
                    $totalUploaded += $fileSize;
                } catch (\Exception $e) {
                    // Clean up file if database insert fails
                    unlink($filepath);
                    $results[] = [
                        'file' => $this->sanitizeInput($file['name']), 
                        'error' => 'Datenbankfehler beim Speichern'
                    ];
                    error_log("Database error during file upload: " . $e->getMessage());
                }
            } else {
                $results[] = [
                    'file' => $this->sanitizeInput($file['name']), 
                    'error' => 'Fehler beim Speichern der Datei'
                ];
            }
        }
        
        try {
            header('Content-Type: application/json');
            echo json_encode([
                'results' => $results,
                'summary' => [
                    'total' => count($files['name']),
                    'success' => $successCount,
                    'failed' => count($files['name']) - $successCount,
                    'totalSize' => $this->formatFileSize($totalUploaded)
                ]
            ]);
        } catch (\Exception $e) {
            error_log("Upload error in final response: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Server-Fehler beim Verarbeiten der Antwort']);
        }
    }
    
    private function getUploadErrorMessage($error) {
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
    
    
    public function listUsers() {
        $this->requireAdmin();
        
        $stmt = $this->db->getPdo()->prepare("
            SELECT u.*, 
                   GROUP_CONCAT(g.name) as gallery_names,
                   COUNT(DISTINCT ug.gallery_id) as gallery_count,
                   COUNT(DISTINCT m.id) as total_files_uploaded,
                   COALESCE(SUM(m.file_size), 0) as total_storage_used
            FROM users u
            LEFT JOIN user_galleries ug ON u.id = ug.user_id
            LEFT JOIN galleries g ON ug.gallery_id = g.id
            LEFT JOIN media m ON u.id = m.user_id
            WHERE u.role = 'client'
            GROUP BY u.id
            ORDER BY u.created_at DESC
        ");
        $stmt->execute();
        $users = $stmt->fetchAll();
        
        // Add formatted storage for each user
        foreach ($users as &$user) {
            $user['total_storage_formatted'] = $this->formatFileSize($user['total_storage_used']);
        }
        
        $this->render('admin/users/list', [
            'users' => $users,
            'flash' => $this->getFlash()
        ]);
    }
    
    public function createUser() {
        $this->requireAdmin();
        
        $stmt = $this->db->getPdo()->prepare("SELECT * FROM galleries ORDER BY name");
        $stmt->execute();
        $galleries = $stmt->fetchAll();
        
        $this->render('admin/users/create', [
            'galleries' => $galleries,
            'flash' => $this->getFlash()
        ]);
    }
    
    public function storeUser() {
        $this->requireAdmin();
        $this->validateSession();
        
        // Validate CSRF token
        CSRFToken::validateRequest();
        
        $email = $this->sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? ''; // Don't sanitize passwords
        $galleryIds = array_map('intval', $_POST['gallery_ids'] ?? []); // Sanitize array of IDs
        
        if (empty($email) || empty($password)) {
            $this->redirect('/admin/users/create', ['error' => 'E-Mail und Passwort sind erforderlich']);
        }
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->redirect('/admin/users/create', ['error' => 'Ungültige E-Mail-Adresse']);
        }
        
        // Validate password strength
        if (strlen($password) < 8) {
            $this->redirect('/admin/users/create', ['error' => 'Passwort muss mindestens 8 Zeichen lang sein']);
        }
        
        // Check if email already exists
        $stmt = $this->db->getPdo()->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $this->redirect('/admin/users/create', ['error' => 'E-Mail-Adresse bereits vorhanden']);
        }
        
        try {
            $this->db->getPdo()->beginTransaction();
            
            // Create user
            $stmt = $this->db->getPdo()->prepare("
                INSERT INTO users (email, password_hash, role)
                VALUES (?, ?, 'client')
            ");
            $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);
            $userId = $this->db->getPdo()->lastInsertId();
            
            // Assign galleries
            if (!empty($galleryIds)) {
                $stmt = $this->db->getPdo()->prepare("
                    INSERT INTO user_galleries (user_id, gallery_id) VALUES (?, ?)
                ");
                foreach ($galleryIds as $galleryId) {
                    $stmt->execute([$userId, $galleryId]);
                }
            }
            
            $this->db->getPdo()->commit();
            
            // Log user creation
            $this->logger->log('create', 'user', $userId, "Created user: {$email}");
            
            $this->redirect('/admin/users', ['success' => 'Benutzer wurde erfolgreich erstellt']);
            
        } catch (\Exception $e) {
            $this->db->getPdo()->rollBack();
            $this->redirect('/admin/users/create', ['error' => 'Fehler beim Erstellen des Benutzers']);
        }
    }
    
    public function editUser($id) {
        $this->requireAdmin();
        
        $stmt = $this->db->getPdo()->prepare("SELECT * FROM users WHERE id = ? AND role = 'client'");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            http_response_code(404);
            echo 'User not found';
            return;
        }
        
        $stmt = $this->db->getPdo()->prepare("SELECT * FROM galleries ORDER BY name");
        $stmt->execute();
        $galleries = $stmt->fetchAll();
        
        $stmt = $this->db->getPdo()->prepare("SELECT gallery_id FROM user_galleries WHERE user_id = ?");
        $stmt->execute([$id]);
        $assignedGalleryIds = array_column($stmt->fetchAll(), 'gallery_id');
        
        // Get detailed upload statistics for this user
        $uploadLimitValidator = new UploadLimitValidator();
        $userGalleryStats = $uploadLimitValidator->getAllUserGalleryStats($id);
        
        // Add formatted values and limits info
        foreach ($userGalleryStats as &$galleryStat) {
            $galleryStat['total_size_formatted'] = $this->formatFileSize($galleryStat['total_size']);
            $galleryStat['remaining_files'] = max(0, 5 - $galleryStat['file_count']);
            $galleryStat['remaining_size'] = max(0, (15 * 1024 * 1024) - $galleryStat['total_size']);
            $galleryStat['remaining_size_formatted'] = $this->formatFileSize($galleryStat['remaining_size']);
        }
        
        $this->render('admin/users/edit', [
            'user' => $user,
            'galleries' => $galleries,
            'assignedGalleryIds' => $assignedGalleryIds,
            'userGalleryStats' => $userGalleryStats,
            'flash' => $this->getFlash()
        ]);
    }
    
    public function updateUser($id) {
        $this->requireAdmin();
        
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $galleryIds = $_POST['gallery_ids'] ?? [];
        
        if (empty($email)) {
            $this->redirect('/admin/users/' . $id, ['error' => 'E-Mail ist erforderlich']);
        }
        
        // Check if email already exists for other users
        $stmt = $this->db->getPdo()->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $id]);
        if ($stmt->fetch()) {
            $this->redirect('/admin/users/' . $id, ['error' => 'E-Mail-Adresse bereits vorhanden']);
        }
        
        try {
            $this->db->getPdo()->beginTransaction();
            
            // Update user
            if (!empty($password)) {
                $stmt = $this->db->getPdo()->prepare("
                    UPDATE users SET email = ?, password_hash = ? WHERE id = ?
                ");
                $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT), $id]);
            } else {
                $stmt = $this->db->getPdo()->prepare("
                    UPDATE users SET email = ? WHERE id = ?
                ");
                $stmt->execute([$email, $id]);
            }
            
            // Remove old gallery assignments
            $stmt = $this->db->getPdo()->prepare("DELETE FROM user_galleries WHERE user_id = ?");
            $stmt->execute([$id]);
            
            // Add new gallery assignments
            if (!empty($galleryIds)) {
                $stmt = $this->db->getPdo()->prepare("
                    INSERT INTO user_galleries (user_id, gallery_id) VALUES (?, ?)
                ");
                foreach ($galleryIds as $galleryId) {
                    $stmt->execute([$id, $galleryId]);
                }
            }
            
            $this->db->getPdo()->commit();
            $this->redirect('/admin/users', ['success' => 'Benutzer wurde erfolgreich aktualisiert']);
            
        } catch (\Exception $e) {
            $this->db->getPdo()->rollBack();
            $this->redirect('/admin/users/' . $id, ['error' => 'Fehler beim Aktualisieren des Benutzers']);
        }
    }
    
    public function deleteMedia($mediaId) {
        $this->requireAuth();
        $this->validateSession();
        
        // Validate CSRF token
        CSRFToken::validateRequest();
        
        $uploadLimitValidator = new UploadLimitValidator();
        
        // For non-admin users, only allow deleting their own files
        if ($_SESSION['user_role'] !== 'admin') {
            $result = $uploadLimitValidator->deleteUserFile($_SESSION['user_id'], $mediaId);
        } else {
            // Admin can delete any file
            $stmt = $this->db->getPdo()->prepare("
                SELECT filename, file_size, user_id 
                FROM media 
                WHERE id = ?
            ");
            $stmt->execute([$mediaId]);
            $media = $stmt->fetch();
            
            if (!$media) {
                $result = ['success' => false, 'error' => 'Datei nicht gefunden'];
            } else {
                // Delete from database
                $stmt = $this->db->getPdo()->prepare("DELETE FROM media WHERE id = ?");
                $stmt->execute([$mediaId]);
                
                // Delete physical file
                $filePath = __DIR__ . '/../../public/uploads/' . $media['filename'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                
                $result = [
                    'success' => true,
                    'freed_size' => $media['file_size'],
                    'freed_size_formatted' => $this->formatFileSize($media['file_size'])
                ];
            }
        }
        
        if ($result['success']) {
            $this->logger->log('delete', 'media', $mediaId, "Deleted media file");
        }
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }
    
    public function activityLog() {
        $this->requireAdmin();
        
        $page = (int) ($_GET['page'] ?? 1);
        $limit = 50;
        $offset = ($page - 1) * $limit;
        
        // Get activity logs with pagination
        $stmt = $this->db->getPdo()->prepare("
            SELECT al.*, u.email as user_email
            FROM activity_log al
            LEFT JOIN users u ON al.user_id = u.id
            ORDER BY al.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        $activities = $stmt->fetchAll();
        
        // Get total count for pagination
        $stmt = $this->db->getPdo()->prepare("SELECT COUNT(*) FROM activity_log");
        $stmt->execute();
        $totalCount = $stmt->fetchColumn();
        $totalPages = ceil($totalCount / $limit);
        
        // Get activity statistics
        $activityStats = $this->logger->getActivityStats(7); // Last 7 days
        
        $this->render('admin/activity_log', [
            'activities' => $activities,
            'activityStats' => $activityStats,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount
        ]);
    }
}