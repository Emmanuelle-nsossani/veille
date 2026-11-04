<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Sujets tendances calculés à chaque récupération (voir TrendRefresher).
 */
class Trend
{
    public function __construct(private PDO $db)
    {
    }

    public function all(): array
    {
        $rows = $this->db->query('SELECT * FROM trends ORDER BY position')->fetchAll();
        return array_map([$this, 'hydrate'], $rows);
    }

    public function find(string $slug): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM trends WHERE slug = ?');
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    /** Remplace toutes les tendances par la nouvelle liste. */
    public function replace(array $trends): void
    {
        $insert = $this->db->prepare(
            'INSERT INTO trends (slug, term, label, terms, article_ids, related_ids, sources, growth, score, position,
                                 summary, summary_source, updated_at)
             VALUES (:slug, :term, :label, :terms, :article_ids, :related_ids, :sources, :growth, :score, :position,
                     :summary, :summary_source, :updated_at)'
        );
        $this->db->beginTransaction();
        try {
            $this->db->exec('DELETE FROM trends');
            foreach (array_values($trends) as $position => $t) {
                $insert->execute([
                    ':slug'           => $t['slug'],
                    ':term'           => $t['key'],
                    ':label'          => $t['label'],
                    ':terms'          => json_encode($t['terms'], JSON_UNESCAPED_UNICODE),
                    ':article_ids'    => json_encode($t['article_ids']),
                    ':related_ids'    => json_encode($t['related_ids']),
                    ':sources'        => $t['sources'],
                    ':growth'         => $t['growth'],
                    ':score'          => $t['score'],
                    ':position'       => $position,
                    ':summary'        => $t['summary'],
                    ':summary_source' => $t['summary_source'],
                    ':updated_at'     => date('Y-m-d H:i:s'),
                ]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function hydrate(array $row): array
    {
        $row['terms'] = json_decode($row['terms'], true) ?: [];
        $row['article_ids'] = array_map('intval', json_decode($row['article_ids'], true) ?: []);
        $row['related_ids'] = array_map('intval', json_decode($row['related_ids'], true) ?: []);
        $row['sources'] = (int) $row['sources'];
        $row['growth'] = (float) $row['growth'];
        $row['articles'] = count($row['article_ids']);
        return $row;
    }
}
