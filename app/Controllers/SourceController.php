<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Article;
use App\Models\Source;
use App\Services\FeedFetcher;
use App\Services\ThemeClassifier;

class SourceController extends Controller
{
    public function index(): void
    {
        $this->requireLogin();
        $host = $_SERVER['HTTP_HOST'] ?? 'emmanuellecode.fr';
        $this->render('sources/index', [
            'sources' => (new Source($this->db))->all(),
            'token'   => Auth::secrets($this->config)['token'] ?? '',
            'siteUrl' => 'https://' . $host . rtrim($this->config['base_path'], '/'),
            'rootDir' => ROOT,
        ]);
    }

    public function add(): void
    {
        $this->requireLogin();
        $this->requireCsrf();
        $name = trim((string) ($_POST['name'] ?? ''));
        $url = trim((string) ($_POST['url'] ?? ''));

        if ($name === '' || !filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
            $this->flash('error', 'Indique un nom et une adresse de flux valide (https://…).');
        } elseif ((new Source($this->db))->add($name, $url)) {
            $this->flash('success', "Source « $name » ajoutée. Elle sera lue à la prochaine récupération.");
        } else {
            $this->flash('error', 'Cette adresse est déjà dans tes sources.');
        }
        $this->redirect('/sources');
    }

    public function toggle(): void
    {
        $this->requireLogin();
        $this->requireCsrf();
        (new Source($this->db))->toggle((int) ($_POST['id'] ?? 0));
        $this->redirect('/sources');
    }

    public function delete(): void
    {
        $this->requireLogin();
        $this->requireCsrf();
        (new Source($this->db))->delete((int) ($_POST['id'] ?? 0));
        $this->flash('success', 'Source supprimée, avec ses articles.');
        $this->redirect('/sources');
    }

    /** Récupération manuelle depuis l'interface. */
    public function refresh(): void
    {
        $this->requireLogin();
        $this->requireCsrf();
        @set_time_limit(300);
        $fetcher = new FeedFetcher($this->config, new Source($this->db), new Article($this->db), new ThemeClassifier($this->themes));
        $report = $fetcher->run();
        $this->flash(
            $report['errors'] ? 'warning' : 'success',
            "{$report['new']} nouvel(s) article(s) depuis {$report['sources']} source(s)"
            . ($report['errors'] ? " — {$report['errors']} source(s) en erreur, voir le détail ci-dessous." : '.')
        );
        $this->redirect('/sources');
    }
}
