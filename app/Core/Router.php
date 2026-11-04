<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Routeur minimal : associe "MÉTHODE /chemin" à "Contrôleur@action".
 */
class Router
{
    private array $routes = ['GET' => [], 'POST' => []];

    public function __construct(private array $config)
    {
    }

    public function get(string $path, string $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, string $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method) === 'HEAD' ? 'GET' : strtoupper($method);
        $path = $this->normalize($uri);
        $handler = $this->routes[$method][$path] ?? null;

        try {
            if ($handler === null) {
                $allowed = array_keys(array_filter($this->routes, fn (array $r) => isset($r[$path])));
                if ($allowed) {
                    header('Allow: ' . implode(', ', $allowed));
                    http_response_code(405);
                    echo 'Méthode non autorisée';
                    return;
                }
                (new Controller($this->config))->notFound();
                return;
            }

            [$controller, $action] = explode('@', $handler, 2);
            $class = 'App\\Controllers\\' . $controller;
            (new $class($this->config))->$action();
        } catch (\Throwable $e) {
            error_log('[Veille] ' . $e);
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/plain; charset=utf-8');
            }
            echo "Erreur interne. Le détail est dans le journal d'erreurs PHP.";
        }
    }

    private function normalize(string $uri): string
    {
        $path = (string) parse_url($uri, PHP_URL_PATH);
        $base = rtrim((string) $this->config['base_path'], '/');
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        if (str_starts_with($path, '/index.php')) {
            $path = substr($path, strlen('/index.php'));
        }
        $path = '/' . trim($path, '/');
        return $path;
    }
}
