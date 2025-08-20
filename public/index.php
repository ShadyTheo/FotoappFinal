<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Router;
use App\Controllers\AuthController;
use App\Controllers\AdminController;
use App\Controllers\GalleryController;
use App\Controllers\PaymentController;
use App\Security\SecurityHeaders;
use App\Security\ErrorHandler;

// Initialize error handling
ErrorHandler::init();

// Apply security configuration
SecurityHeaders::setSecureSessionConfig();
SecurityHeaders::apply();
SecurityHeaders::validateRequest();
SecurityHeaders::sanitizeGlobals();

session_start();

$router = new Router();

// Auth routes
$router->get('/', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);

// Admin routes
$router->get('/admin', [AdminController::class, 'dashboard']);
$router->get('/admin/galleries/create', [AdminController::class, 'createGallery']);
$router->post('/admin/galleries', [AdminController::class, 'storeGallery']);
$router->get('/admin/galleries/{id}', [AdminController::class, 'showGallery']);
$router->post('/admin/galleries/{id}/upload', [AdminController::class, 'uploadMedia']);

// User management routes
$router->get('/admin/users', [AdminController::class, 'listUsers']);
$router->get('/admin/users/create', [AdminController::class, 'createUser']);
$router->post('/admin/users', [AdminController::class, 'storeUser']);
$router->get('/admin/users/{id}', [AdminController::class, 'editUser']);
$router->post('/admin/users/{id}', [AdminController::class, 'updateUser']);

// Activity log routes
$router->get('/admin/activity', [AdminController::class, 'activityLog']);

// Media management routes
$router->post('/media/{id}/delete', [AdminController::class, 'deleteMedia']);

// Payment routes
$router->get('/gallery/{id}/payment', [PaymentController::class, 'initiatePayment']);
$router->get('/payment/verify/{reference}', [PaymentController::class, 'verifyPayment']);
$router->post('/payment/confirm/{reference}', [PaymentController::class, 'confirmPayment']);
$router->get('/payment/status/{id}', [PaymentController::class, 'checkPaymentStatus']);

// User dashboard
$router->get('/galleries', [GalleryController::class, 'userDashboard']);

// Gallery routes
$router->get('/gallery/{id}', [GalleryController::class, 'show']);
$router->get('/gallery/{id}/access', [GalleryController::class, 'showAccess']);
$router->post('/gallery/{id}/access', [GalleryController::class, 'checkAccess']);

// Static files - secured
$router->get('/uploads/{file}', function($file) {
    // Validate filename to prevent path traversal
    $file = basename($file);
    if (preg_match('/[^a-zA-Z0-9._-]/', $file) || strpos($file, '..') !== false) {
        http_response_code(403);
        echo 'Access denied';
        exit;
    }
    
    $filePath = __DIR__ . '/uploads/' . $file;
    if (file_exists($filePath) && is_file($filePath)) {
        $mimeType = mime_content_type($filePath);
        
        // Only serve allowed file types
        $allowedTypes = [
            'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
            'video/mp4', 'video/mov', 'video/avi', 'video/wmv'
        ];
        
        if (!in_array($mimeType, $allowedTypes)) {
            http_response_code(403);
            echo 'File type not allowed';
            exit;
        }
        
        // Set security headers for files
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: inline; filename="' . htmlspecialchars($file, ENT_QUOTES) . '"');
        
        readfile($filePath);
        exit;
    }
    http_response_code(404);
    echo 'File not found';
});

$router->dispatch();