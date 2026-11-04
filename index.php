<?php
declare(strict_types=1);

/**
 * Veille numérique — contrôleur frontal (MVC).
 * Toutes les requêtes passent ici (voir .htaccess).
 */

define('ROOT', __DIR__);

// Serveur de test local (php -S) : laisser passer les fichiers statiques
if (PHP_SAPI === 'cli-server' && str_starts_with((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/assets/')
    && is_file(ROOT . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = ROOT . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

use App\Core\Router;
use App\Core\Auth;

$config = require ROOT . '/config/config.php';
date_default_timezone_set($config['timezone']);

Auth::start();

$router = new Router($config);

// Pages
$router->get('/', 'ArticleController@index');
$router->get('/login', 'AuthController@showLogin');
$router->post('/login', 'AuthController@login');
$router->post('/logout', 'AuthController@logout');
$router->get('/sources', 'SourceController@index');
$router->post('/sources/add', 'SourceController@add');
$router->post('/sources/toggle', 'SourceController@toggle');
$router->post('/sources/delete', 'SourceController@delete');
$router->post('/refresh', 'SourceController@refresh');

// Actions AJAX
$router->post('/articles/favorite', 'ArticleController@toggleFavorite');
$router->post('/articles/read', 'ArticleController@toggleRead');
$router->post('/articles/read-all', 'ArticleController@markAllRead');

// API JSON (future application mobile) + déclencheur cron par URL
$router->get('/api/articles', 'ApiController@articles');
$router->get('/api/themes', 'ApiController@themes');
$router->get('/cron', 'CronController@run');

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
