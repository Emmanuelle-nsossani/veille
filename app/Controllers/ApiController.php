<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Article;
use App\Models\Trend;

/**
 * API JSON en lecture, prévue pour la future application mobile.
 * Authentification : en-tête "Authorization: Bearer <jeton>" ou ?token=<jeton>.
 *
 * GET /api/articles?theme=ia&q=mistral&view=unread&limit=50&offset=0
 * GET /api/themes
 * GET /api/trends  (et ?t=<slug>)
 */
class ApiController extends Controller
{
    public function articles(): void
    {
        if (!$this->authorized()) {
            return;
        }
        $theme = (string) ($_GET['theme'] ?? '');
        $view = (string) ($_GET['view'] ?? 'all');
        $filters = [
            'theme' => array_key_exists($theme, $this->themes) ? $theme : '',
            'q'     => trim(mb_substr((string) ($_GET['q'] ?? ''), 0, 100)),
            'view'  => in_array($view, ['all', 'unread', 'favorites', 'today'], true) ? $view : 'all',
            'since' => isset($_GET['since']) ? date('Y-m-d H:i:s', strtotime((string) $_GET['since']) ?: 0) : '',
        ];
        $limit = min(200, max(1, (int) ($_GET['limit'] ?? 50)));
        $offset = max(0, (int) ($_GET['offset'] ?? 0));

        $model = new Article($this->db);
        $this->json([
            'total'    => $model->count($filters),
            'limit'    => $limit,
            'offset'   => $offset,
            'articles' => $model->search($filters, $limit, $offset),
        ]);
    }

    public function themes(): void
    {
        if (!$this->authorized()) {
            return;
        }
        $out = [];
        foreach ($this->themes as $key => $t) {
            $out[] = ['key' => $key, 'label' => $t['label'], 'color' => $t['color']];
        }
        $this->json(['themes' => $out]);
    }

    /**
     * GET /api/trends            → liste des tendances (sans les articles)
     * GET /api/trends?t=<slug>   → une tendance avec ses articles et les articles connectés
     */
    public function trends(): void
    {
        if (!$this->authorized()) {
            return;
        }
        $model = new Trend($this->db);
        $public = fn (array $t) => [
            'slug'           => $t['slug'],
            'label'          => $t['label'],
            'articles_count' => $t['articles'],
            'sources'        => $t['sources'],
            'growth'         => $t['growth'],
            'summary'        => $t['summary'],
            'summary_source' => $t['summary_source'],
            'updated_at'     => $t['updated_at'],
        ];

        if (isset($_GET['t'])) {
            $trend = $model->find((string) $_GET['t']);
            if ($trend === null) {
                $this->json(['error' => 'Tendance introuvable'], 404);
                return;
            }
            $articles = new Article($this->db);
            $this->json($public($trend) + [
                'articles' => $articles->byIds($trend['article_ids']),
                'related'  => $articles->byIds($trend['related_ids']),
            ]);
            return;
        }

        $this->json(['trends' => array_map($public, $model->all())]);
    }

    private function authorized(): bool
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        $token = str_starts_with($header, 'Bearer ') ? substr($header, 7) : ($_GET['token'] ?? null);
        if (Auth::check() || Auth::validToken($this->config, $token)) {
            return true;
        }
        $this->json(['error' => 'Non autorisé'], 401);
        return false;
    }
}
