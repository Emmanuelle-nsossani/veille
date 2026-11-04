<?php
declare(strict_types=1);

/**
 * Récupération quotidienne des flux, à lancer par le cron de Hostinger :
 *   php /home/<utilisateur>/domains/emmanuellecode.fr/public_html/veille/cron/fetch.php
 * (le chemin exact est affiché sur la page /sources du site)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI uniquement');
}

define('ROOT', dirname(__DIR__));

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) === 0) {
        $file = ROOT . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

use App\Core\Database;
use App\Models\Article;
use App\Models\Source;
use App\Services\FeedFetcher;
use App\Services\ThemeClassifier;

$config = require ROOT . '/config/config.php';
date_default_timezone_set($config['timezone']);
$db = Database::get($config);

$fetcher = new FeedFetcher($config, new Source($db), new Article($db), new ThemeClassifier(require ROOT . '/config/themes.php'));
$report = $fetcher->run();

echo '[' . date('Y-m-d H:i') . "] {$report['new']} nouveaux, {$report['errors']} erreur(s), {$report['purged']} purgé(s)\n";
echo implode("\n", $report['details']) . "\n";
