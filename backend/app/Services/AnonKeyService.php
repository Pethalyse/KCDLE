<?php

namespace App\Services;

use Illuminate\Http\Request;

/**
 * Anonymous key generator service.
 *
 * This service provides deterministic keys to associate guesses to a non-authenticated identity.
 * - Browser flow: based on IP (fromRequest)
 * - Discord bot flow: based on a provided stable identifier (fromValue)
 */
class AnonKeyService
{
    /**
     * Generate a deterministic anonymous key from the client's IP.
     *
     * @param Request $request HTTP request providing the client IP.
     *
     * @return string Deterministic anonymous identifier.
     */
    public function fromRequest(Request $request): string
    {
        $ip = (string) $request->ip();

        return $this->fromValue($ip);
    }

    /**
     * Generate a deterministic anonymous key from an arbitrary stable value.
     *
     * @param string $value Stable identifier (e.g. IP, "discord:{id}", etc.).
     *
     * @return string Deterministic anonymous identifier.
     */
    public function fromValue(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }
}
