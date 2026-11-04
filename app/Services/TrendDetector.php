<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Repère les sujets tendances : les termes qui reviennent dans plusieurs
 * articles de sources différentes sur les dernières 48 h, et plus souvent
 * que d'habitude (comparaison avec les 14 jours précédents).
 */
class TrendDetector
{
    public const RECENT_HOURS = 48;
    private const BASE_DAYS = 14;
    private const MIN_ARTICLES = 3;
    private const MIN_SOURCES = 2;
    private const MAX_TRENDS = 6;
    private const MAX_GROWTH = 6.0;
    private const MIN_HISTORY = 100; // articles sur la période de référence

    public function __construct(private PDO $db)
    {
    }

    /**
     * @return array<int, array{key:string, slug:string, label:string, terms:string[], article_ids:int[],
     *     sources:int, growth:float, score:float}>
     */
    public function detect(?int $now = null): array
    {
        $now ??= time();
        $recentFrom = $now - self::RECENT_HOURS * 3600;
        $baseFrom = $recentFrom - self::BASE_DAYS * 86400;

        $stmt = $this->db->prepare('SELECT id, source_id, title, published_at FROM articles WHERE published_at >= ?');
        $stmt->execute([date('Y-m-d H:i:s', $baseFrom)]);

        $recent = [];   // clé => [ids d'articles]
        $sources = [];  // clé => [source_id => true]
        $labels = [];   // clé => [forme affichée => nombre]
        $base = [];     // clé => nombre d'articles sur la période de référence
        $published = [];
        $baseRows = 0;

        foreach ($stmt as $row) {
            $isRecent = strtotime($row['published_at']) >= $recentFrom;
            $baseRows += $isRecent ? 0 : 1;
            foreach (Terms::extract($row['title']) as $key => $display) {
                if (!$isRecent) {
                    $base[$key] = ($base[$key] ?? 0) + 1;
                    continue;
                }
                $recent[$key][] = (int) $row['id'];
                $sources[$key][(int) $row['source_id']] = true;
                $labels[$key][$display] = ($labels[$key][$display] ?? 0) + 1;
            }
            if ($isRecent) {
                $published[(int) $row['id']] = $row['published_at'];
            }
        }

        // Sans historique suffisant (premiers jours), la hausse ne veut rien dire : on l'ignore
        $hasHistory = $baseRows >= self::MIN_HISTORY;

        // Candidats : assez d'articles et au moins deux sources
        $candidates = [];
        foreach ($recent as $key => $ids) {
            $n = count($ids);
            $nSources = count($sources[$key]);
            if ($n < self::MIN_ARTICLES || $nSources < self::MIN_SOURCES) {
                continue;
            }
            $perDayRecent = $n / (self::RECENT_HOURS / 24);
            $perDayBase = (($base[$key] ?? 0) + 1) / self::BASE_DAYS;
            $growth = $hasHistory ? min(self::MAX_GROWTH, $perDayRecent / $perDayBase) : 1.0;
            $isPair = str_contains($key, ' ');

            $candidates[$key] = [
                'key'     => $key,
                'ids'     => $ids,
                'sources' => $nSources,
                'growth'  => round($growth, 1),
                'score'   => $n * sqrt($growth) * (1 + 0.2 * ($nSources - self::MIN_SOURCES)) * ($isPair ? 1.3 : 1.0),
            ];
        }

        // Un mot seul déjà couvert par une paire qui le contient (« Apple » dans « Apple Vision ») s'efface
        foreach ($candidates as $key => $c) {
            if (str_contains($key, ' ')) {
                continue;
            }
            foreach ($candidates as $pairKey => $pair) {
                if (str_contains($pairKey, ' ') && in_array($key, explode(' ', $pairKey), true)
                    && count(array_intersect($pair['ids'], $c['ids'])) >= 0.6 * count($c['ids'])) {
                    unset($candidates[$key]);
                    break;
                }
            }
        }

        uasort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);

        // Regroupement : un terme dont les articles recoupent ceux d'une tendance déjà retenue la rejoint
        $trends = [];
        foreach ($candidates as $c) {
            foreach ($trends as &$trend) {
                $overlap = count(array_intersect($c['ids'], $trend['article_ids']));
                if ($overlap >= 0.5 * count($c['ids'])) {
                    $trend['terms'][] = $c['key'];
                    $trend['article_ids'] = array_values(array_unique(array_merge($trend['article_ids'], $c['ids'])));
                    continue 2;
                }
            }
            unset($trend);
            if (count($trends) < self::MAX_TRENDS) {
                $trends[] = [
                    'key'         => $c['key'],
                    'slug'        => Terms::slug($c['key']),
                    'label'       => $this->bestLabel($labels[$c['key']]),
                    'terms'       => [$c['key']],
                    'article_ids' => $c['ids'],
                    'sources'     => $c['sources'],
                    'growth'      => $c['growth'],
                    'score'       => round($c['score'], 2),
                ];
            }
        }
        unset($trend);

        foreach ($trends as &$trend) {
            usort($trend['article_ids'], fn ($a, $b) => strcmp($published[$b] ?? '', $published[$a] ?? ''));
            $trend['sources'] = $this->countSources($trend['article_ids']);
        }
        unset($trend);

        return $trends;
    }

    /**
     * Articles connectés à une tendance, hors articles de la tendance :
     * ceux qui partagent le plus de termes avec elle sur toute la période conservée.
     *
     * @return int[]
     */
    public function related(array $trend, int $limit = 6): array
    {
        $inTrend = array_flip($trend['article_ids']);

        // Profil de la tendance : termes fréquents dans ses articles (titre + résumé)
        $profile = [];
        foreach ($this->fetchTexts($trend['article_ids']) as $row) {
            foreach (array_keys(Terms::extract($row['title'] . ' ' . $row['summary'], false)) as $key) {
                $profile[$key] = ($profile[$key] ?? 0) + 1;
            }
        }
        // Les termes de la tendance comptent toujours, même s'ils sont rares dans les résumés
        foreach ($trend['terms'] as $term) {
            foreach (explode(' ', $term) as $word) {
                if (!preg_match('/^[\d.]+$/', $word)) {
                    $profile[$word] = max($profile[$word] ?? 0, count($trend['article_ids']));
                }
            }
        }
        $minShared = max(2, (int) ceil(count($trend['article_ids']) * 0.3));
        $profile = array_filter($profile, fn ($n) => $n >= min($minShared, 2));
        arsort($profile);
        $profile = array_slice($profile, 0, 15, true);
        // Mots distinctifs de la tendance (« gemini » pour « gemini 3 »)
        $mainWords = [];
        foreach ($trend['terms'] as $term) {
            foreach (explode(' ', $term) as $word) {
                if (!preg_match('/^[\d.]+$/', $word)) {
                    $mainWords[$word] = true;
                }
            }
        }

        $scores = [];
        $stmt = $this->db->query('SELECT id, title, summary, published_at FROM articles ORDER BY published_at DESC LIMIT 3000');
        foreach ($stmt as $row) {
            $id = (int) $row['id'];
            if (isset($inTrend[$id])) {
                continue;
            }
            $terms = Terms::extract($row['title'] . ' ' . $row['summary'], false);
            $matched = array_intersect_key($profile, $terms);
            $mainHits = count(array_intersect_key($mainWords, $terms));
            if (count($matched) < 2 && $mainHits === 0) {
                continue;
            }
            $titleTerms = Terms::extract($row['title'], false);
            $score = 0.0;
            foreach ($matched as $key => $weight) {
                $score += $weight * (isset($titleTerms[$key]) ? 2 : 1);
            }
            $scores[$id] = $score + 5 * $mainHits;
        }

        arsort($scores);
        return array_map('intval', array_slice(array_keys($scores), 0, $limit));
    }

    private function bestLabel(array $forms): string
    {
        // La forme la plus fréquente ; à égalité, celle avec des majuscules (noms propres)
        uksort($forms, function ($a, $b) use ($forms) {
            return [$forms[$b], preg_match('/\p{Lu}/u', (string) $b)] <=> [$forms[$a], preg_match('/\p{Lu}/u', (string) $a)];
        });
        $label = (string) array_key_first($forms);
        return mb_strtoupper(mb_substr($label, 0, 1)) . mb_substr($label, 1);
    }

    private function countSources(array $ids): int
    {
        if (!$ids) {
            return 0;
        }
        $in = implode(',', array_map('intval', $ids));
        return (int) $this->db->query("SELECT COUNT(DISTINCT source_id) FROM articles WHERE id IN ($in)")->fetchColumn();
    }

    private function fetchTexts(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $in = implode(',', array_map('intval', $ids));
        return $this->db->query("SELECT id, title, summary FROM articles WHERE id IN ($in)")->fetchAll();
    }
}
