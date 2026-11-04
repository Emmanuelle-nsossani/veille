<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Article;
use App\Models\Source;
use SimpleXMLElement;

/**
 * Télécharge les flux actifs (RSS 2.0, RSS 1.0/RDF, Atom),
 * enregistre les nouveaux articles avec leurs thèmes et purge les anciens.
 */
class FeedFetcher
{
    private const MAX_ITEMS_PER_FEED = 50;
    private const SUMMARY_LENGTH = 400;

    private const NS_ATOM = 'http://www.w3.org/2005/Atom';
    private const NS_RSS1 = 'http://purl.org/rss/1.0/';
    private const NS_CONTENT = 'http://purl.org/rss/1.0/modules/content/';
    private const NS_DC = 'http://purl.org/dc/elements/1.1/';

    public function __construct(
        private array $config,
        private Source $sources,
        private Article $articles,
        private ThemeClassifier $classifier,
    ) {
    }

    /** @return array{sources:int, new:int, errors:int, purged:int, details:string[]} */
    public function run(): array
    {
        $report = ['sources' => 0, 'new' => 0, 'errors' => 0, 'purged' => 0, 'details' => []];
        $cutoff = strtotime('-' . (int) $this->config['retention_days'] . ' days');

        foreach ($this->sources->active() as $source) {
            $report['sources']++;
            try {
                $items = $this->parse($this->download($source['url']));
                $new = 0;
                foreach (array_slice($items, 0, self::MAX_ITEMS_PER_FEED) as $item) {
                    if (strtotime($item['published_at']) < $cutoff) {
                        continue;
                    }
                    $themes = $this->classifier->classify($item['title'], $item['summary']);
                    if ($this->articles->insert((int) $source['id'], $item, $themes)) {
                        $new++;
                    }
                }
                $this->sources->recordResult((int) $source['id'], null, $new);
                $report['new'] += $new;
                $report['details'][] = "OK      {$source['name']} : $new nouveau(x)";
            } catch (\Throwable $e) {
                $message = mb_substr($e->getMessage(), 0, 200);
                $this->sources->recordResult((int) $source['id'], $message, 0);
                $report['errors']++;
                $report['details'][] = "ERREUR  {$source['name']} : $message";
            }
        }

        $report['purged'] = $this->articles->purge((int) $this->config['retention_days']);
        return $report;
    }

    private function download(string $url): string
    {
        $timeout = (int) $this->config['fetch_timeout'];
        $accept = 'application/rss+xml, application/atom+xml, application/xml;q=0.9, text/xml;q=0.8, */*;q=0.5';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_USERAGENT      => $this->config['user_agent'],
                CURLOPT_ENCODING       => '',
                CURLOPT_HTTPHEADER     => ['Accept: ' . $accept],
            ];
            if (defined('CURLOPT_PROTOCOLS_STR')) {
                $options[CURLOPT_PROTOCOLS_STR] = 'http,https';
                $options[CURLOPT_REDIR_PROTOCOLS_STR] = 'http,https';
            } else {
                $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
                $options[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
            }
            curl_setopt_array($ch, $options);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($body === false) {
                throw new \RuntimeException('Connexion impossible : ' . $error);
            }
            if ($status >= 400) {
                throw new \RuntimeException("Le serveur répond HTTP $status");
            }
            return (string) $body;
        }

        $context = stream_context_create(['http' => [
            'timeout'       => $timeout,
            'user_agent'    => $this->config['user_agent'],
            'header'        => 'Accept: ' . $accept,
            'ignore_errors' => true,
        ]]);
        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            throw new \RuntimeException('Connexion impossible');
        }
        $statusLine = $http_response_header[0] ?? '';
        if (preg_match('#\s(\d{3})\s#', $statusLine, $m) && (int) $m[1] >= 400) {
            throw new \RuntimeException("Le serveur répond HTTP {$m[1]}");
        }
        return $body;
    }

    /** @return array<int, array{guid:string, url:string, title:string, summary:string, published_at:string}> */
    private function parse(string $body): array
    {
        $body = ltrim(preg_replace('/^\xEF\xBB\xBF/', '', $body) ?? $body);
        if ($body === '' || $body[0] !== '<') {
            throw new \RuntimeException("La réponse n'est pas un flux RSS/Atom");
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            throw new \RuntimeException('XML illisible');
        }

        $root = strtolower($xml->getName());
        $raw = match ($root) {
            'rss'  => $this->rssItems($xml->channel->item ?? []),
            'rdf'  => $this->rssItems($xml->children(self::NS_RSS1)->item ?? []),
            'feed' => $this->atomItems($xml),
            default => throw new \RuntimeException("Format non reconnu (<$root>), ce n'est pas un flux RSS/Atom"),
        };

        $items = [];
        foreach ($raw as $item) {
            $title = $this->clean($item['title']);
            $url = trim($item['url']);
            if ($title === '' || !preg_match('#^https?://#i', $url)) {
                continue;
            }
            $items[] = [
                'guid'         => mb_substr(trim($item['guid']) ?: $url, 0, 500),
                'url'          => $url,
                'title'        => mb_substr($title, 0, 300),
                'summary'      => $this->summary($item['summary']),
                'published_at' => $this->date($item['date']),
            ];
        }
        return $items;
    }

    private function rssItems(iterable $nodes): array
    {
        $out = [];
        foreach ($nodes as $node) {
            $rss1 = $node->children(self::NS_RSS1);
            $dc = $node->children(self::NS_DC);
            $content = $node->children(self::NS_CONTENT);

            $description = (string) ($node->description ?? '') ?: (string) ($rss1->description ?? '');
            $out[] = [
                'title'   => (string) ($node->title ?? '') ?: (string) ($rss1->title ?? ''),
                'url'     => (string) ($node->link ?? '') ?: (string) ($rss1->link ?? ''),
                'guid'    => (string) ($node->guid ?? '') ?: (string) ($node->attributes('rdf', true)?->about ?? ''),
                'summary' => $description ?: (string) ($content->encoded ?? ''),
                'date'    => (string) ($node->pubDate ?? '') ?: (string) ($dc->date ?? ''),
            ];
        }
        return $out;
    }

    private function atomItems(SimpleXMLElement $xml): array
    {
        $namespaces = $xml->getNamespaces();
        $feed = in_array(self::NS_ATOM, $namespaces, true) ? $xml->children(self::NS_ATOM) : $xml;

        $out = [];
        foreach ($feed->entry ?? [] as $entry) {
            $entry = in_array(self::NS_ATOM, $namespaces, true) ? $entry->children(self::NS_ATOM) : $entry;
            $link = '';
            foreach ($entry->link ?? [] as $l) {
                $attrs = $l->attributes();
                $rel = (string) ($attrs['rel'] ?? 'alternate');
                if ($rel === 'alternate' || $link === '') {
                    $link = (string) ($attrs['href'] ?? '');
                    if ($rel === 'alternate') {
                        break;
                    }
                }
            }
            $out[] = [
                'title'   => (string) ($entry->title ?? ''),
                'url'     => $link,
                'guid'    => (string) ($entry->id ?? ''),
                'summary' => (string) ($entry->summary ?? '') ?: (string) ($entry->content ?? ''),
                'date'    => (string) ($entry->published ?? '') ?: (string) ($entry->updated ?? ''),
            ];
        }
        return $out;
    }

    /** Texte brut : sans balises, entités décodées, espaces normalisés. */
    private function clean(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text); // au cas où des balises étaient encodées en entités
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function summary(string $html): string
    {
        // Un espace avant chaque balise évite de coller les mots de deux paragraphes
        $text = $this->clean(str_replace('<', ' <', $html));
        if (mb_strlen($text) <= self::SUMMARY_LENGTH) {
            return $text;
        }
        $cut = mb_substr($text, 0, self::SUMMARY_LENGTH);
        $space = mb_strrpos($cut, ' ');
        return rtrim($space > 200 ? mb_substr($cut, 0, $space) : $cut, " ,.;:") . '…';
    }

    /** Date locale « Y-m-d H:i:s ». Absente, illisible ou dans le futur : maintenant. */
    private function date(string $value): string
    {
        $ts = trim($value) !== '' ? strtotime(trim($value)) : false;
        if ($ts === false || $ts > time() + 3600) {
            $ts = time();
        }
        return date('Y-m-d H:i:s', $ts);
    }
}
