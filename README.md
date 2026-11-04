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

Le site repère les sujets d'actualité des **3 derniers mois** (`trend_days` dans `config/config.php`) :

- seuls les **sujets précis** comptent : noms propres, sigles, versions, expressions sans mot courant
  (« Gemini 3 », « AI Act », « NIS2 », « iOS 27 ») ; les mots génériques (« sécurité », « modèle »)
  et les expressions banales (« Google modifie ») sont ignorés ;
- un sujet doit apparaître dans au moins **4 articles** venant d'au moins **3 sources** ;
- les articles récents pèsent plus lourd, et le badge « En hausse » signale un sujet qui accélère
  sur les 14 derniers jours (une fois 30 jours d'historique disponibles).

Chaque tendance a sa page : un **résumé** (rédigé par l'IA si une clé API Claude est enregistrée
sur la page Sources, sinon des extraits), les **articles du sujet** et les **articles connectés**.

### Historique

Un flux RSS ne donne que ses derniers articles. Le bouton **Récupérer l'historique (3 mois)** de la
page Sources va chercher les pages précédentes des flux qui le permettent (`?paged=N`, WordPress
notamment), jusqu'à 3 mois. En ligne de commande : `php cron/fetch.php --historique`.
Les articles sont conservés 120 jours (`retention_days`).

## Personnaliser

- Thèmes et mots-clés : `config/themes.php` (mots entiers, « s » final toléré)
- Durée de conservation des articles (120 jours, favoris jamais supprimés) : `config/config.php`
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
