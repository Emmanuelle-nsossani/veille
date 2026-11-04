<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Articles récupérés depuis les flux.
 * Filtres acceptés : theme, q, view (all|unread|favorites|today), since (API).
 */
class Article
{
    public function __construct(private PDO $db)
    {
    }

    public function search(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->where($filters);
        $stmt = $this->db->prepare(
            "SELECT a.id, a.source_id, s.name AS source_name, a.url, a.title, a.summary, a.themes,
                    a.published_at, a.fetched_at, a.is_read, a.is_favorite
             FROM articles a JOIN sources s ON s.id = a.source_id
             $where
             ORDER BY a.published_at DESC, a.id DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'hydrate'], $stmt->fetchAll());
    }

    /** Articles par identifiants, dans l'ordre donné (ceux qui n'existent plus sont ignorés). */
    public function byIds(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) {
            return [];
        }
        $in = implode(',', $ids);
        $rows = $this->db->query(
            "SELECT a.id, a.source_id, s.name AS source_name, a.url, a.title, a.summary, a.themes,
                    a.published_at, a.fetched_at, a.is_read, a.is_favorite
             FROM articles a JOIN sources s ON s.id = a.source_id
             WHERE a.id IN ($in)"
        )->fetchAll();
        $byId = [];
        foreach ($rows as $row) {
            $byId[(int) $row['id']] = $this->hydrate($row);
        }
        return array_values(array_filter(array_map(fn ($id) => $byId[$id] ?? null, $ids)));
    }

    public function count(array $filters): int
    {
        [$where, $params] = $this->where($filters);
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM articles a $where");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** Non lus, favoris, publiés aujourd'hui. */
    public function stats(): array
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(is_read = 0), 0) AS unread,
                    COALESCE(SUM(is_favorite = 1), 0) AS favorites,
                    COALESCE(SUM(published_at >= :today), 0) AS today,
                    COUNT(*) AS total
             FROM articles'
        );
        $stmt->execute([':today' => date('Y-m-d 00:00:00')]);
        return array_map('intval', $stmt->fetch());
    }

    /** Nombre d'articles non lus par thème. */
    public function countByTheme(array $keys): array
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM articles WHERE is_read = 0 AND themes LIKE :theme');
        $out = [];
        foreach ($keys as $key) {
            $stmt->execute([':theme' => '%,' . $key . ',%']);
            $out[$key] = (int) $stmt->fetchColumn();
        }
        return $out;
    }

    public function markAllRead(array $filters): int
    {
        [$where, $params] = $this->where($filters);
        $stmt = $this->db->prepare(
            "UPDATE articles SET is_read = 1
             WHERE is_read = 0 AND id IN (SELECT a.id FROM articles a $where)"
        );
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /** Inverse is_read ou is_favorite. Renvoie la nouvelle valeur, ou null si l'article n'existe pas. */
    public function toggle(int $id, string $field): ?bool
    {
        if (!in_array($field, ['is_read', 'is_favorite'], true)) {
            throw new \InvalidArgumentException("Champ non modifiable : $field");
        }
        $stmt = $this->db->prepare("UPDATE articles SET $field = 1 - $field WHERE id = ?");
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            return null;
        }
        $value = $this->db->prepare("SELECT $field FROM articles WHERE id = ?");
        $value->execute([$id]);
        return (bool) $value->fetchColumn();
    }

    /** Ajoute un article s'il n'existe pas déjà pour cette source. Renvoie true s'il est nouveau. */
    public function insert(int $sourceId, array $item, array $themes): bool
    {
        $stmt = $this->db->prepare(
            'INSERT OR IGNORE INTO articles (source_id, guid, url, title, summary, themes, published_at, fetched_at)
             VALUES (:source_id, :guid, :url, :title, :summary, :themes, :published_at, :fetched_at)'
        );
        $stmt->execute([
            ':source_id'    => $sourceId,
            ':guid'         => $item['guid'],
            ':url'          => $item['url'],
            ':title'        => $item['title'],
            ':summary'      => $item['summary'],
            ':themes'       => ',' . implode(',', $themes) . ($themes ? ',' : ''),
            ':published_at' => $item['published_at'],
            ':fetched_at'   => date('Y-m-d H:i:s'),
        ]);
        return $stmt->rowCount() > 0;
    }

    /** Supprime les articles non favoris plus vieux que $days jours. */
    public function purge(int $days): int
    {
        $stmt = $this->db->prepare('DELETE FROM articles WHERE is_favorite = 0 AND published_at < ?');
        $stmt->execute([date('Y-m-d H:i:s', strtotime("-$days days"))]);
        return $stmt->rowCount();
    }

    private function where(array $filters): array
    {
        $where = [];
        $params = [];

        if (($filters['theme'] ?? '') !== '') {
            $where[] = 'a.themes LIKE :theme';
            $params[':theme'] = '%,' . $filters['theme'] . ',%';
        }
        if (($filters['q'] ?? '') !== '') {
            $like = '%' . addcslashes($filters['q'], '%_\\') . '%';
            $where[] = "(a.title LIKE :q1 ESCAPE '\\' OR a.summary LIKE :q2 ESCAPE '\\')";
            $params[':q1'] = $like;
            $params[':q2'] = $like;
        }
        switch ($filters['view'] ?? 'all') {
            case 'unread':
                $where[] = 'a.is_read = 0';
                break;
            case 'favorites':
                $where[] = 'a.is_favorite = 1';
                break;
            case 'today':
                $where[] = 'a.published_at >= :today';
                $params[':today'] = date('Y-m-d 00:00:00');
                break;
        }
        if (($filters['since'] ?? '') !== '') {
            $where[] = 'a.published_at >= :since';
            $params[':since'] = $filters['since'];
        }

        return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
    }

    private function hydrate(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['source_id'] = (int) $row['source_id'];
        $row['themes'] = array_values(array_filter(explode(',', $row['themes'])));
        $row['is_read'] = (bool) $row['is_read'];
        $row['is_favorite'] = (bool) $row['is_favorite'];
        return $row;
    }
}
