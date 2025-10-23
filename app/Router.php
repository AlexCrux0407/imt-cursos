<?php

class Router
{
    private array $routes = [];
    private array $middleware = [];

    public function get(string $path, $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    private function addRoute(string $method, string $path, $handler, array $middleware): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middleware' => $middleware
        ];
    }

    public function dispatch(): void
    {
        $requestMethod = $_SERVER['REQUEST_METHOD'];
        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Remover trailing slash excepto para la raíz
        if ($requestUri !== '/' && substr($requestUri, -1) === '/') {
            $requestUri = rtrim($requestUri, '/');
        }

        foreach ($this->routes as $route) {
            if ($route['method'] === $requestMethod && $this->matchPath($route['path'], $requestUri)) {
                // Ejecutar middleware (soporta parámetros con notación 'Clase:parametro')
                foreach ($route['middleware'] as $mw) {
                    $middleware = null;

                    if (is_string($mw)) {
                        if (strpos($mw, ':') !== false) {
                            [$mwClass, $mwParam] = explode(':', $mw, 2);
                            $middleware = new $mwClass($mwParam);
                        } else {
                            $middleware = new $mw();
                        }
                    } elseif (is_array($mw) && count($mw) > 0) {
                        $mwClass = array_shift($mw);
                        $middleware = new $mwClass(...$mw);
                    } elseif (is_object($mw)) {
                        $middleware = $mw;
                    }

                    if ($middleware) {
                        $middleware->handle();
                    }
                }

                // Extraer parámetros de la URL
                $params = $this->extractParams($route['path'], $requestUri);
                
                // Ejecutar handler
                if (is_string($route['handler']) && strpos($route['handler'], '@') !== false) {
                    [$controllerClass, $method] = explode('@', $route['handler']);
                    $controller = new $controllerClass();
                    $controller->$method($params);
                } elseif (is_callable($route['handler'])) {
                    call_user_func($route['handler'], $params);
                }
                return;
            }
        }

        // 404 - Ruta no encontrada
        $this->notFound();
    }

    private function matchPath(string $routePath, string $requestUri): bool
    {
        // Convertir parámetros de ruta a regex
        $pattern = preg_replace('/\{([^}]+)\}/', '([^/]+)', $routePath);
        $pattern = '#^' . $pattern . '$#';
        
        return preg_match($pattern, $requestUri);
    }

    private function extractParams(string $routePath, string $requestUri): array
    {
        $params = [];
        
        // Extraer nombres de parámetros
        preg_match_all('/\{([^}]+)\}/', $routePath, $paramNames);
        
        // Extraer valores de parámetros
        $pattern = preg_replace('/\{([^}]+)\}/', '([^/]+)', $routePath);
        $pattern = '#^' . $pattern . '$#';
        
        if (preg_match($pattern, $requestUri, $matches)) {
            array_shift($matches); // Remover match completo
            
            foreach ($paramNames[1] as $index => $paramName) {
                $params[$paramName] = $matches[$index] ?? null;
            }
        }
        
        return $params;
    }

    private function notFound(): void
    {
        http_response_code(404);
        echo "404 - Página no encontrada";
    }

    public function redirect(string $url, int $code = 302): void
    {
        http_response_code($code);
        header("Location: $url");
        exit;
    }
}