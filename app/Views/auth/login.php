<?php /** @var bool $firstRun @var string $csrf */ ?>
<section class="login">
    <p class="eyebrow"><?= $firstRun ? 'Bienvenue' : 'Bon retour' ?></p>
    <h1><?= $firstRun ? 'Choisis ton mot de passe' : 'Connexion' ?></h1>
    <p class="muted">
        <?= $firstRun
            ? 'Première visite : ce mot de passe protégera ta veille. Au moins 8 caractères.'
            : 'Ta veille du jour t’attend.' ?>
    </p>

    <form class="stack" method="post" action="<?= e(url('/login')) ?>">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <label>
            Mot de passe
            <input type="password" name="password" required minlength="<?= $firstRun ? 8 : 1 ?>"
                   autocomplete="<?= $firstRun ? 'new-password' : 'current-password' ?>" autofocus>
        </label>
        <?php if ($firstRun): ?>
            <label>
                Confirme le mot de passe
                <input type="password" name="password_confirm" required minlength="8" autocomplete="new-password">
            </label>
        <?php endif; ?>
        <button type="submit"><?= $firstRun ? 'Créer et entrer' : 'Entrer' ?></button>
    </form>
</section>
