<?php

namespace App\Services\Discord;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Discord OAuth2 client.
 *
 * This service generates authorization URLs, exchanges authorization codes for
 * access tokens, and retrieves the Discord user profile.
 */
class DiscordOAuthService
{
    /**
     * Build the Discord authorization URL.
     *
     * @param string $state CSRF state token.
     *
     * @return string
     */
    public function buildAuthorizationUrl(string $state): string
    {
        $clientId = (string) config('services.discord.client_id', '');
        $redirectUri = (string) config('services.discord.redirect_uri', '');
        $scopes = (string) config('services.discord.scopes', 'identify email');

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => $scopes,
            'state' => $state,
            'prompt' => 'consent',
        ]);

        $base = rtrim((string) config('services.discord.base_uri', 'https://discord.com'), '/');

        return $base . '/api/oauth2/authorize?' . $query;
    }

    /**
     * Exchange a Discord authorization code for an access token.
     *
     * @param string $code Authorization code.
     *
     * @return array{ok:bool,status:int,payload:array<string,mixed>}
     * @throws ConnectionException
     */
    public function exchangeCode(string $code): array
    {
        $clientId = (string) config('services.discord.client_id', '');
        $clientSecret = (string) config('services.discord.client_secret', '');
        $redirectUri = (string) config('services.discord.redirect_uri', '');
        $apiBase = rtrim((string) config('services.discord.api_base_uri', 'https://discord.com/api'), '/');

        /** @var Response $response */
        $response = Http::asForm()
            ->acceptJson()
            ->post($apiBase . '/oauth2/token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ]);

        if (! $response->ok()) {
            return [
                'ok' => false,
                'status' => $response->status(),
                'payload' => [
                    'message' => 'Discord token exchange failed.',
                    'discord' => $response->json(),
                ],
            ];
        }

        return [
            'ok' => true,
            'status' => $response->status(),
            'payload' => (array) $response->json(),
        ];
    }

    /**
     * Fetch the Discord user profile (users/@me).
     *
     * @param string $accessToken OAuth access token.
     *
     * @return array{ok:bool,status:int,payload:array<string,mixed>}
     * @throws ConnectionException
     */
    public function fetchUser(string $accessToken): array
    {
        $apiBase = rtrim((string) config('services.discord.api_base_uri', 'https://discord.com/api'), '/');

        /** @var Response $response */
        $response = Http::acceptJson()
            ->withToken($accessToken, 'Bearer')
            ->get($apiBase . '/users/@me');

        if (! $response->ok()) {
            return [
                'ok' => false,
                'status' => $response->status(),
                'payload' => [
                    'message' => 'Discord user fetch failed.',
                    'discord' => $response->json(),
                ],
            ];
        }

        return [
            'ok' => true,
            'status' => $response->status(),
            'payload' => (array) $response->json(),
        ];
    }
}
