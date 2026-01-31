<?php

namespace App\Http\Controllers;

use App\Services\SitemapService;
use Illuminate\Http\Response;

/**
 * Handles the public sitemap endpoint.
 */
class SitemapController extends Controller
{
    /**
     * @param SitemapService $sitemapService The service used to generate the sitemap.
     */
    public function __construct(
        protected SitemapService $sitemapService
    ) {
    }

    /**
     * Return the XML sitemap.
     *
     * @return Response
     */
    public function index(): Response
    {
        $xml = $this->sitemapService->buildXml();

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
