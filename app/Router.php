<?php

/**
 * Enrutador HTTP simple con soporte de middleware y parámetros de ruta.
 */
class Router
{
    private array $routes = [];
    private array $middleware = [];

    /**
     * Registra ruta GET.
     */
    public function get(string $path, $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    /**
     * Registra ruta POST.
     */
    public function post(string $path, $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    /**
     * Agrega una ruta con método, handler y middleware.
     */
    private function addRoute(string $method, string $path, $handler, array $middleware): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middleware' => $middleware
        ];
    }

    /**
     * Resuelve la solicitud, ejecuta middleware y despacha el handler.
     */
    public function dispatch(): void
    {
        $requestMethod = $_SERVER['REQUEST_METHOD'];
        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        if ($requestUri !== '/' && substr($requestUri, -1) === '/') {
            $requestUri = rtrim($requestUri, '/');
        }

        foreach ($this->routes as $route) {
            if ($route['method'] === $requestMethod && $this->matchPath($route['path'], $requestUri)) {
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

                $params = $this->extractParams($route['path'], $requestUri);

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

        $this->notFound();
    }

    /**
     * Compara path de ruta con URI, soportando parámetros `{}`.
     */
    private function matchPath(string $routePath, string $requestUri): bool
    {
        $pattern = preg_replace('/\{([^}]+)\}/', '([^/]+)', $routePath);
        $pattern = '#^' . $pattern . '$#';

        return preg_match($pattern, $requestUri);
    }

    /**
     * Extrae parámetros nombrados desde la URI según el patrón de la ruta.
     */
    private function extractParams(string $routePath, string $requestUri): array
    {
        $params = [];

        preg_match_all('/\{([^}]+)\}/', $routePath, $paramNames);

        $pattern = preg_replace('/\{([^}]+)\}/', '([^/]+)', $routePath);
        $pattern = '#^' . $pattern . '$#';

        if (preg_match($pattern, $requestUri, $matches)) {
            array_shift($matches);

            foreach ($paramNames[1] as $index => $paramName) {
                $params[$paramName] = $matches[$index] ?? null;
            }
        }

        return $params;
    }

    /**
     * Respuesta 404 para rutas no encontradas.
     */
    private function notFound(): void
    {
        http_response_code(404);
        echo "404 - Página no encontrada";
    }

    /**
     * Redirección con código de estado.
     */
    public function redirect(string $url, int $code = 302): void
    {
        http_response_code($code);
        header("Location: $url");
        exit;
    }
}