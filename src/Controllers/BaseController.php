<?php

namespace App\Controllers;

class BaseController {
    protected function render($view, $data = []) {
        extract($data);
        
        ob_start();
        include __DIR__ . '/../../views/' . $view . '.php';
        $content = ob_get_clean();
        
        include __DIR__ . '/../../views/layout.php';
    }
    
    protected function redirect($url, $flashData = []) {
        if (!empty($flashData)) {
            $_SESSION['flash'] = $flashData;
        }
        header('Location: ' . $url);
        exit;
    }
    
    protected function requireAuth() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /');
            exit;
        }
    }
    
    protected function requireAdmin() {
        $this->requireAuth();
        if ($_SESSION['user_role'] !== 'admin') {
            http_response_code(403);
            echo 'Access denied';
            exit;
        }
    }
    
    protected function getFlash($key = null) {
        $flash = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        
        return $key ? ($flash[$key] ?? null) : $flash;
    }
    
    protected function formatFileSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    protected function getActionLabel($action) {
        $labels = [
            'login' => 'Anmeldung',
            'logout' => 'Abmeldung', 
            'login_failed' => 'Fehlgeschl. Anmeldung',
            'create' => 'Erstellt',
            'update' => 'Aktualisiert',
            'delete' => 'Gelöscht',
            'upload' => 'Hochgeladen',
            'download' => 'Heruntergeladen',
            'view' => 'Angesehen'
        ];
        return $labels[$action] ?? ucfirst($action);
    }
}