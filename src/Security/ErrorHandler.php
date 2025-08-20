<?php

namespace App\Security;

class ErrorHandler {
    
    public static function init() {
        // Set error reporting (hide in production)
        error_reporting(E_ALL);
        ini_set('display_errors', 0);
        ini_set('log_errors', 1);
        ini_set('error_log', __DIR__ . '/../../logs/error.log');
        
        // Create log directory if it doesn't exist
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Set custom error handlers
        set_error_handler([self::class, 'errorHandler']);
        set_exception_handler([self::class, 'exceptionHandler']);
        register_shutdown_function([self::class, 'shutdownHandler']);
    }
    
    public static function errorHandler($severity, $message, $file, $line) {
        // Don't handle suppressed errors
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $errorType = self::getErrorType($severity);
        $logMessage = "[{$errorType}] {$message} in {$file} on line {$line}";
        
        // Log all errors
        error_log($logMessage);
        
        // For fatal errors, show user-friendly message
        if ($severity & (E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR)) {
            self::showErrorPage('Ein unerwarteter Fehler ist aufgetreten.');
        }
        
        return true;
    }
    
    public static function exceptionHandler($exception) {
        $logMessage = "[EXCEPTION] " . $exception->getMessage() . 
                     " in " . $exception->getFile() . 
                     " on line " . $exception->getLine() . 
                     "\nStack trace:\n" . $exception->getTraceAsString();
        
        error_log($logMessage);
        
        // Don't expose sensitive information
        $userMessage = 'Ein unerwarteter Fehler ist aufgetreten.';
        
        // For specific exception types, provide more specific messages
        if ($exception instanceof \PDOException) {
            $userMessage = 'Datenbankfehler. Bitte versuchen Sie es später erneut.';
        } elseif ($exception instanceof \InvalidArgumentException) {
            $userMessage = 'Ungültige Eingabedaten.';
        }
        
        self::showErrorPage($userMessage);
    }
    
    public static function shutdownHandler() {
        $error = error_get_last();
        
        if ($error && ($error['type'] & (E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_PARSE))) {
            $logMessage = "[FATAL] {$error['message']} in {$error['file']} on line {$error['line']}";
            error_log($logMessage);
            
            self::showErrorPage('Ein kritischer Fehler ist aufgetreten.');
        }
    }
    
    private static function showErrorPage($message) {
        // Clean any output buffer
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
        
        // Don't include detailed error information
        echo self::getErrorPageHtml($message);
        exit;
    }
    
    private static function getErrorPageHtml($message) {
        return '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fehler - Fotogalerie</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f8f9fa;
            color: #212529;
            margin: 0;
            padding: 40px 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .error-container {
            max-width: 500px;
            text-align: center;
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .error-icon {
            font-size: 4rem;
            color: #dc3545;
            margin-bottom: 20px;
        }
        h1 {
            color: #dc3545;
            margin-bottom: 20px;
        }
        p {
            margin-bottom: 30px;
            color: #6c757d;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: #0d6efd;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.2s;
        }
        .btn:hover {
            background-color: #0b5ed7;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">⚠️</div>
        <h1>Oops! Es ist ein Fehler aufgetreten</h1>
        <p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>
        <a href="/" class="btn">Zur Startseite</a>
    </div>
</body>
</html>';
    }
    
    private static function getErrorType($severity) {
        switch ($severity) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
                return 'FATAL ERROR';
            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_USER_WARNING:
                return 'WARNING';
            case E_NOTICE:
            case E_USER_NOTICE:
                return 'NOTICE';
            case E_STRICT:
                return 'STRICT';
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                return 'DEPRECATED';
            default:
                return 'UNKNOWN';
        }
    }
    
    public static function handle404() {
        http_response_code(404);
        header('Content-Type: text/html; charset=UTF-8');
        echo self::get404PageHtml();
        exit;
    }
    
    private static function get404PageHtml() {
        return '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seite nicht gefunden - Fotogalerie</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f8f9fa;
            color: #212529;
            margin: 0;
            padding: 40px 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .error-container {
            max-width: 500px;
            text-align: center;
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .error-code {
            font-size: 6rem;
            font-weight: bold;
            color: #6c757d;
            margin-bottom: 20px;
        }
        h1 {
            color: #212529;
            margin-bottom: 20px;
        }
        p {
            margin-bottom: 30px;
            color: #6c757d;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: #0d6efd;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.2s;
        }
        .btn:hover {
            background-color: #0b5ed7;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code">404</div>
        <h1>Seite nicht gefunden</h1>
        <p>Die angeforderte Seite konnte nicht gefunden werden.</p>
        <a href="/" class="btn">Zur Startseite</a>
    </div>
</body>
</html>';
    }
}