<?php
/**
 * @var array $articles @var array $filters @var int $page @var int $pages @var int $total
 * @var array $stats @var array $themeCounts @var array $themes @var string $csrf
 */
$views = ['all' => 'Tous', 'unread' => 'Non lus', 'favorites' => 'Favoris', 'today' => "Aujourd'hui"];
$activeTheme = $filters['theme'] !== '' ? $themes[$filters['theme']] : null;
?>
<section class="hero">
    <div>
        <p class="eyebrow"><?= e(french_today()) ?></p>
        <h1><?= $activeTheme ? e($activeTheme['label']) : 'Ta veille du jour' ?></h1>
    </div>
    <dl class="stats">
        <div class="stat"><dt>Non lus</dt><dd><?= $stats['unread'] ?></dd></div>
        <div class="stat"><dt>Favoris</dt><dd><?= $stats['favorites'] ?></dd></div>
        <div class="stat"><dt>Aujourd'hui</dt><dd><?= $stats['today'] ?></dd></div>
    </dl>
</section>

<form class="search" method="get" action="<?= e(url('/')) ?>" role="search">
    <?php if ($filters['theme'] !== ''): ?><input type="hidden" name="theme" value="<?= e($filters['theme']) ?>"><?php endif; ?>
    <?php if ($filters['view'] !== 'all'): ?><input type="hidden" name="view" value="<?= e($filters['view']) ?>"><?php endif; ?>
    <label class="sr" for="q">Rechercher</label>
    <input type="search" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Rechercher un mot, une marque, un sujet…">
    <button type="submit">Chercher</button>
</form>

<nav class="chips" aria-label="Thèmes">
    <a class="chip<?= $filters['theme'] === '' ? ' is-active' : '' ?>" href="<?= e(feed_url($filters, ['theme' => ''])) ?>">
        Tout <span class="chip-count"><?= $stats['unread'] ?></span>
    </a>
    <?php foreach ($themes as $key => $theme): ?>
        <a class="chip t-<?= e($key) ?><?= $filters['theme'] === $key ? ' is-active' : '' ?>"
           style="--c: <?= e($theme['color']) ?>"
           href="<?= e(feed_url($filters, ['theme' => $key])) ?>">
            <?= e($theme['label']) ?>
            <?php if (!empty($themeCounts[$key])): ?><span class="chip-count"><?= $themeCounts[$key] ?></span><?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>

<div class="toolbar">
    <nav class="tabs" aria-label="Affichage">
        <?php foreach ($views as $key => $label): ?>
            <a href="<?= e(feed_url($filters, ['view' => $key])) ?>"<?= $filters['view'] === $key ? ' class="is-active" aria-current="page"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <?php if ($total > 0): ?>
        <form method="post" action="<?= e(url('/articles/read-all')) ?>">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="theme" value="<?= e($filters['theme']) ?>">
            <input type="hidden" name="q" value="<?= e($filters['q']) ?>">
            <input type="hidden" name="view" value="<?= e($filters['view']) ?>">
            <button type="submit" class="ghost small">Tout marquer comme lu</button>
        </form>
    <?php endif; ?>
</div>

<?php if ($articles): ?>
    <p class="count"><?= $total ?> article<?= $total > 1 ? 's' : '' ?><?= $filters['q'] !== '' ? ' pour « ' . e($filters['q']) . ' »' : '' ?></p>

    <ul class="feed">
        <?php foreach ($articles as $a): ?>
            <li class="card<?= $a['is_read'] ? ' is-read' : '' ?>" data-id="<?= $a['id'] ?>">
                <div class="card-meta">
                    <span class="source"><?= e($a['source_name']) ?></span>
                    <span aria-hidden="true">·</span>
                    <time datetime="<?= e(date('c', strtotime($a['published_at']))) ?>"><?= e(relative_date($a['published_at'])) ?></time>
                </div>
                <h3><a class="js-open" href="<?= e($a['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($a['title']) ?></a></h3>
                <?php if ($a['summary'] !== ''): ?>
                    <p class="summary"><?= e($a['summary']) ?></p>
                <?php endif; ?>
                <div class="card-foot">
                    <div class="tags">
                        <?php foreach ($a['themes'] as $key): ?>
                            <?php if (isset($themes[$key])): ?>
                                <a class="tag" style="--c: <?= e($themes[$key]['color']) ?>" href="<?= e(feed_url($filters, ['theme' => $key])) ?>"><?= e($themes[$key]['label']) ?></a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <div class="actions">
                        <button type="button" class="icon js-read" aria-pressed="<?= $a['is_read'] ? 'true' : 'false' ?>" title="Lu / non lu">
                            <span class="sr">Marquer comme lu</span>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                        </button>
                        <button type="button" class="icon js-fav" aria-pressed="<?= $a['is_favorite'] ? 'true' : 'false' ?>" title="Favori">
                            <span class="sr">Ajouter aux favoris</span>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2L12 17.3l-5.6 2.9 1.1-6.2L3 9.6l6.2-.9z"/></svg>
                        </button>
                    </div>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($pages > 1): ?>
        <nav class="pager" aria-label="Pages">
            <span><?php if ($page > 1): ?><a href="<?= e(feed_url($filters, ['page' => $page - 1])) ?>">← Plus récents</a><?php endif; ?></span>
            <span>Page <?= $page ?> / <?= $pages ?></span>
            <span><?php if ($page < $pages): ?><a href="<?= e(feed_url($filters, ['page' => $page + 1])) ?>">Plus anciens →</a><?php endif; ?></span>
        </nav>
    <?php endif; ?>
<?php elseif ($stats['total'] === 0): ?>
    <section class="empty">
        <h2>Ton fil est encore vide</h2>
        <p class="muted">Lance une première récupération depuis la page Sources.</p>
        <p><a href="<?= e(url('/sources')) ?>">Aller aux sources →</a></p>
    </section>
<?php else: ?>
    <section class="empty">
        <h2>Rien par ici</h2>
        <p class="muted">Aucun article ne correspond à ces filtres.</p>
        <p><a href="<?= e(url('/')) ?>">Voir tout le fil</a></p>
    </section>
<?php endif; ?>
