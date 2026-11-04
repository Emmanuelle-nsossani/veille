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
    // Clé API Claude pour les résumés de tendances (saisie sur la page Sources, jamais versionnée)
    'ai_path'        => ROOT . '/storage/ai.json',
    'ai_model'       => 'claude-haiku-5-5',
    'retention_days' => 60,   // les articles non favoris plus vieux sont supprimés
    'per_page'       => 30,
    'fetch_timeout'  => 15,   // secondes par flux
    'user_agent'     => 'VeilleNumerique/1.0 (+https://emmanuellecode.fr)',
    // Sous-dossier où le site est installé : emmanuellecode.fr/veille
    // Laisser vide si le site est un jour à la racine du domaine.
    'base_path'      => '/veille',
];
