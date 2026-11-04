<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Attribue des thèmes à un article selon les mots-clés de config/themes.php.
 * Recherche en mots entiers, insensible à la casse, « s » final toléré :
 * « ia » trouve « l'IA » ou « IA générative » mais pas « média ».
 */
class ThemeClassifier
{
    /** @var array<string, string> clé du thème => expression régulière */
    private array $patterns = [];

    public function __construct(array $themes)
    {
        foreach ($themes as $key => $theme) {
            $words = array_filter(array_map(
                fn (string $k) => preg_quote(mb_strtolower(trim($k)), '/'),
                $theme['keywords'] ?? []
            ));
            if (!$words) {
                continue;
            }
            // Les plus longs d'abord pour que « big data » passe avant « data »
            usort($words, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
            $this->patterns[$key] = '/(?<![\p{L}\p{N}])(?:' . implode('|', $words) . ')s?(?![\p{L}\p{N}])/iu';
        }
    }

    /** @return string[] clés des thèmes trouvés */
    public function classify(string $title, string $summary = ''): array
    {
        $text = mb_strtolower($title . ' ' . $summary);
        $text = str_replace(['’', '‘', "\u{00A0}"], ["'", "'", ' '], $text);

        $found = [];
        foreach ($this->patterns as $key => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                $found[] = $key;
            }
        }
        return $found;
    }
}
