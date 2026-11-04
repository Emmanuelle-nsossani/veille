<?php
/** @var string $content @var array $flashes @var bool $loggedIn @var string $csrf @var array $config */
$appName = $config['app_name'];
$pageTitle = isset($title) ? $title . ' · ' . $appName : $appName;
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#4c1d95">
    <title><?= e($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,800&family=DM+Sans:wght@400;500;700&display=swap">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body>
<header class="topbar">
    <a class="brand" href="<?= e(url('/')) ?>">
        <span class="brand-mark" aria-hidden="true"></span>
        <span><?= e($appName) ?></span>
    </a>
    <?php if ($loggedIn): ?>
        <nav class="nav" aria-label="Navigation principale">
            <a href="<?= e(url('/')) ?>">Fil</a>
            <a href="<?= e(url('/sources')) ?>">Sources</a>
            <form method="post" action="<?= e(url('/logout')) ?>">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <button type="submit" class="link">Quitter</button>
            </form>
        </nav>
    <?php endif; ?>
</header>

<main class="container">
    <?php foreach ($flashes as $flash): ?>
        <div class="flash flash-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
    <?php endforeach; ?>

    <?= $content ?>
</main>

<script>
    window.VEILLE = <?= json_encode(['base' => rtrim($config['base_path'], '/'), 'csrf' => $csrf], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
