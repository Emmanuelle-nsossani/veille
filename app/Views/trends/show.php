<?php
/**
 * @var array $trend @var array $articles @var array $related @var array $others
 * @var array $themes @var array $filters
 */
$lines = array_filter(array_map('trim', explode("\n", $trend['summary'])));
?>
<p class="back"><a href="<?= e(url('/')) ?>">← Retour au fil</a></p>

<section class="hero">
    <div>
        <p class="eyebrow"><span class="flame" aria-hidden="true"></span>Tendance du moment</p>
        <h1><?= e($trend['label']) ?></h1>
        <p class="muted trend-facts">
            <?= $trend['articles'] ?> articles · <?= $trend['sources'] ?> sources en 48 h
            <?php if ($badge = growth_badge($trend['growth'])): ?><span class="trend-badge"><?= e($badge) ?></span><?php endif; ?>
        </p>
    </div>
</section>

<section class="summary-box">
    <div class="summary-head">
        <h2>En bref</h2>
        <span class="pill"><?= $trend['summary_source'] === 'ia' ? 'Résumé rédigé par l’IA' : 'Résumé automatique · extraits' ?></span>
    </div>
    <?php if ($trend['summary_source'] === 'ia'): ?>
        <?php foreach ($lines as $line): ?><p><?= e($line) ?></p><?php endforeach; ?>
    <?php else: ?>
        <ul class="quotes">
            <?php foreach ($lines as $line): ?>
                <?php [$source, $sentence] = array_pad(explode(' — ', $line, 2), 2, ''); ?>
                <li><strong><?= e($source) ?></strong> <?= e($sentence) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php if (count($trend['terms']) > 1): ?>
        <p class="muted small">Mots repérés : <?= e(implode(', ', $trend['terms'])) ?></p>
    <?php endif; ?>
</section>

<h2 class="section-title">Les articles du sujet</h2>
<ul class="feed">
    <?php foreach ($articles as $a): ?>
        <?php require ROOT . '/app/Views/partials/article-card.php'; ?>
    <?php endforeach; ?>
</ul>

<h2 class="section-title">Articles connectés</h2>
<?php if ($related): ?>
    <p class="muted">D’autres articles de ta veille qui parlent du même sujet ou d’un sujet proche.</p>
    <ul class="feed">
        <?php foreach ($related as $a): ?>
            <?php require ROOT . '/app/Views/partials/article-card.php'; ?>
        <?php endforeach; ?>
    </ul>
<?php else: ?>
    <p class="muted">Pas encore d’autre article proche dans ta veille. Il en apparaîtra au fil des récupérations.</p>
<?php endif; ?>

<?php if ($others): ?>
    <h2 class="section-title">Autres tendances</h2>
    <nav class="chips">
        <?php foreach ($others as $o): ?>
            <a class="chip" href="<?= e(trend_url($o['slug'])) ?>"><?= e($o['label']) ?> <span class="chip-count"><?= $o['articles'] ?></span></a>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>
