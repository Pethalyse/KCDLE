<?php

namespace App\Services\Discord;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Service responsible for broadcasting site-side wins to the Discord bot.
 *
 * The bot exposes an internal HTTP endpoint that will fan-out the announcement
 * to each Discord guild/channel configured through the `/init` command.
 *
 * This service is intentionally best-effort: failures must not prevent the
 * game request from succeeding.
 */
class DiscordBotAnnouncementService
{
    /**
     * Announce that a Discord-linked user solved a given game on the website.
     *
     * The announcement is sent to the bot internal endpoint, authenticated via
     * the shared secret configured on both sides.
     *
     * @param string $discordId    Discord user identifier.
     * @param string $game         Game identifier (kcdle, lecdle, lfldle).
     * @param int    $guessesCount Number of guesses used to solve.
     *
     * @return void
     */
    public function announceSolved(string $discordId, string $game, int $guessesCount): void
    {
        $baseUrl = (string) config('services.discord.bot_internal_base_url', '');
        $secret = (string) config('services.discord.bot_secret', '');

        $baseUrl = trim($baseUrl);
        $secret = trim($secret);

        if ($baseUrl === '' || $secret === '') {
            return;
        }

        if (!in_array($game, ['kcdle', 'lecdle', 'lfldle'], true)) {
            return;
        }

        if ($guessesCount <= 0 || $discordId === '') {
            return;
        }

        $url = rtrim($baseUrl, '/') . '/internal/announce-solved';

        try {
            Http::timeout(2)
                ->withHeaders([
                    'X-KCDLE-Bot-Secret' => $secret,
                    'Accept' => 'application/json',
                ])
                ->post($url, [
                    'discord_id' => $discordId,
                    'game' => $game,
                    'guesses_count' => $guessesCount,
                ]);
        } catch (Throwable $e) {
            Log::warning('Discord bot announcement failed', [
                'error' => $e->getMessage(),
                'discord_id' => $discordId,
                'game' => $game,
                'guesses_count' => $guessesCount,
            ]);
        }
    }
}
