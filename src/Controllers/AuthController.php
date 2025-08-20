<?php

namespace App\Controllers;

use App\Database;
use App\ActivityLogger;

class AuthController extends BaseController {
    private $db;
    private $logger;
    
    public function __construct() {
        $this->db = new Database();
        $this->logger = new ActivityLogger();
    }
    
    public function showLogin() {
        if (isset($_SESSION['user_id'])) {
            if ($_SESSION['user_role'] === 'admin') {
                header('Location: /admin');
                exit;
            } else {
                header('Location: /galleries');
                exit;
            }
        }
        
        $this->render('auth/login');
    }
    
    public function login() {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $this->redirect('/', ['error' => 'E-Mail und Passwort sind erforderlich']);
        }
        
        $stmt = $this->db->getPdo()->prepare("SELECT id, email, password_hash, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            
            // Log successful login
            $this->logger->log('login', 'user', $user['id'], "Successful login for {$user['email']}", $user['id']);
            
            if ($user['role'] === 'admin') {
                header('Location: /admin');
            } else {
                header('Location: /galleries');
            }
            exit;
        }
        
        // Log failed login attempt
        $this->logger->log('login_failed', 'user', null, "Failed login attempt for {$email}");
        
        $this->redirect('/', ['error' => 'Ungültige Anmeldedaten']);
    }
    
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            $this->logger->log('logout', 'user', $_SESSION['user_id'], "User logged out");
        }
        
        session_destroy();
        header('Location: /');
        exit;
    }
}