<?php
/** Carte d'article. @var array $a @var array $themes @var array $filters */
?>
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
