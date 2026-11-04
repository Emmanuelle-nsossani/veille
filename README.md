# Veille Numérique

Ma veille personnelle sur le numérique : IA, Data, Web & dev, Product & projet, Automatisation, Cyber, Régulation.
Site en ligne sur [emmanuellecode.fr/veille](https://emmanuellecode.fr/veille), déployé depuis ce dépôt
dans le dossier `public_html/veille` (réglage `base_path` dans `config/config.php`).

Site PHP 8.1+ en architecture MVC, sans framework ni dépendance. Base SQLite (aucune base MySQL à créer).

## Structure

```
index.php            Contrôleur frontal + routes
.htaccess            Réécriture d'URL, HTTPS, blocage des dossiers sensibles
app/Core/            Routeur, base de données, authentification, contrôleur parent, fonctions de vue
app/Models/          Article, Source (accès aux données)
app/Controllers/     Fil, connexion, sources, API JSON, cron par URL
app/Services/        Récupération RSS/Atom, classement par thème
app/Views/           Gabarits HTML
config/              Réglages, sources RSS de départ, thèmes et mots-clés
cron/fetch.php       Script de récupération quotidienne
assets/              CSS et JS
storage/             Base SQLite, secrets et sessions (créés sur le serveur, jamais versionnés)
```

## Ce qui n'est jamais dans le dépôt

`storage/veille.sqlite`, `storage/secrets.json` et `storage/sessions/` sont créés sur le serveur
et ignorés par Git (voir `.gitignore`). Seul `storage/.gitkeep` est versionné.

## Mise en ligne sur Hostinger

1. hPanel → **Avancé** → **Configuration PHP** : PHP 8.1 ou plus.
2. hPanel → **Avancé** → **Git** : dépôt `Emmanuelle-nsossani/veille`, branche `main`, dossier `veille`,
   avec le déploiement automatique activé.
3. Va sur https://emmanuellecode.fr/veille : choisis ton mot de passe.
4. Page **Sources** → **Récupérer maintenant**. Les sources en erreur sont signalées en rouge :
   désactive-les ou remplace leur adresse.
5. hPanel → **Avancé** → **Tâches Cron** : tâche quotidienne (ex. 6h30) avec la commande
   affichée sur la page Sources, de la forme :
   `/usr/bin/php /home/uXXXXXXX/domains/emmanuellecode.fr/public_html/veille/cron/fetch.php`

## Tester en local

```
php -S localhost:8000 index.php
```

Avec `base_path` vidé dans `config/config.php`, puis http://localhost:8000 (la redirection HTTPS du `.htaccess` ne s'applique pas avec ce serveur).

## Tendances du moment

À chaque récupération, le site repère les sujets qui reviennent dans au moins 3 articles de 2 sources
différentes sur les dernières 48 h (et plus souvent que sur les 14 jours précédents, une fois l'historique suffisant).
Chaque tendance a sa page avec :

- un **résumé** : rédigé par l'IA si une clé API Claude est enregistrée sur la page Sources
  (stockée dans `storage/ai.json`, jamais versionnée), sinon composé d'extraits des articles ;
- les **articles du sujet** ;
- les **articles connectés** : autres articles de la veille qui partagent ses mots-clés.

Réglages dans `app/Services/TrendDetector.php` (fenêtre, seuils) et `app/Services/Terms.php` (mots ignorés).
Modèle IA : `ai_model` dans `config/config.php`.

## Personnaliser

- Thèmes et mots-clés : `config/themes.php` (mots entiers, « s » final toléré)
- Durée de conservation des articles (60 jours, favoris jamais supprimés) : `config/config.php`
- Sources : directement depuis la page /sources du site

## Vers l'application mobile

L'API JSON est prête (jeton affiché sur la page Sources) :

- `GET /api/articles?theme=ia&view=unread&q=…&limit=50&offset=0&since=2026-11-01`
- `GET /api/themes`
- `GET /api/trends` et `GET /api/trends?t=<slug>` (résumé, articles, articles connectés)
- En-tête : `Authorization: Bearer <jeton>`

## Mot de passe oublié

Supprime `storage/secrets.json` via le gestionnaire de fichiers, puis retourne sur le site
pour en choisir un nouveau (le jeton de l'API et du cron change aussi).
