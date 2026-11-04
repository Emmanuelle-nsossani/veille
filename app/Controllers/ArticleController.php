<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Article;

class ArticleController extends Controller
{
    public function index(): void
    {
        $this->requireLogin();
        $model = new Article($this->db);

        $filters = $this->filtersFromQuery();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = (int) $this->config['per_page'];

        $total = $model->count($filters);
        $this->render('articles/index', [
            'articles'    => $model->search($filters, $perPage, ($page - 1) * $perPage),
            'filters'     => $filters,
            'page'        => $page,
            'pages'       => max(1, (int) ceil($total / $perPage)),
            'total'       => $total,
            'stats'       => $model->stats(),
            'themeCounts' => $model->countByTheme(array_keys($this->themes)),
        ]);
    }

    public function toggleFavorite(): void { $this->toggle('is_favorite'); }
    public function toggleRead(): void     { $this->toggle('is_read'); }

    public function markAllRead(): void
    {
        $this->requireLogin();
        $this->requireCsrf();
        $count = (new Article($this->db))->markAllRead($this->filtersFromQuery($_POST));
        $this->flash('success', "$count article(s) marqué(s) comme lu(s).");
        $query = http_build_query(array_filter([
            'theme' => $_POST['theme'] ?? '', 'q' => $_POST['q'] ?? '', 'view' => $_POST['view'] ?? '',
        ]));
        $this->redirect('/' . ($query ? '?' . $query : ''));
    }

    private function toggle(string $field): void
    {
        $this->requireLogin();
        $this->requireCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        $value = (new Article($this->db))->toggle($id, $field);
        if ($value === null) {
            $this->json(['error' => 'Article introuvable'], 404);
            return;
        }
        $this->json(['id' => $id, 'value' => $value]);
    }

    private function filtersFromQuery(?array $source = null): array
    {
        $source ??= $_GET;
        $theme = (string) ($source['theme'] ?? '');
        $view = (string) ($source['view'] ?? 'all');
        return [
            'theme' => array_key_exists($theme, $this->themes) ? $theme : '',
            'q'     => trim(mb_substr((string) ($source['q'] ?? ''), 0, 100)),
            'view'  => in_array($view, ['all', 'unread', 'favorites', 'today'], true) ? $view : 'all',
        ];
    }
}
