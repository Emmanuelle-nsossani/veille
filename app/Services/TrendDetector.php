<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Repère les sujets d'actualité sur une période glissante (3 mois par défaut) :
 * les sujets précis (noms propres, sigles, versions, expressions de deux mots)
 * repris par plusieurs articles et plusieurs sources. Les articles récents
 * pèsent plus lourd, et un sujet qui accélère sur les 14 derniers jours est mis en avant.
 */
class TrendDetector
{
    private const RECENT_DAYS = 14;     // pour mesurer la hausse
    private const MAX_GROWTH = 6.0;
    private const MIN_HISTORY_DAYS = 30; // historique minimal avant d'afficher une hausse

    private int $days;
    private int $minArticles;
    private int $minSources;
    private int $maxTrends;

    public function __construct(private PDO $db, array $config = [])
    {
        $this->days = max(2, (int) ($config['trend_days'] ?? 90));
        $this->minArticles = max(2, (int) ($config['trend_min_articles'] ?? 4));
        $this->minSources = max(1, (int) ($config['trend_min_sources'] ?? 3));
        $this->maxTrends = max(1, (int) ($config['trend_max'] ?? 8));
    }

    /**
     * @return array<int, array{key:string, slug:string, label:string, terms:string[], article_ids:int[],
     *     sources:int, growth:float, score:float, last_at:string}>
     */
    public function detect(?int $now = null): array
    {
        $now ??= time();
        $from = $now - $this->days * 86400;
        $recentFrom = $now - self::RECENT_DAYS * 86400;
        $tau = $this->days / 4; // un article de ~2 mois pèse environ 4 fois moins qu'un article du jour

        $stmt = $this->db->prepare('SELECT id, source_id, title, published_at FROM articles WHERE published_at >= ?');
        $stmt->execute([date('Y-m-d H:i:s', $from)]);

        $ids = $sources = $labels = $weight = $precise = $recent = [];
        $published = [];
        $total = 0;
        $oldest = $now;

        foreach ($stmt as $row) {
            $id = (int) $row['id'];
            $ts = (int) strtotime($row['published_at']);
            $oldest = min($oldest, $ts);
            $published[$id] = $row['published_at'];
            $total++;
            $w = exp(-max(0, $now - $ts) / 86400 / $tau);

            foreach (Terms::extract($row['title'], true, $specific) as $key => $display) {
                $ids[$key][] = $id;
                $sources[$key][(int) $row['source_id']] = true;
                $labels[$key][$display] = ($labels[$key][$display] ?? 0) + 1;
                $weight[$key] = ($weight[$key] ?? 0) + $w;
                $precise[$key] = ($precise[$key] ?? 0) + ($specific[$key] ? 1 : 0);
                $recent[$key] = ($recent[$key] ?? 0) + ($ts >= $recentFrom ? 1 : 0);
            }
        }
        if ($total === 0) {
            return [];
        }

        $historyDays = ($now - $oldest) / 86400;
        $hasHistory = $historyDays >= self::MIN_HISTORY_DAYS;
        $olderDays = max(1, min($this->days, $historyDays) - self::RECENT_DAYS);

        $candidates = [];
        foreach ($ids as $key => $list) {
            $n = count($list);
            $nSources = count($sources[$key]);
            if ($n < $this->minArticles || $nSources < $this->minSources) {
                continue;
            }
            $isPair = str_contains($key, ' ');
            $isPrecise = $precise[$key] / $n >= 0.5;
            if (!$isPrecise) {
                continue; // mot courant (« sécurité ») ou expression banale (« Google modifie ») : pas un sujet
            }

            $growth = 1.0;
            if ($hasHistory) {
                $ratePerDayRecent = $recent[$key] / self::RECENT_DAYS;
                $ratePerDayOlder = ($n - $recent[$key] + 1) / $olderDays;
                $growth = min(self::MAX_GROWTH, $ratePerDayRecent / $ratePerDayOlder);
            }
            $share = $n / $total; // les mots présents partout (« Google ») sont moins parlants

            $candidates[$key] = [
                'key'     => $key,
                'ids'     => $list,
                'sources' => $nSources,
                'growth'  => round($growth, 1),
                'score'   => $weight[$key]
                    * (1 + 0.25 * ($nSources - $this->minSources))
                    * ($isPair ? 1.3 : 1.0)
                    * (1 + 0.15 * max(0.0, min($growth, 5.0) - 1))
                    / (1 + 10 * $share),
            ];
        }

        // Un mot seul déjà couvert par une paire qui le contient (« Gemini » dans « Gemini 3 ») s'efface
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
            if (count($trends) < $this->maxTrends) {
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
            $trend['last_at'] = $published[$trend['article_ids'][0]] ?? date('Y-m-d H:i:s', $now);
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
        // Majuscule initiale, sauf si le mot a déjà sa propre casse (« iOS », « eBay »)
        $first = (string) strtok($label, ' ');
        if (preg_match('/\p{Lu}/u', mb_substr($first, 1))) {
            return $label;
        }
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
