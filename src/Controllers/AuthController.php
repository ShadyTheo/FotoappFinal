<?php

namespace App\Controllers;

use App\Database;
use App\ActivityLogger;
use App\Security\RateLimiter;
use App\Security\CSRFToken;

class AuthController extends BaseController {
    private $db;
    private $logger;
    private $rateLimiter;
    
    public function __construct() {
        $this->db = new Database();
        $this->logger = new ActivityLogger();
        $this->rateLimiter = new RateLimiter();
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
        // Validate CSRF token
        CSRFToken::validateRequest();
        
        $clientId = $this->rateLimiter->getClientIdentifier();
        
        // Check rate limiting
        if (!$this->rateLimiter->isAllowed($clientId, 'login')) {
            $remainingTime = $this->rateLimiter->getRemainingTime($clientId, 'login');
            $this->logger->log('login_blocked', 'user', null, "Login blocked for client: {$clientId}");
            $this->redirect('/', ['error' => "Zu viele Anmeldeversuche. Versuchen Sie es in " . ceil($remainingTime / 60) . " Minuten erneut."]);
        }
        
        $email = $this->sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $this->rateLimiter->recordAttempt($clientId, 'login', false);
            $this->redirect('/', ['error' => 'E-Mail und Passwort sind erforderlich']);
        }
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->rateLimiter->recordAttempt($clientId, 'login', false);
            $this->redirect('/', ['error' => 'Ungültige E-Mail-Adresse']);
        }
        
        $stmt = $this->db->getPdo()->prepare("SELECT id, email, password_hash, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Successful login
            $this->rateLimiter->recordAttempt($clientId, 'login', true);
            
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['login_time'] = time();
            
            // Log successful login
            $this->logger->log('login', 'user', $user['id'], "Successful login for {$user['email']}", $user['id']);
            
            if ($user['role'] === 'admin') {
                header('Location: /admin');
            } else {
                header('Location: /galleries');
            }
            exit;
        }
        
        // Failed login
        $this->rateLimiter->recordAttempt($clientId, 'login', false);
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