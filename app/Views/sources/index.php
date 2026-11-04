<?php
/** @var array $sources @var string $token @var string $siteUrl @var string $rootDir @var string $csrf */
$errors = count(array_filter($sources, fn ($s) => $s['active'] && $s['last_status'] === 'error'));
$this->pageTitle = 'Sources';
?>
<section class="page-head">
    <div>
        <p class="eyebrow"><?= count($sources) ?> source<?= count($sources) > 1 ? 's' : '' ?><?= $errors ? " · $errors en erreur" : '' ?></p>
        <h1>Sources</h1>
    </div>
    <div class="head-actions">
        <form class="js-refresh" method="post" action="<?= e(url('/refresh')) ?>">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <button type="submit">Récupérer maintenant</button>
        </form>
        <form class="js-refresh" method="post" action="<?= e(url('/sources/backfill')) ?>"
              title="Va chercher les articles plus anciens des flux qui le permettent, sur <?= e(period_label((int) $config['trend_days'])) ?>">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <button type="submit" class="ghost">Récupérer l’historique (<?= e(period_label((int) $config['trend_days'])) ?>)</button>
        </form>
    </div>
</section>

<?php if ($sources): ?>
    <ul class="sources">
        <?php foreach ($sources as $s): ?>
            <?php $dot = $s['last_status'] === 'ok' ? 'dot-ok' : ($s['last_status'] === 'error' ? 'dot-error' : ''); ?>
            <li class="source-row<?= $s['active'] ? '' : ' is-off' ?>">
                <div class="source-main">
                    <span class="dot <?= $dot ?>" title="<?= $s['last_status'] === 'ok' ? 'OK' : ($s['last_status'] === 'error' ? 'Erreur' : 'Jamais récupérée') ?>"></span>
                    <div>
                        <strong><?= e($s['name']) ?></strong>
                        <small class="muted url"><?= e($s['url']) ?></small>
                        <?php if ($s['last_status'] === 'error'): ?>
                            <small class="err"><?= e($s['last_error']) ?></small>
                        <?php elseif ($s['last_fetched_at']): ?>
                            <small class="muted"><?= (int) $s['article_count'] ?> article(s) · dernière lecture <?= e(relative_date($s['last_fetched_at'])) ?> (+<?= (int) $s['last_new'] ?>)</small>
                        <?php else: ?>
                            <small class="muted">Pas encore récupérée</small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="source-actions">
                    <form method="post" action="<?= e(url('/sources/toggle')) ?>">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                        <button type="submit" class="ghost small"><?= $s['active'] ? 'Désactiver' : 'Activer' ?></button>
                    </form>
                    <form method="post" action="<?= e(url('/sources/delete')) ?>"
                          onsubmit="return confirm('Supprimer « <?= e(addslashes($s['name'])) ?> » et ses articles (favoris compris) ?')">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                        <button type="submit" class="ghost small danger">Supprimer</button>
                    </form>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php else: ?>
    <section class="empty">
        <h2>Aucune source</h2>
        <p class="muted">Ajoute ton premier flux RSS ci-dessous.</p>
    </section>
<?php endif; ?>

<section class="panel">
    <h2>Ajouter une source</h2>
    <p class="muted">L'adresse du flux RSS ou Atom (souvent /feed, /rss ou /rss.xml).</p>
    <form class="stack inline" method="post" action="<?= e(url('/sources/add')) ?>">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <label>Nom <input name="name" required maxlength="100" placeholder="Ex. Frandroid"></label>
        <label>Adresse du flux <input type="url" name="url" required placeholder="https://…/feed/"></label>
        <button type="submit">Ajouter</button>
    </form>
</section>

<section class="panel">
    <h2>Résumés des tendances</h2>
    <?php if ($aiKey !== ''): ?>
        <p>Résumés rédigés par l’IA <strong>activés</strong> (clé …<?= e(substr($aiKey, -4)) ?>, modèle <?= e($config['ai_model']) ?>).</p>
        <?php if ($aiError): ?>
            <p class="err">Dernier appel en échec (<?= e(relative_date($aiError['at'])) ?>) : <?= e($aiError['message']) ?>.
                Les résumés sont faits à partir d’extraits en attendant.</p>
        <?php endif; ?>
        <form method="post" action="<?= e(url('/sources/ai')) ?>">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="delete">
            <button type="submit" class="ghost small danger">Retirer la clé</button>
        </form>
    <?php else: ?>
        <p class="muted">Sans clé, chaque tendance est résumée par des extraits de ses articles.
            Avec une clé API Claude (console.anthropic.com), un vrai résumé est rédigé à chaque récupération.
            La clé reste sur ton serveur, dans storage/.</p>
        <form class="stack inline ai-form" method="post" action="<?= e(url('/sources/ai')) ?>">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <label>Clé API Claude <input type="password" name="api_key" required autocomplete="off" placeholder="sk-ant-…"></label>
            <button type="submit">Enregistrer</button>
        </form>
    <?php endif; ?>
</section>

<section class="panel">
    <h2>Automatisation</h2>

    <h3>Tâche cron Hostinger (quotidienne)</h3>
    <p class="muted">hPanel → Avancé → Tâches Cron, commande :</p>
    <pre>/usr/bin/php <?= e($rootDir) ?>/cron/fetch.php</pre>

    <h3>Ou par URL (service de cron externe)</h3>
    <pre><?= e($siteUrl) ?>/cron?token=<?= e($token) ?></pre>

    <h3>API JSON (future application mobile)</h3>
    <pre>GET <?= e($siteUrl) ?>/api/articles?view=unread&amp;limit=50
GET <?= e($siteUrl) ?>/api/themes
Authorization: Bearer <?= e($token) ?></pre>
    <p class="muted">Garde ce jeton pour toi : il donne accès à ta veille sans mot de passe.</p>
</section>
