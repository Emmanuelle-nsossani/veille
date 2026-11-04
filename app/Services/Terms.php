<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Découpe un texte en termes significatifs (mots seuls et paires de mots)
 * pour repérer les sujets qui reviennent d'un article à l'autre.
 * Clé normalisée (minuscules, sans accents) => forme affichable.
 */
final class Terms
{
    /** Mots vides FR/EN et mots trop génériques pour faire une tendance. */
    private const STOP = [
        // français
        'les', 'des', 'une', 'aux', 'est', 'sont', 'ont', 'ete', 'etre', 'avoir', 'fait', 'faire', 'font', 'peut', 'peuvent',
        'veut', 'veulent', 'doit', 'faut', 'sera', 'seront', 'serait', 'avait', 'etait', 'cette', 'ces', 'cet', 'son', 'ses',
        'leur', 'leurs', 'notre', 'nos', 'votre', 'vos', 'mon', 'mes', 'ton', 'tes', 'qui', 'que', 'quoi', 'dont', 'elle',
        'elles', 'ils', 'nous', 'vous', 'pas', 'par', 'pour', 'dans', 'avec', 'sans', 'sur', 'sous', 'chez', 'entre', 'contre',
        'vers', 'depuis', 'pendant', 'selon', 'comme', 'mais', 'donc', 'car', 'quand', 'alors', 'ainsi', 'aussi', 'encore',
        'deja', 'enfin', 'apres', 'avant', 'tout', 'tous', 'toute', 'toutes', 'tres', 'bien', 'plus', 'moins', 'trop', 'peu',
        'beaucoup', 'autre', 'autres', 'meme', 'memes', 'chaque', 'quel', 'quelle', 'quels', 'quelles', 'comment', 'pourquoi',
        'voici', 'voila', 'oui', 'non', 'ici', 'lors', 'via', 'pres', 'moitie', 'premier', 'premiere', 'dernier', 'derniere',
        'nouveau', 'nouvelle', 'nouveaux', 'nouvelles', 'grand', 'grande', 'grands', 'grandes', 'petit', 'petite', 'gros',
        'bon', 'bonne', 'meilleur', 'meilleure', 'meilleurs', 'vrai', 'vraiment', 'annonce', 'annonces', 'lance', 'lancement',
        'arrive', 'devient', 'passe', 'pourrait', 'pourraient', 'permet', 'propose', 'presente', 'devoile', 'officiel',
        'officiellement', 'enfin', 'jour', 'jours', 'semaine', 'semaines', 'mois', 'annee', 'annees', 'ans', 'heure', 'heures',
        'fois', 'million', 'millions', 'milliard', 'milliards', 'euros', 'dollars', 'prix', 'offre', 'offres', 'bon', 'plan',
        'france', 'francais', 'francaise', 'monde', 'pays', 'entreprise', 'entreprises', 'societe', 'groupe', 'utilisateurs',
        'tech', 'technologie', 'technologies', 'numerique', 'digital', 'internet', 'ligne', 'site', 'sites', 'service',
        'services', 'outil', 'outils', 'version', 'mise', 'jour', 'sujet', 'question', 'article', 'articles', 'video',
        'intelligence', 'artificielle', 'donnees', 'test', 'avis', 'guide', 'astuce', 'astuces', 'tuto', 'tutoriel',
        // anglais
        'the', 'and', 'for', 'with', 'from', 'this', 'that', 'these', 'those', 'your', 'you', 'are', 'was', 'were', 'been',
        'being', 'how', 'why', 'what', 'when', 'where', 'who', 'which', 'new', 'now', 'can', 'could', 'will', 'would', 'should',
        'its', 'has', 'have', 'had', 'not', 'about', 'into', 'over', 'after', 'before', 'more', 'most', 'just', 'out', 'our',
        'all', 'get', 'gets', 'got', 'use', 'using', 'used', 'show', 'ask', 'tell', 'hn', 'here', 'there', 'than', 'then',
        'they', 'them', 'their', 'his', 'her', 'she', 'him', 'one', 'two', 'first', 'last', 'year', 'years', 'day', 'days',
        'week', 'month', 'way', 'make', 'makes', 'made', 'like', 'some', 'any', 'only', 'also', 'still', 'really', 'every',
        'big', 'best', 'top', 'good', 'better', 'free', 'why', 'says', 'said', 'say', 'want', 'wants', 'need', 'needs',
        'people', 'company', 'companies', 'world', 'update', 'review', 'launches', 'launch', 'announces', 'announced',
        'artificial', 'tech', 'thing', 'things', 'time', 'via', 'vs', 'versus', 'without', 'while', 'through', 'under',
    ];

    /** Trop génériques seuls, mais utiles dans une expression (« AI Act », « Data Act »). */
    private const PAIR_ONLY = ['ia', 'ai', 'data'];

    /** Sigles courts gardés malgré leur longueur. */
    private const SHORT_KEEP = ['ue', 'eu', '5g', '6g', 'x', 'ux', 'ui', 'vr', 'ar', 'os', 'pc', 'ia'];

    private static ?array $stop = null;

    /**
     * $specific (facultatif) reçoit, pour chaque clé, true si le terme ressemble à un sujet précis :
     * nom propre (« Mistral »), sigle (« RGPD »), version (« Gemini 3 », « iOS 26 »), ou expression
     * sans mot courant (« AI Act », « Google Chrome », mais pas « Google modifie »).
     *
     * @return array<string, string> clé normalisée => forme affichable (unique par texte)
     */
    public static function extract(string $text, bool $withPairs = true, ?array &$specific = null): array
    {
        $specific = [];
        $text = str_replace(['’', '‘', "'", '«', '»', '“', '”', "\u{00A0}"], ' ', $text);
        $raw = preg_split('/[^\p{L}\p{N}.+#-]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        // [clé, affichage, seulement en expression, casse] ou null pour une coupure.
        // Casse : 'upper' (nom propre, sigle, chiffres), 'lower' (mot courant), 'unknown' (1er mot du titre)
        $tokens = [];
        foreach ($raw as $position => $word) {
            $word = trim($word, '.-+#');
            $key = self::normalize($word);
            if (preg_match('/^\d{1,2}(\.\d{1,2})?$/', $key)) {
                // « 3 » dans « Gemini 3 » : jamais seul, seulement après un mot
                $tokens[] = [$key, $word, true, 'upper'];
                continue;
            }
            if (in_array($key, self::PAIR_ONLY, true)) {
                $tokens[] = [$key, $word, true, self::caseOf($word, $position)];
                continue;
            }
            if (!self::isMeaningful($key)) {
                $tokens[] = null;
                continue;
            }
            $tokens[] = [$key, $word, false, self::caseOf($word, $position)];
        }

        $terms = [];
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            if ($tokens[$i] === null) {
                continue;
            }
            [$key, $display, $pairOnly, $case] = $tokens[$i];
            if (!$pairOnly) {
                $terms[$key] ??= $display;
                $specific[$key] = ($specific[$key] ?? false) || $case === 'upper';
            }
            if ($withPairs && $i + 1 < $count && $tokens[$i + 1] !== null) {
                [$key2, $display2, $pairOnly2, $case2] = $tokens[$i + 1];
                if ($pairOnly && $pairOnly2) {
                    continue; // « IA 3 » n'a pas de sens
                }
                $pair = $key . ' ' . $key2;
                $terms[$pair] ??= $display . ' ' . $display2;
                $precise = $case !== 'lower' && $case2 !== 'lower' && ($case === 'upper' || $case2 === 'upper');
                $specific[$pair] = ($specific[$pair] ?? false) || $precise;
            }
        }
        return $terms;
    }

    /** 'upper' : nom propre hors début de titre, sigle ou mot avec chiffres ; 'lower' : mot courant ; 'unknown' : 1er mot. */
    private static function caseOf(string $word, int $position): string
    {
        if (preg_match('/\d|[+#]/', $word)) {
            return 'upper';
        }
        if (mb_strlen($word) >= 2 && $word === mb_strtoupper($word) && preg_match('/\p{Lu}/u', $word)) {
            return 'upper'; // RGPD, CNIL, AI
        }
        if ($position === 0) {
            return preg_match('/^\p{Lu}\p{Ll}*$/u', $word) ? 'unknown' : (preg_match('/\p{Lu}/u', $word) ? 'upper' : 'lower');
        }
        return preg_match('/\p{Lu}/u', $word) ? 'upper' : 'lower';
    }

    public static function normalize(string $word): string
    {
        $word = mb_strtolower($word, 'UTF-8');
        return strtr($word, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a',
            'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ì' => 'i', 'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'ò' => 'o', 'õ' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u', 'ÿ' => 'y', 'ñ' => 'n', 'œ' => 'oe', 'æ' => 'ae',
        ]);
    }

    public static function slug(string $key): string
    {
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', self::normalize($key)), '-');
        return $slug !== '' ? substr($slug, 0, 60) : 'tendance';
    }

    private static function isMeaningful(string $key): bool
    {
        if ($key === '' || preg_match('/^[\d.,+#-]+$/', $key)) {
            return false; // nombres seuls, années…
        }
        self::$stop ??= array_flip(self::STOP);
        if (isset(self::$stop[$key])) {
            return false;
        }
        return mb_strlen($key) >= 3 || in_array($key, self::SHORT_KEEP, true);
    }
}
