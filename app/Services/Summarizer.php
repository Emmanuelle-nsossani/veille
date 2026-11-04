<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Résumé d'une tendance.
 * - Avec une clé API Claude (page Sources, stockée dans storage/ai.json) : résumé rédigé par l'IA.
 * - Sans clé, ou si l'appel échoue : résumé automatique fait d'extraits des articles.
 */
class Summarizer
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';

    public function __construct(private array $config)
    {
    }

    /** @return array{text:string, source:string} source = 'ia' ou 'extraits' */
    public function summarize(string $label, array $articles): array
    {
        if ($this->apiKey() !== '') {
            try {
                $text = $this->withAi($label, $articles);
                $this->recordStatus(null);
                return ['text' => $text, 'source' => 'ia'];
            } catch (\Throwable $e) {
                error_log('[Veille] Résumé IA impossible, repli sur les extraits : ' . $e->getMessage());
                $this->recordStatus($e->getMessage());
            }
        }
        return ['text' => $this->extractive($articles), 'source' => 'extraits'];
    }

    public function apiKey(): string
    {
        return (string) ($this->settings()['api_key'] ?? '');
    }

    /** Dernière erreur de l'API (null si le dernier appel a réussi). */
    public function lastError(): ?array
    {
        $s = $this->settings();
        return !empty($s['last_error']) ? ['message' => $s['last_error'], 'at' => $s['last_error_at'] ?? ''] : null;
    }

    private function settings(): array
    {
        $file = $this->config['ai_path'];
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    private function recordStatus(?string $error): void
    {
        $s = $this->settings();
        if ($s === [] || ($s['last_error'] ?? null) === $error) {
            return;
        }
        $s['last_error'] = $error;
        $s['last_error_at'] = $error ? date('Y-m-d H:i:s') : null;
        file_put_contents($this->config['ai_path'], json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    public function saveApiKey(?string $key): void
    {
        $file = $this->config['ai_path'];
        if ($key === null || $key === '') {
            if (is_file($file)) {
                unlink($file);
            }
            return;
        }
        file_put_contents($file, json_encode(['api_key' => $key, 'saved_at' => date('c')], JSON_PRETTY_PRINT), LOCK_EX);
        @chmod($file, 0600);
    }

    /** Une phrase par source (trois au plus), une par ligne : « Source — phrase ». */
    public function extractive(array $articles): string
    {
        $lines = [];
        $seen = [];
        foreach ($articles as $a) {
            if (isset($seen[$a['source_id']]) || count($lines) >= 3) {
                continue;
            }
            $sentence = $this->firstSentence($a['summary']) ?: $a['title'];
            $lines[] = $a['source_name'] . ' — ' . $sentence;
            $seen[$a['source_id']] = true;
        }
        return implode("\n", $lines);
    }

    private function firstSentence(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        if (preg_match('/^(.{40,240}?[.!?])(\s|$)/u', $text, $m)) {
            return $m[1];
        }
        return mb_strlen($text) > 220 ? rtrim(mb_substr($text, 0, 220), ' ,.;:') . '…' : $text;
    }

    private function withAi(string $label, array $articles): string
    {
        $lines = [];
        foreach (array_slice($articles, 0, 10) as $a) {
            $lines[] = '- [' . $a['source_name'] . '] ' . $a['title'] . ($a['summary'] !== '' ? ' : ' . mb_substr($a['summary'], 0, 300) : '');
        }
        $prompt = "Voici des articles de veille récents sur le sujet « $label » :\n\n" . implode("\n", $lines)
            . "\n\nRédige en français un résumé de 3 ou 4 phrases (90 mots au plus) : ce qui se passe et pourquoi c'est notable."
            . " Appuie-toi uniquement sur ces extraits, n'invente aucun fait ni chiffre, ne mets ni titre, ni liste, ni lien.";

        $payload = json_encode([
            'model'      => $this->config['ai_model'],
            'max_tokens' => 400,
            'system'     => "Tu rédiges des synthèses de veille numérique claires et factuelles pour une professionnelle du web et du produit.",
            'messages'   => [['role' => 'user', 'content' => $prompt]],
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 40,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => [
                'content-type: application/json',
                'x-api-key: ' . $this->apiKey(),
                'anthropic-version: 2023-06-01',
            ],
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new \RuntimeException('connexion impossible : ' . $error);
        }
        $data = json_decode((string) $body, true);
        if ($status !== 200) {
            throw new \RuntimeException("HTTP $status : " . ($data['error']['message'] ?? mb_substr((string) $body, 0, 200)));
        }
        $text = '';
        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            }
        }
        $text = trim($text);
        if ($text === '') {
            throw new \RuntimeException('réponse vide');
        }
        return $text;
    }
}
