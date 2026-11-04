<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Article;
use App\Models\Source;
use App\Services\FeedFetcher;
use App\Services\ThemeClassifier;
use App\Services\TrendRefresher;

/**
 * Déclencheur par URL, si tu préfères un service de cron externe
 * au cron de Hostinger : GET /cron?token=<jeton>
 */
class CronController extends Controller
{
    public function run(): void
    {
        if (!Auth::validToken($this->config, $_GET['token'] ?? null)) {
            $this->json(['error' => 'Non autorisé'], 401);
            return;
        }
        @set_time_limit(300);
        $fetcher = new FeedFetcher($this->config, new Source($this->db), new Article($this->db), new ThemeClassifier($this->themes));
        $report = $fetcher->run();
        $report['trends'] = (new TrendRefresher($this->db, $this->config))->run();
        $this->json($report);
    }
}
