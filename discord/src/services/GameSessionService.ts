import { KcdleApiClient, PlayerListItem } from './KcdleApiClient.js';
import { GameId, GuessRow, DailyRun, GuessDisplay } from './GameSessionRepository.js';
import { PlayerCache } from './PlayerCache.js';

export type SubmitGuessResult = {
    run: DailyRun;
    correct: boolean;
    alreadySolved: boolean;
    message?: string;
};

/**
 * Orchestrates bot interactions with KCDLE API.
 *
 * The API is the single source of truth for:
 * - today's date
 * - whether a game is solved
 * - the full guess history (including guesses made on the website)
 */
export class GameSessionService {
    private readonly api: KcdleApiClient;
    private readonly players: PlayerCache;

    public constructor(api: KcdleApiClient, players: PlayerCache) {
        this.api = api;
        this.players = players;
    }

    public async getTodayRun(discordUserId: string, guildId: string, channelId: string, game: GameId): Promise<DailyRun> {
        const daily = await this.api.getDaily(game);
        const date = this.computeRunDate(daily.selected_for_date);

        const today = await this.api.getDiscordTodayRun(game, discordUserId);

        return {
            discordUserId,
            guildId,
            channelId,
            game,
            date,
            guesses: today.history
                .slice()
                .sort((a, b) => a.guess_order - b.guess_order)
                .map((g) => {
                    const player = this.players.getById(game, g.player_id);
                    const playerName = player?.playerName ?? g.player_name ?? `#${g.player_id}`;

                    const row: GuessRow = {
                        playerId: g.player_id,
                        playerName,
                        fields: g.fields ?? {},
                        correct: Boolean(g.correct),
                        display: buildDisplay(game, player),
                    };

                    return row;
                }),
            solvedAt: today.solved ? date : null,
        };
    }

    public async submitGuess(discordUserId: string, guildId: string, channelId: string, game: GameId, playerId: number): Promise<SubmitGuessResult> {
        const today = await this.api.getDiscordTodayRun(game, discordUserId);

        if (today.solved) {
            return {
                run: await this.getTodayRun(discordUserId, guildId, channelId, game),
                correct: false,
                alreadySolved: true,
                message: "Déjà terminé pour aujourd'hui.",
            };
        }

        if (today.history.some((g) => g.player_id === playerId)) {
            return {
                run: await this.getTodayRun(discordUserId, guildId, channelId, game),
                correct: false,
                alreadySolved: false,
                message: 'Tu as déjà proposé ce joueur.',
            };
        }

        const guessOrder = today.history.length + 1;

        const { status, body } = await this.api.submitBotGuess(game, discordUserId, playerId, guessOrder);

        if (status === 409) {
            return {
                run: await this.getTodayRun(discordUserId, guildId, channelId, game),
                correct: false,
                alreadySolved: true,
                message: String(body?.message ?? 'Already solved for today.'),
            };
        }

        if (status !== 200) {
            return {
                run: await this.getTodayRun(discordUserId, guildId, channelId, game),
                correct: false,
                alreadySolved: false,
                message: String(body?.message ?? `Erreur (${status})`),
            };
        }

        const correct = Boolean(body?.correct ?? body?.comparison?.correct ?? false);

        const run = await this.getTodayRun(discordUserId, guildId, channelId, game);

        return {
            run,
            correct,
            alreadySolved: false,
        };
    }

    private computeRunDate(selectedForDate: string): string {
        return toParisIsoDate(selectedForDate);
    }
}

function toParisIsoDate(input: unknown): string {
    const raw = String(input ?? '').trim();
    if (!raw) {
        return '';
    }

    const simple = /^\d{4}-\d{2}-\d{2}$/.exec(raw);
    if (simple) {
        return raw;
    }

    const d = new Date(raw);
    if (Number.isNaN(d.getTime())) {
        return raw.slice(0, 10);
    }

    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Europe/Paris',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(d);

    const y = parts.find((p) => p.type === 'year')?.value ?? '';
    const m = parts.find((p) => p.type === 'month')?.value ?? '';
    const day = parts.find((p) => p.type === 'day')?.value ?? '';

    if (!y || !m || !day) {
        return raw.slice(0, 10);
    }

    return `${y}-${m}-${day}`;
}

function buildDisplay(game: GameId, player: PlayerListItem | null): GuessDisplay | undefined {
    if (!player) {
        return undefined;
    }

    const age = computeAge(player.birthdate);

    if (game === 'kcdle') {
        return {
            playerImageUrl: player.imageUrl,
            countryCode: player.countryCode,
            countryName: player.countryName,
            age,
            gameName: player.gameName,
            firstOfficialYear: player.firstOfficialYear,
            trophies: player.trophies,
            previousTeamName: player.previousTeamName,
            currentTeamName: player.currentTeamName,
            roleLabel: player.roleLabel,
            teamName: null,
            lolRole: null,
        };
    }

    return {
        playerImageUrl: player.imageUrl,
        countryCode: player.countryCode,
        countryName: player.countryName,
        age,
        gameName: null,
        firstOfficialYear: null,
        trophies: null,
        previousTeamName: null,
        currentTeamName: null,
        roleLabel: null,
        teamName: player.teamName,
        lolRole: player.lolRole,
    };
}

function computeAge(birthdate: string | null): number | null {
    if (!birthdate) {
        return null;
    }

    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(birthdate);
    if (!match) {
        return null;
    }

    const year = Number(match[1]);
    const month = Number(match[2]);
    const day = Number(match[3]);

    if (!Number.isFinite(year) || !Number.isFinite(month) || !Number.isFinite(day)) {
        return null;
    }

    const today = new Date();
    let age = today.getUTCFullYear() - year;

    const m = today.getUTCMonth() + 1;
    const d = today.getUTCDate();

    if (m < month || (m === month && d < day)) {
        age--;
    }

    if (age < 0 || age > 120) {
        return null;
    }

    return age;
}
