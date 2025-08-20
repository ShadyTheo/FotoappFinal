<?php

namespace App\Controllers;

use App\Database;
use App\ActivityLogger;

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
        
        $name = trim($_POST['name'] ?? '');
        $clientEmail = trim($_POST['client_email'] ?? '') ?: null;
        $accessCode = trim($_POST['access_code'] ?? '') ?: null;
        $isPublic = isset($_POST['is_public']) ? 1 : 0;
        
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
        $this->requireAdmin();
        
        $stmt = $this->db->getPdo()->prepare("SELECT * FROM galleries WHERE id = ?");
        $stmt->execute([$id]);
        $gallery = $stmt->fetch();
        
        if (!$gallery) {
            http_response_code(404);
            echo 'Gallery not found';
            return;
        }
        
        $stmt = $this->db->getPdo()->prepare("SELECT * FROM media WHERE gallery_id = ? ORDER BY uploaded_at DESC");
        $stmt->execute([$id]);
        $media = $stmt->fetchAll();
        
        $this->render('admin/gallery', [
            'gallery' => $gallery,
            'media' => $media,
            'flash' => $this->getFlash()
        ]);
    }
    
    public function uploadMedia($id) {
        $this->requireAdmin();
        
        if (!isset($_FILES['files'])) {
            echo json_encode(['error' => 'Keine Dateien hochgeladen']);
            return;
        }
        
        $files = $_FILES['files'];
        $uploadDir = __DIR__ . '/../../public/uploads/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $results = [];
        $allowedTypes = [
            'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
            'video/mp4', 'video/mov', 'video/avi', 'video/wmv'
        ];
        $maxFileSize = 100 * 1024 * 1024; // 100MB
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
            
            // Enhanced error handling
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errorMsg = $this->getUploadErrorMessage($file['error']);
                $results[] = ['file' => $file['name'], 'error' => $errorMsg];
                continue;
            }
            
            // File size validation
            if ($file['size'] > $maxFileSize) {
                $results[] = ['file' => $file['name'], 'error' => 'Datei zu groß (max. 100MB)'];
                continue;
            }
            
            if ($file['size'] == 0) {
                $results[] = ['file' => $file['name'], 'error' => 'Leere Datei'];
                continue;
            }
            
            // Enhanced MIME type validation
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $realMimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($realMimeType, $allowedTypes) && !in_array($file['type'], $allowedTypes)) {
                $results[] = ['file' => $file['name'], 'error' => 'Ungültiger Dateityp: ' . $file['type']];
                continue;
            }
            
            // Generate secure filename
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = date('Y-m-d_H-i-s') . '_' . uniqid() . '.' . $extension;
            $filepath = $uploadDir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $type = strpos($realMimeType, 'image/') === 0 ? 'photo' : 'video';
                
                // Get additional metadata
                $fileSize = filesize($filepath);
                $dimensions = null;
                $duration = null;
                
                if ($type === 'photo' && function_exists('getimagesize')) {
                    $imageInfo = getimagesize($filepath);
                    if ($imageInfo) {
                        $dimensions = $imageInfo[0] . 'x' . $imageInfo[1];
                    }
                }
                
                $stmt = $this->db->getPdo()->prepare("
                    INSERT INTO media (gallery_id, type, filename, mime_type, title, file_size)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([$id, $type, $filename, $realMimeType, $file['name'], $fileSize]);
                $mediaId = $this->db->getPdo()->lastInsertId();
                
                // Log media upload
                $this->logger->log('upload', 'media', $mediaId, "Uploaded {$type}: {$file['name']} ({$this->formatFileSize($fileSize)})");
                
                $results[] = [
                    'file' => $file['name'], 
                    'success' => true,
                    'size' => $this->formatFileSize($fileSize),
                    'dimensions' => $dimensions
                ];
                $successCount++;
                $totalUploaded += $fileSize;
            } else {
                $results[] = ['file' => $file['name'], 'error' => 'Fehler beim Speichern der Datei'];
            }
        }
        
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
                   COUNT(DISTINCT ug.gallery_id) as gallery_count
            FROM users u
            LEFT JOIN user_galleries ug ON u.id = ug.user_id
            LEFT JOIN galleries g ON ug.gallery_id = g.id
            WHERE u.role = 'client'
            GROUP BY u.id
            ORDER BY u.created_at DESC
        ");
        $stmt->execute();
        $users = $stmt->fetchAll();
        
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
        
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $galleryIds = $_POST['gallery_ids'] ?? [];
        
        if (empty($email) || empty($password)) {
            $this->redirect('/admin/users/create', ['error' => 'E-Mail und Passwort sind erforderlich']);
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
        
        $this->render('admin/users/edit', [
            'user' => $user,
            'galleries' => $galleries,
            'assignedGalleryIds' => $assignedGalleryIds,
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