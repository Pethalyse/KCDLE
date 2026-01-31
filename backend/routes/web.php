<?php

/**
 * Web routes.
 *
 * This file contains routes that are not part of the JSON API. The front-end
 * application serves the public website pages, while this backend exposes
 * auxiliary endpoints such as the sitemap.
 */

use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
