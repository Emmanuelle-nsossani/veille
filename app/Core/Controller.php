<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

require_once __DIR__ . '/helpers.php';

/**
 * Contrôleur parent : accès à la config, à la base et aux thèmes,
 * rendu des vues, redirections, JSON, messages flash et contrôles.
 */
class Controller
{
    protected PDO $db;
    protected array $themes;
    /** Titre de page, modifiable depuis une vue avec $this->pageTitle = '…'. */
    public ?string $pageTitle = null;

    public function __construct(protected array $config)
    {
        $this->db = Database::get($config);
        $this->themes = require ROOT . '/config/themes.php';
        $GLOBALS['veille_base_path'] = rtrim((string) $config['base_path'], '/');
    }

    protected function render(string $view, array $data = [], string $layout = 'layouts/main'): void
    {
        $data += [
            'config'   => $this->config,
            'themes'   => $this->themes,
            'loggedIn' => Auth::check(),
            'csrf'     => Auth::csrfToken(),
        ];
        $content = $this->capture($view, $data);
        $data['content'] = $content;
        $data['title'] ??= $this->pageTitle;
        $data['flashes'] = $this->pullFlashes();
        header('Content-Type: text/html; charset=utf-8');
        echo $this->capture($layout, $data);
    }

    private function capture(string $view, array $data): string
    {
        $file = ROOT . '/app/Views/' . $view . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Vue introuvable : $view");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }

    protected function redirect(string $path): never
    {
        header('Location: ' . url($path), true, 303);
        exit;
    }

    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    protected function flash(string $type, string $message, array $details = []): void
    {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message, 'details' => $details];
    }

    private function pullFlashes(): array
    {
        $flashes = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $flashes;
    }

    protected function requireLogin(): void
    {
        if (Auth::check()) {
            return;
        }
        if ($this->wantsJson()) {
            $this->json(['error' => 'Session expirée'], 401);
            exit;
        }
        $this->redirect('/login');
    }

    protected function requireCsrf(): void
    {
        $token = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (Auth::validCsrf(is_string($token) ? $token : null)) {
            return;
        }
        if ($this->wantsJson()) {
            $this->json(['error' => 'Jeton de sécurité invalide'], 403);
            exit;
        }
        $this->flash('error', 'La page avait expiré, réessaie.');
        $this->redirect('/');
    }

    private function wantsJson(): bool
    {
        return isset($_SERVER['HTTP_X_CSRF_TOKEN'])
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }

    public function notFound(): void
    {
        http_response_code(404);
        $this->render('errors/404', ['title' => 'Page introuvable']);
    }
}
