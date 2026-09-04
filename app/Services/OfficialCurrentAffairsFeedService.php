<?php

namespace App\Services;

use App\Models\ContentSource;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OfficialCurrentAffairsFeedService
{
    public function __construct(
        private readonly CurrentAffairsService $currentAffairs,
        private readonly TrustedSourcePolicy $sourcePolicy,
    ) {}

    public function fetch(ContentSource $source, int $limit = 100, bool $dryRun = false): array
    {
        $this->assertFetchable($source);

        $response = $this->client($source)->get($source->feed_url);
        $source->update(['last_checked_at' => now()]);

        if (! $response->successful()) {
            throw new RuntimeException("Official feed returned HTTP {$response->status()}.");
        }

        $items = array_slice($this->parse($response->body(), $source), 0, max(1, min($limit, 500)));

        if ($dryRun) {
            return ['fetched' => count($items), 'accepted' => 0, 'duplicates' => 0, 'rejected' => 0];
        }

        return ['fetched' => count($items)] + $this->currentAffairs->ingest($source, $items);
    }

    private function client(ContentSource $source): PendingRequest
    {
        return Http::accept('application/rss+xml, application/atom+xml, application/xml, text/xml')
            ->withUserAgent('Mozilla/5.0 (compatible; MCI-Test-Series/1.0; +https://test.mciedu.com)')
            ->withHeaders(['Referer' => rtrim((string) $source->base_url, '/').'/'])
            ->connectTimeout(10)
            ->timeout(25)
            ->retry(2, 500, throw: false)
            ->withOptions(['allow_redirects' => ['max' => 3]]);
    }

    private function assertFetchable(ContentSource $source): void
    {
        if (! $source->is_active || ! $source->allow_current_affairs || ! filled($source->feed_url)) {
            throw new RuntimeException('Source is not enabled for official current-affairs feeds.');
        }

        if (! $this->sourcePolicy->canGenerateQuestions($source)) {
            throw new RuntimeException('Source has not passed the trusted-source policy.');
        }

        $feedHost = strtolower((string) parse_url($source->feed_url, PHP_URL_HOST));
        $baseHost = strtolower((string) parse_url($source->base_url, PHP_URL_HOST));

        if (parse_url($source->feed_url, PHP_URL_SCHEME) !== 'https'
            || ! $this->sameOfficialDomain($feedHost, $baseHost)) {
            throw new RuntimeException('Feed URL must use HTTPS on the registered official domain.');
        }
    }

    private function parse(string $xml, ContentSource $source): array
    {
        $previous = libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($document === false) {
            throw new RuntimeException('Official feed returned invalid XML.');
        }

        $nodes = $document->channel->item ?? $document->entry ?? [];
        $items = [];

        foreach ($nodes as $node) {
            $link = trim((string) ($node->link['href'] ?? $node->link ?? ''));
            $host = strtolower((string) parse_url($link, PHP_URL_HOST));
            $baseHost = strtolower((string) parse_url($source->base_url, PHP_URL_HOST));

            if ($link === '' || parse_url($link, PHP_URL_SCHEME) !== 'https'
                || ! $this->sameOfficialDomain($host, $baseHost)) {
                continue;
            }

            $title = $this->clean((string) $node->title);
            $summary = $this->clean((string) ($node->description ?? $node->summary ?? $node->content ?? ''));
            $published = trim((string) ($node->pubDate ?? $node->published ?? $node->updated ?? ''));
            $published = $published !== '' && strtotime($published) !== false ? $published : null;

            if ($title === '' || mb_strlen($title) > 500) {
                continue;
            }

            $items[] = [
                'external_reference' => mb_substr(trim((string) ($node->guid ?? $node->id ?? $link)), 0, 255),
                'title' => $title,
                'summary' => mb_substr($summary, 0, 5000),
                'source_url' => $link,
                'published_at' => $published,
                'metadata' => ['feed_url' => $source->feed_url, 'imported_as' => 'official_feed_fact'],
            ];
        }

        return $items;
    }

    private function clean(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    private function sameOfficialDomain(string $candidate, string $registered): bool
    {
        $registered = preg_replace('/^www\./', '', $registered) ?? $registered;
        $candidate = preg_replace('/^www\./', '', $candidate) ?? $candidate;

        return $candidate === $registered || str_ends_with($candidate, '.'.$registered);
    }
}
