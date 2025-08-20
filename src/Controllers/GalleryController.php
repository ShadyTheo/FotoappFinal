<?php

namespace App\Controllers;

use App\Database;

class GalleryController extends BaseController {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function show($id) {
        $stmt = $this->db->getPdo()->prepare("SELECT * FROM galleries WHERE id = ?");
        $stmt->execute([$id]);
        $gallery = $stmt->fetch();
        
        if (!$gallery) {
            http_response_code(404);
            echo 'Gallery not found';
            return;
        }
        
        // Check access permissions
        if (!$this->hasAccess($gallery)) {
            $this->redirect('/gallery/' . $id . '/access');
        }
        
        $stmt = $this->db->getPdo()->prepare("SELECT * FROM media WHERE gallery_id = ? ORDER BY uploaded_at DESC");
        $stmt->execute([$id]);
        $media = $stmt->fetchAll();
        
        $this->render('gallery/show', [
            'gallery' => $gallery,
            'media' => $media
        ]);
    }
    
    public function showAccess($id) {
        $stmt = $this->db->getPdo()->prepare("SELECT * FROM galleries WHERE id = ?");
        $stmt->execute([$id]);
        $gallery = $stmt->fetch();
        
        if (!$gallery) {
            http_response_code(404);
            echo 'Gallery not found';
            return;
        }
        
        if ($this->hasAccess($gallery)) {
            $this->redirect('/gallery/' . $id);
        }
        
        $this->render('gallery/access', [
            'gallery' => $gallery,
            'flash' => $this->getFlash()
        ]);
    }
    
    public function checkAccess($id) {
        $stmt = $this->db->getPdo()->prepare("SELECT * FROM galleries WHERE id = ?");
        $stmt->execute([$id]);
        $gallery = $stmt->fetch();
        
        if (!$gallery) {
            http_response_code(404);
            echo 'Gallery not found';
            return;
        }
        
        $code = $_POST['access_code'] ?? '';
        
        if ($gallery['access_code'] === $code) {
            $_SESSION['gallery_access_' . $id] = true;
            $this->redirect('/gallery/' . $id);
        }
        
        $this->redirect('/gallery/' . $id . '/access', ['error' => 'Ungültiger Zugangscode']);
    }
    
    public function userDashboard() {
        $this->requireAuth();
        
        if ($_SESSION['user_role'] === 'admin') {
            header('Location: /admin');
            exit;
        }
        
        // Get galleries assigned to this user
        $stmt = $this->db->getPdo()->prepare("
            SELECT g.*, COUNT(m.id) as media_count
            FROM galleries g
            INNER JOIN user_galleries ug ON g.id = ug.gallery_id
            LEFT JOIN media m ON g.id = m.gallery_id
            WHERE ug.user_id = ?
            GROUP BY g.id
            ORDER BY g.created_at DESC
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $galleries = $stmt->fetchAll();
        
        $this->render('gallery/dashboard', [
            'galleries' => $galleries,
            'flash' => $this->getFlash()
        ]);
    }
    
    private function hasAccess($gallery) {
        // Public galleries
        if ($gallery['is_public']) {
            return true;
        }
        
        // Admin access
        if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
            return true;
        }
        
        // User assignment access (password unlocks assigned galleries)
        if (isset($_SESSION['user_id'])) {
            $stmt = $this->db->getPdo()->prepare("
                SELECT 1 FROM user_galleries WHERE user_id = ? AND gallery_id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $gallery['id']]);
            if ($stmt->fetch()) {
                return true;
            }
        }
        
        // Client email access (legacy)
        if ($gallery['client_email'] && 
            isset($_SESSION['user_email']) && 
            $_SESSION['user_email'] === $gallery['client_email']) {
            return true;
        }
        
        // Access code access
        if (isset($_SESSION['gallery_access_' . $gallery['id']])) {
            return true;
        }
        
        return false;
    }
}