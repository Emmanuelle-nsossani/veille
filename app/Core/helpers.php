<?php
declare(strict_types=1);

/**
 * Petites fonctions utilisées dans les vues.
 */

if (!function_exists('e')) {
    /** Échappe une valeur pour l'afficher dans du HTML. */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    /** Adresse interne au site, en tenant compte de base_path. */
    function url(string $path = '/'): string
    {
        return ($GLOBALS['veille_base_path'] ?? '') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    /** Fichier statique avec un numéro de version pour forcer le rafraîchissement du cache. */
    function asset(string $path): string
    {
        $file = ROOT . '/assets/' . ltrim($path, '/');
        $version = is_file($file) ? filemtime($file) : 0;
        return url('/assets/' . ltrim($path, '/')) . '?v=' . $version;
    }
}

if (!function_exists('feed_url')) {
    /** Lien vers le fil en gardant les filtres courants, modifiés par $changes. */
    function feed_url(array $filters, array $changes = []): string
    {
        $params = array_merge(
            ['theme' => $filters['theme'] ?? '', 'view' => $filters['view'] ?? 'all', 'q' => $filters['q'] ?? ''],
            $changes
        );
        if (($params['view'] ?? '') === 'all') {
            unset($params['view']);
        }
        $query = http_build_query(array_filter($params, fn ($v) => $v !== '' && $v !== null && $v !== 1));
        return url('/') . ($query ? '?' . $query : '');
    }
}

if (!function_exists('relative_date')) {
    /** « à l'instant », « il y a 3 h », « hier 14:05 » ou « 12 oct. ». */
    function relative_date(string $datetime): string
    {
        $ts = strtotime($datetime);
        if ($ts === false) {
            return '';
        }
        $diff = time() - $ts;
        if ($diff < 60) {
            return "à l'instant";
        }
        if ($diff < 3600) {
            return 'il y a ' . intdiv($diff, 60) . ' min';
        }
        if (date('Y-m-d', $ts) === date('Y-m-d')) {
            return 'il y a ' . intdiv($diff, 3600) . ' h';
        }
        if (date('Y-m-d', $ts) === date('Y-m-d', strtotime('-1 day'))) {
            return 'hier ' . date('H:i', $ts);
        }
        $months = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
        $label = (int) date('j', $ts) . ' ' . $months[(int) date('n', $ts) - 1];
        return date('Y', $ts) === date('Y') ? $label : $label . ' ' . date('Y', $ts);
    }
}

if (!function_exists('french_today')) {
    /** « Mercredi 4 novembre ». */
    function french_today(): string
    {
        $days = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
        $months = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
        return $days[(int) date('w')] . ' ' . (int) date('j') . ' ' . $months[(int) date('n') - 1];
    }
}

if (!function_exists('trend_url')) {
    function trend_url(string $slug): string
    {
        return url('/trend') . '?t=' . rawurlencode($slug);
    }
}

if (!function_exists('growth_badge')) {
    /** « Nouveau », « ×3 » ou rien, selon la hausse par rapport aux 14 jours précédents. */
    function growth_badge(float $growth): string
    {
        if ($growth >= 6) {
            return 'Nouveau';
        }
        if ($growth >= 2) {
            return '×' . (int) round($growth);
        }
        return '';
    }
}
