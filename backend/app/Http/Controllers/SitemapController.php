<?php

namespace App\Http\Controllers;

use App\Services\SitemapService;
use Illuminate\Http\Response;

/**
 * Controller responsible for serving the XML sitemap.
 */
class SitemapController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @param SitemapService $sitemapService Service used to generate the sitemap XML.
     */
    public function __construct(
        protected SitemapService $sitemapService
    ) {}

    /**
     * Return the sitemap XML.
     *
     * @return Response XML response compliant with the sitemaps.org protocol.
     */
    public function index(): Response
    {
        $xml = $this->sitemapService->buildXml();

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
