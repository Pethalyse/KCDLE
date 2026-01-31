<?php

namespace App\Services;

use App\Models\DailyGame;
use Illuminate\Support\Facades\Cache;
use Throwable;
use XMLWriter;

/**
 * Service responsible for generating the front-end XML sitemap.
 */
class SitemapService
{
    /**
     * Build the XML sitemap and return it as a string.
     *
     * @return string Sitemap XML content.
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
     * @return string Sitemap XML content.
     */
    protected function generateXml(): string
    {
        $baseUrl = $this->getBaseUrl();
        try {
            $latestDaily = $this->getLatestDailyDates(['kcdle', 'lecdle', 'lfldle']);
        } catch (Throwable) {
            $latestDaily = [];
        }

        $kcdleLastMod = $latestDaily['kcdle'] ?? null;
        $lecdleLastMod = $latestDaily['lecdle'] ?? null;
        $lfldleLastMod = $latestDaily['lfldle'] ?? null;
        $globalLastMod = $this->maxDate([$kcdleLastMod, $lecdleLastMod, $lfldleLastMod]);

        $entries = [
            $this->makeEntry($this->url($baseUrl, '/'), $globalLastMod, 'daily', 1.0),

            $this->makeEntry($this->url($baseUrl, '/kcdle'), $kcdleLastMod, 'daily', 0.9),
            $this->makeEntry($this->url($baseUrl, '/kcdle/higher-or-lower'), $kcdleLastMod, 'weekly', 0.6),
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
     * Get the sitemap base URL (front-end URL preferred).
     *
     * @return string Base URL without trailing slash.
     */
    protected function getBaseUrl(): string
    {
        $frontend = (string) config('app.frontend_url');
        $appUrl = (string) config('app.url');

        $base = $frontend !== '' ? $frontend : $appUrl;

        return rtrim($base, '/');
    }

    /**
     * Build a full absolute URL from a base URL and a path.
     *
     * @param string $baseUrl Base URL without trailing slash.
     * @param string $path URL path (with or without leading slash).
     * @return string Fully qualified URL.
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
     * Retrieve the latest daily dates for a set of DLE game keys.
     *
     * @param array<int, string> $games List of DLE game identifiers.
     * @return array<string, string> Map of game => YYYY-MM-DD.
     */
    protected function getLatestDailyDates(array $games): array
    {
        $rows = DailyGame::query()
            ->whereIn('game', $games)
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
     * Create a sitemap entry array.
     *
     * @param string $loc Absolute URL.
     * @param string|null $lastmod Date in YYYY-MM-DD.
     * @param string|null $changefreq Change frequency.
     * @param float|null $priority Priority between 0.0 and 1.0.
     * @return array<string, mixed> Entry data.
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
     * Compute the maximum date among the provided values.
     *
     * @param array<int, string|null> $dates Dates in YYYY-MM-DD.
     * @return string|null The most recent date.
     */
    protected function maxDate(array $dates): ?string
    {
        $filtered = array_values(array_filter($dates, fn ($d) => is_string($d) && $d !== ''));
        if (count($filtered) === 0) {
            return null;
        }

        rsort($filtered);

        return $filtered[0] ?? null;
    }

    /**
     * Write the sitemap XML from a list of entries.
     *
     * @param array<int, array<string, mixed>> $entries Sitemap entries.
     * @return string XML content.
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
                $w->writeElement('priority', rtrim(rtrim(number_format($p, 1, '.', ''), '0'), '.'));
            }

            $w->endElement();
        }

        $w->endElement();
        $w->endDocument();

        return $w->outputMemory();
    }

    /**
     * Get the cache key for the generated sitemap.
     *
     * @return string Cache key.
     */
    protected function getCacheKey(): string
    {
        $version = (string) config('app.version');
        $baseUrl = $this->getBaseUrl();

        return 'sitemap.xml:' . sha1($version . '|' . $baseUrl);
    }
}
