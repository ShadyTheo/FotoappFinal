<?php

namespace App;

class Router {
    private $routes = [];
    
    public function get($path, $callback) {
        $this->routes['GET'][$path] = $callback;
    }
    
    public function post($path, $callback) {
        $this->routes['POST'][$path] = $callback;
    }
    
    public function dispatch() {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Remove trailing slash except for root
        if ($uri !== '/' && substr($uri, -1) === '/') {
            $uri = rtrim($uri, '/');
        }
        
        // Debug logging
        if (getenv('ENV') === 'development') {
            error_log("Router: $method $uri");
        }
        
        foreach ($this->routes[$method] ?? [] as $route => $callback) {
            $pattern = $this->convertRouteToRegex($route);
            
            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches); // Remove full match
                
                try {
                    if (is_array($callback)) {
                        $controller = new $callback[0]();
                        $method = $callback[1];
                        return $controller->$method(...$matches);
                    } else {
                        return $callback(...$matches);
                    }
                } catch (Exception $e) {
                    if (getenv('ENV') === 'development') {
                        echo 'Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString();
                    } else {
                        http_response_code(500);
                        echo 'Internal Server Error';
                    }
                    return;
                }
            }
        }
        
        // 404 Not Found
        http_response_code(404);
        echo '404 - Page not found';
    }
    
    private function convertRouteToRegex($route) {
        $pattern = preg_replace('/\{([^}]+)\}/', '([^/]+)', $route);
        return '#^' . $pattern . '$#';
    }
}