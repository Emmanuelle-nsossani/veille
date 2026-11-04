<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Connexion SQLite unique. Crée le dossier storage/, le schéma
 * et les sources de départ (config/feeds.php) au premier appel.
 */
class Database
{
    private static ?PDO $pdo = null;

    public static function get(array $config): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        self::ensureStorage(dirname($config['db_path']));

        $pdo = new PDO('sqlite:' . $config['db_path'], null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec('PRAGMA journal_mode = WAL');

        self::migrate($pdo);
        self::seed($pdo);

        return self::$pdo = $pdo;
    }

    /** Crée storage/ et l'interdit au web, même si le .htaccess racine manquait. */
    public static function ensureStorage(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $guard = $dir . '/.htaccess';
        if (!is_file($guard)) {
            file_put_contents($guard, "Require all denied\nDeny from all\n");
        }
    }

    private static function migrate(PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS meta (
                key   TEXT PRIMARY KEY,
                value TEXT
            );
            CREATE TABLE IF NOT EXISTS sources (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                name            TEXT NOT NULL,
                url             TEXT NOT NULL UNIQUE,
                active          INTEGER NOT NULL DEFAULT 1,
                last_status     TEXT,
                last_error      TEXT,
                last_new        INTEGER NOT NULL DEFAULT 0,
                last_fetched_at TEXT,
                created_at      TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
            );
            CREATE TABLE IF NOT EXISTS articles (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                source_id    INTEGER NOT NULL REFERENCES sources(id) ON DELETE CASCADE,
                guid         TEXT NOT NULL,
                url          TEXT NOT NULL,
                title        TEXT NOT NULL,
                summary      TEXT NOT NULL DEFAULT '',
                themes       TEXT NOT NULL DEFAULT ',',
                published_at TEXT NOT NULL,
                fetched_at   TEXT NOT NULL,
                is_read      INTEGER NOT NULL DEFAULT 0,
                is_favorite  INTEGER NOT NULL DEFAULT 0,
                UNIQUE (source_id, guid)
            );
            CREATE TABLE IF NOT EXISTS trends (
                slug           TEXT PRIMARY KEY,
                term           TEXT NOT NULL,
                label          TEXT NOT NULL,
                terms          TEXT NOT NULL DEFAULT '[]',
                article_ids    TEXT NOT NULL DEFAULT '[]',
                related_ids    TEXT NOT NULL DEFAULT '[]',
                sources        INTEGER NOT NULL DEFAULT 0,
                growth         REAL NOT NULL DEFAULT 1,
                score          REAL NOT NULL DEFAULT 0,
                position       INTEGER NOT NULL DEFAULT 0,
                summary        TEXT NOT NULL DEFAULT '',
                summary_source TEXT NOT NULL DEFAULT 'extraits',
                updated_at     TEXT NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_articles_published ON articles (published_at DESC);
            CREATE INDEX IF NOT EXISTS idx_articles_read ON articles (is_read);
            CREATE INDEX IF NOT EXISTS idx_articles_fav ON articles (is_favorite);
        SQL);
    }

    /** Ajoute les sources de départ une seule fois (pas après une suppression volontaire). */
    private static function seed(PDO $pdo): void
    {
        $done = $pdo->query("SELECT value FROM meta WHERE key = 'seeded'")->fetchColumn();
        if ($done) {
            return;
        }
        $feeds = require ROOT . '/config/feeds.php';
        $insert = $pdo->prepare('INSERT OR IGNORE INTO sources (name, url) VALUES (?, ?)');
        $pdo->beginTransaction();
        foreach ($feeds as $feed) {
            $insert->execute([$feed['name'], $feed['url']]);
        }
        $pdo->exec("INSERT OR REPLACE INTO meta (key, value) VALUES ('seeded', '1')");
        $pdo->commit();
    }
}
