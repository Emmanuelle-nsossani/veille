<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Article;
use App\Models\Source;
use App\Services\FeedFetcher;
use App\Services\Summarizer;
use App\Services\ThemeClassifier;
use App\Services\TrendRefresher;

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
            'aiKey'   => (new Summarizer($this->config))->apiKey(),
            'aiError' => (new Summarizer($this->config))->lastError(),
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

    /** Enregistre ou retire la clé API Claude utilisée pour les résumés de tendances. */
    public function saveAi(): void
    {
        $this->requireLogin();
        $this->requireCsrf();
        $summarizer = new Summarizer($this->config);

        if (($_POST['action'] ?? '') === 'delete') {
            $summarizer->saveApiKey(null);
            $this->flash('success', 'Clé retirée : les résumés seront faits à partir d’extraits.');
            $this->redirect('/sources');
        }

        $key = trim((string) ($_POST['api_key'] ?? ''));
        if (!preg_match('/^sk-ant-[A-Za-z0-9_-]{20,}$/', $key)) {
            $this->flash('error', 'Cette clé ne ressemble pas à une clé API Claude (elle commence par sk-ant-).');
            $this->redirect('/sources');
        }
        $summarizer->saveApiKey($key);
        $this->db->exec("UPDATE trends SET summary = '' WHERE summary_source = 'extraits'");
        $this->flash('success', 'Clé enregistrée. Les résumés IA seront rédigés à la prochaine récupération.');
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
        $report['trends'] = (new TrendRefresher($this->db, $this->config))->run();
        $this->flash(
            $report['errors'] ? 'warning' : 'success',
            "{$report['new']} nouvel(s) article(s) depuis {$report['sources']} source(s), {$report['trends']} tendance(s)"
            . ($report['errors'] ? " — {$report['errors']} source(s) en erreur, voir le détail ci-dessous." : '.')
        );
        $this->redirect('/sources');
    }
}
