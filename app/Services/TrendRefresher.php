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
    public function __construct(private PDO $db, private array $config)
    {
    }

    /** @return int nombre de tendances */
    public function run(): int
    {
        $detector = new TrendDetector($this->db);
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
        return count($rows);
    }

    private function sameSet(array $a, array $b): bool
    {
        sort($a);
        sort($b);
        return $a === $b;
    }
}
