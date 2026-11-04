<?php
/**
 * Configuration générale.
 * Le mot de passe et le jeton secret ne sont PAS ici : ils sont créés
 * automatiquement à la première connexion dans storage/secrets.json.
 */
return [
    'app_name'       => 'Veille Numérique',
    'timezone'       => 'Europe/Paris',
    'db_path'        => ROOT . '/storage/veille.sqlite',
    'secrets_path'   => ROOT . '/storage/secrets.json',
    'retention_days' => 60,   // les articles non favoris plus vieux sont supprimés
    'per_page'       => 30,
    'fetch_timeout'  => 15,   // secondes par flux
    'user_agent'     => 'VeilleNumerique/1.0 (+https://emmanuellecode.fr)',
    // Laisser vide si le site est à la racine du domaine (cas normal).
    // Exemple si installé dans un sous-dossier : '/veille'
    'base_path'      => '',
];
