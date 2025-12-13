<?php

namespace App\Services;

use Illuminate\Http\Request;

class AnonKeyService
{
    /**
     * Generate a deterministic anonymous key from the client's IP.
     *
     * The anonymous key is used to associate PendingGuess entries with a browser
     * session before the user registers or logs in. It does not expose the IP
     * directly; instead, an HMAC-SHA256 hash of the IP combined with the
     * application key is produced.
     *
     * Multiple requests coming from the same IP will produce the same key,
     * ensuring continuity of pending guesses through the anonymous session.
     *
     * @param Request $request HTTP request providing the client IP.
     *
     * @return string Deterministic anonymous identifier.
     */
    public function fromRequest(Request $request): string
    {
        $ip = (string) $request->ip();
        return hash_hmac('sha256', $ip, config('app.key'));
    }
}
