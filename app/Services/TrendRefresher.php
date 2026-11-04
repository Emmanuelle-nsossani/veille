<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Article;
use App\Models\Trend;
use PDO;

/**
 * Recalcule les tendances après chaque récupération :
 * détection, articles connectés et résumé (réutilisé tant que la tendance
 * garde les mêmes articles, pour ne pas rappeler l'IA inutilement).
 */
class TrendRefresher
{
    /** À augmenter quand la méthode de détection change, pour forcer un recalcul en ligne. */
    private const VERSION = '2';

    public function __construct(private PDO $db, private array $config)
    {
    }

    /** @return int nombre de tendances */
    public function run(): int
    {
        $detector = new TrendDetector($this->db, $this->config);
        $articles = new Article($this->db);
        $model = new Trend($this->db);
        $summarizer = new Summarizer($this->config);

        $previous = [];
        foreach ($model->all() as $old) {
            $previous[$old['slug']] = $old;
        }

        $rows = [];
        foreach ($detector->detect() as $trend) {
            $trend['related_ids'] = $detector->related($trend);
            $old = $previous[$trend['slug']] ?? null;
            $sameArticles = $old && $this->sameSet($old['article_ids'], $trend['article_ids']);

            if ($sameArticles && $old['summary'] !== '' && ($old['summary_source'] === 'ia' || $summarizer->apiKey() === '')) {
                $trend['summary'] = $old['summary'];
                $trend['summary_source'] = $old['summary_source'];
            } else {
                $summary = $summarizer->summarize($trend['label'], $articles->byIds($trend['article_ids']));
                $trend['summary'] = $summary['text'];
                $trend['summary_source'] = $summary['source'];
            }
            $rows[] = $trend;
        }

        $model->replace($rows);
        $this->db->prepare("INSERT OR REPLACE INTO meta (key, value) VALUES ('trends_version', ?)")->execute([self::VERSION]);
        return count($rows);
    }

    /** Calcule les tendances si cela n'a jamais été fait avec la méthode actuelle (ex. juste après une mise à jour). */
    public function runIfNeverDone(): void
    {
        $version = $this->db->query("SELECT value FROM meta WHERE key = 'trends_version'")->fetchColumn();
        if ($version !== self::VERSION) {
            $this->run();
        }
    }

    private function sameSet(array $a, array $b): bool
    {
        sort($a);
        sort($b);
        return $a === $b;
    }
}
