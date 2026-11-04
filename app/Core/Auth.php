<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Authentification à un seul utilisateur.
 * storage/secrets.json contient le hash du mot de passe et le jeton
 * de l'API et du cron. Le supprimer réinitialise les deux.
 */
class Auth
{
    private const SESSION_DAYS = 30;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $lifetime = self::SESSION_DAYS * 86400;
        $dir = ROOT . '/storage/sessions';
        Database::ensureStorage(ROOT . '/storage');
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        // Sessions rangées dans storage/ pour ne pas être effacées par le nettoyage du serveur mutualisé
        session_save_path($dir);
        ini_set('session.gc_maxlifetime', (string) $lifetime);
        ini_set('session.use_strict_mode', '1');
        session_name('veille');
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => '/',
            'secure'   => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function check(): bool
    {
        return !empty($_SESSION['auth']);
    }

    public static function isSetUp(array $config): bool
    {
        return !empty(self::secrets($config)['password_hash']);
    }

    public static function setUp(array $config, string $password): void
    {
        $secrets = [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'token'         => bin2hex(random_bytes(24)),
            'created_at'    => date('c'),
        ];
        Database::ensureStorage(dirname($config['secrets_path']));
        file_put_contents($config['secrets_path'], json_encode($secrets, JSON_PRETTY_PRINT), LOCK_EX);
        @chmod($config['secrets_path'], 0600);
    }

    public static function attempt(array $config, string $password): bool
    {
        $hash = self::secrets($config)['password_hash'] ?? '';
        if ($hash === '' || !password_verify($password, $hash)) {
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['auth'] = true;
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function validToken(array $config, ?string $token): bool
    {
        $expected = self::secrets($config)['token'] ?? '';
        return $expected !== '' && is_string($token) && $token !== '' && hash_equals($expected, $token);
    }

    public static function secrets(array $config): array
    {
        $file = $config['secrets_path'];
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['csrf'];
    }

    public static function validCsrf(?string $token): bool
    {
        return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
    }

    private static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    }
}
