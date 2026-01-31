<?php

namespace App\Services;

use App\Models\DailyGame;
use Illuminate\Support\Facades\Cache;
use XMLWriter;

/**
 * Generates a public XML sitemap for the front-end website.
 */
class SitemapService
{
    /**
     * Build the sitemap XML.
     *
     * The result is cached to avoid generating XML on every request.
     *
     * @return string
     */
    public function buildXml(): string
    {
        $cacheKey = $this->getCacheKey();
        $ttlSeconds = (int) config('sitemap.cache_ttl_seconds', 3600);

        return Cache::remember($cacheKey, $ttlSeconds, function (): string {
            return $this->generateXml();
        });
    }

    /**
     * Generate the sitemap XML without using cache.
     *
     * @return string
     */
    protected function generateXml(): string
    {
        $baseUrl = $this->getBaseUrl();
        $lastMods = $this->getLatestDailyDates(['kcdle', 'lecdle', 'lfldle']);

        $kcdleLastMod = $lastMods['kcdle'] ?? null;
        $lecdleLastMod = $lastMods['lecdle'] ?? null;
        $lfldleLastMod = $lastMods['lfldle'] ?? null;
        $globalLastMod = $this->maxDate([$kcdleLastMod, $lecdleLastMod, $lfldleLastMod]);

        $entries = [
            $this->makeEntry($this->url($baseUrl, '/'), $globalLastMod, 'daily', 1.0),

            $this->makeEntry($this->url($baseUrl, '/kcdle'), $kcdleLastMod, 'daily', 0.9),
            $this->makeEntry($this->url($baseUrl, '/lecdle'), $lecdleLastMod, 'daily', 0.9),
            $this->makeEntry($this->url($baseUrl, '/lfldle'), $lfldleLastMod, 'daily', 0.9),

            $this->makeEntry($this->url($baseUrl, '/leaderboard/kcdle'), $kcdleLastMod, 'daily', 0.6),
            $this->makeEntry($this->url($baseUrl, '/leaderboard/lecdle'), $lecdleLastMod, 'daily', 0.6),
            $this->makeEntry($this->url($baseUrl, '/leaderboard/lfldle'), $lfldleLastMod, 'daily', 0.6),

            $this->makeEntry($this->url($baseUrl, '/credits'), null, 'yearly', 0.2),
            $this->makeEntry($this->url($baseUrl, '/privacy'), null, 'yearly', 0.1),
            $this->makeEntry($this->url($baseUrl, '/legal'), null, 'yearly', 0.1),
        ];

        return $this->writeXml($entries);
    }

    /**
     * Determine the front-end base URL.
     *
     * @return string
     */
    protected function getBaseUrl(): string
    {
        $frontendUrl = (string) config('app.frontend_url');
        $appUrl = (string) config('app.url');

        $base = $frontendUrl !== '' ? $frontendUrl : $appUrl;

        return rtrim($base, '/');
    }

    /**
     * Build an absolute URL from a base URL and a path.
     *
     * @param string $baseUrl
     * @param string $path
     * @return string
     */
    protected function url(string $baseUrl, string $path): string
    {
        $path = $path === '' ? '/' : $path;
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        return $baseUrl . $path;
    }

    /**
     * Retrieve the latest daily dates for the given games.
     *
     * @param array<int, string> $games
     * @return array<string, string>
     */
    protected function getLatestDailyDates(array $games): array
    {
        $rows = DailyGame::query()
            ->whereIn('game', $games)
            ->whereDate('selected_for_date', '<=', now()->toDateString())
            ->select('game')
            ->selectRaw('MAX(selected_for_date) as last_date')
            ->groupBy('game')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $game = (string) $row->getAttribute('game');
            $date = $row->getAttribute('last_date');
            if ($game === '' || $date === null) {
                continue;
            }
            $out[$game] = (string) $date;
        }

        return $out;
    }

    /**
     * Create a sitemap entry.
     *
     * @param string $loc
     * @param string|null $lastmod
     * @param string|null $changefreq
     * @param float|null $priority
     * @return array<string, mixed>
     */
    protected function makeEntry(string $loc, ?string $lastmod, ?string $changefreq, ?float $priority): array
    {
        return [
            'loc' => $loc,
            'lastmod' => $lastmod,
            'changefreq' => $changefreq,
            'priority' => $priority,
        ];
    }

    /**
     * Return the most recent date among the provided values.
     *
     * @param array<int, string|null> $dates
     * @return string|null
     */
    protected function maxDate(array $dates): ?string
    {
        $filtered = array_values(array_filter($dates, static fn ($d) => is_string($d) && $d !== ''));
        if ($filtered === []) {
            return null;
        }

        rsort($filtered);

        return $filtered[0] ?? null;
    }

    /**
     * Write the sitemap XML.
     *
     * @param array<int, array<string, mixed>> $entries
     * @return string
     */
    protected function writeXml(array $entries): string
    {
        $w = new XMLWriter();
        $w->openMemory();
        $w->startDocument('1.0', 'UTF-8');
        $w->startElement('urlset');
        $w->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        foreach ($entries as $entry) {
            $loc = (string) ($entry['loc'] ?? '');
            if ($loc === '') {
                continue;
            }

            $w->startElement('url');
            $w->writeElement('loc', $loc);

            $lastmod = $entry['lastmod'] ?? null;
            if (is_string($lastmod) && $lastmod !== '') {
                $w->writeElement('lastmod', $lastmod);
            }

            $changefreq = $entry['changefreq'] ?? null;
            if (is_string($changefreq) && $changefreq !== '') {
                $w->writeElement('changefreq', $changefreq);
            }

            $priority = $entry['priority'] ?? null;
            if (is_float($priority) || is_int($priority)) {
                $p = max(0.0, min(1.0, (float) $priority));
                $w->writeElement('priority', number_format($p, 1, '.', ''));
            }

            $w->endElement();
        }

        $w->endElement();
        $w->endDocument();

        return $w->outputMemory();
    }

    /**
     * Build the cache key for the sitemap.
     *
     * @return string
     */
    protected function getCacheKey(): string
    {
        $version = (string) config('app.version');
        $baseUrl = $this->getBaseUrl();

        return 'sitemap.xml:' . sha1($version . '|' . $baseUrl);
    }
}
