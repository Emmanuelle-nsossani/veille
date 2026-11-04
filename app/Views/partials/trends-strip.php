<?php
/** Bandeau « Tendances du moment » en haut du fil. @var array $trends */
?>
<section class="trends" aria-labelledby="trends-title">
    <div class="trends-head">
        <h2 id="trends-title"><span class="flame" aria-hidden="true"></span>Tendances du moment</h2>
        <span class="muted">sur 48 h</span>
    </div>
    <ol class="trend-list">
        <?php foreach ($trends as $i => $t): ?>
            <li>
                <a class="trend-card" href="<?= e(trend_url($t['slug'])) ?>" style="--i: <?= $i ?>">
                    <span class="trend-rank">#<?= $i + 1 ?></span>
                    <span class="trend-label"><?= e($t['label']) ?></span>
                    <span class="trend-meta">
                        <?= $t['articles'] ?> articles · <?= $t['sources'] ?> sources
                        <?php if ($badge = growth_badge($t['growth'])): ?><span class="trend-badge"><?= e($badge) ?></span><?php endif; ?>
                    </span>
                    <span class="trend-snippet"><?= e(strtok($t['summary'], "\n") ?: '') ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ol>
</section>
