<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Router;
use App\Controllers\AuthController;
use App\Controllers\AdminController;
use App\Controllers\GalleryController;

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

// User dashboard
$router->get('/galleries', [GalleryController::class, 'userDashboard']);

// Gallery routes
$router->get('/gallery/{id}', [GalleryController::class, 'show']);
$router->get('/gallery/{id}/access', [GalleryController::class, 'showAccess']);
$router->post('/gallery/{id}/access', [GalleryController::class, 'checkAccess']);

// Static files
$router->get('/uploads/{file}', function($file) {
    $filePath = __DIR__ . '/uploads/' . basename($file);
    if (file_exists($filePath)) {
        $mimeType = mime_content_type($filePath);
        header('Content-Type: ' . $mimeType);
        readfile($filePath);
        exit;
    }
    http_response_code(404);
    echo 'File not found';
});

$router->dispatch();