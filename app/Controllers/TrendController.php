<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Article;
use App\Models\Trend;

/**
 * Page d'une tendance : résumé, articles du sujet, puis articles connectés.
 */
class TrendController extends Controller
{
    public function show(): void
    {
        $this->requireLogin();
        $trend = (new Trend($this->db))->find((string) ($_GET['t'] ?? ''));
        if ($trend === null) {
            $this->flash('warning', 'Cette tendance n’est plus d’actualité, voici les sujets du moment.');
            $this->redirect('/');
        }

        $articles = new Article($this->db);
        $this->render('trends/show', [
            'title'    => $trend['label'],
            'trend'    => $trend,
            'articles' => $articles->byIds($trend['article_ids']),
            'related'  => $articles->byIds($trend['related_ids']),
            'others'   => array_filter((new Trend($this->db))->all(), fn ($t) => $t['slug'] !== $trend['slug']),
            'filters'  => ['theme' => '', 'view' => 'all', 'q' => ''],
        ]);
    }
}
