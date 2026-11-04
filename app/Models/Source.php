<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Flux RSS/Atom suivis.
 */
class Source
{
    public function __construct(private PDO $db)
    {
    }

    public function all(): array
    {
        return $this->db->query(
            'SELECT s.*, (SELECT COUNT(*) FROM articles a WHERE a.source_id = s.id) AS article_count
             FROM sources s
             ORDER BY s.active DESC, s.name COLLATE NOCASE'
        )->fetchAll();
    }

    public function active(): array
    {
        return $this->db->query('SELECT * FROM sources WHERE active = 1 ORDER BY id')->fetchAll();
    }

    /** Renvoie false si l'adresse existe déjà. */
    public function add(string $name, string $url): bool
    {
        $stmt = $this->db->prepare('INSERT OR IGNORE INTO sources (name, url) VALUES (?, ?)');
        $stmt->execute([mb_substr($name, 0, 100), $url]);
        return $stmt->rowCount() > 0;
    }

    public function toggle(int $id): void
    {
        $this->db->prepare('UPDATE sources SET active = 1 - active WHERE id = ?')->execute([$id]);
    }

    /** Supprime la source et ses articles (clé étrangère ON DELETE CASCADE). */
    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM sources WHERE id = ?')->execute([$id]);
    }

    public function recordResult(int $id, ?string $error, int $new): void
    {
        $this->db->prepare(
            'UPDATE sources SET last_status = ?, last_error = ?, last_new = ?, last_fetched_at = ? WHERE id = ?'
        )->execute([$error === null ? 'ok' : 'error', $error, $new, date('Y-m-d H:i:s'), $id]);
    }
}
