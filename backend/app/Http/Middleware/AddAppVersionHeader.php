<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddAppVersionHeader
{
    /**
     * Handle an incoming request.
     *
     * When an application version is configured, this middleware injects an
     * `X-KCDLE-Version` header in every HTTP response. The frontend can use
     * this header to detect deployments and react gracefully (refresh, token
     * rotation, etc.) without breaking the user experience.
     *
     * @param Request $request Incoming HTTP request.
     * @param Closure(Request): Response $next Next middleware.
     *
     * @return Response HTTP response.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $version = (string) config('app.version', '');
        if ($version !== '') {
            $response->headers->set('X-KCDLE-Version', $version);
        }

        return $response;
    }
}
