import { KcdleApiClient } from './KcdleApiClient.js';
import { GameSessionRepository, GameId, GuessRow, DailyRun } from './GameSessionRepository.js';
import { PlayerCache } from './PlayerCache.js';

export type SubmitGuessResult = {
    run: DailyRun;
    correct: boolean;
    alreadySolved: boolean;
    message?: string;
};

export class GameSessionService {
    private readonly api: KcdleApiClient;
    private readonly repo: GameSessionRepository;
    private readonly players: PlayerCache;

    public constructor(api: KcdleApiClient, repo: GameSessionRepository, players: PlayerCache) {
        this.api = api;
        this.repo = repo;
        this.players = players;
    }

    public async getOrCreateRun(discordUserId: string, guildId: string, channelId: string, game: GameId): Promise<DailyRun> {
        const daily = await this.api.getDaily(game);
        const date = String(daily.selected_for_date).slice(0, 10);

        const existing = this.repo.getRun(discordUserId, game, date);
        if (existing) {
            return existing;
        }

        const run: DailyRun = {
            discordUserId,
            guildId,
            channelId,
            game,
            date,
            guesses: [],
            solvedAt: null,
        };

        this.repo.upsertRun(run);
        return run;
    }

    public async submitGuess(discordUserId: string, guildId: string, channelId: string, game: GameId, playerId: number): Promise<SubmitGuessResult> {
        const daily = await this.api.getDaily(game);
        const date = String(daily.selected_for_date).slice(0, 10);

        const existing = this.repo.getRun(discordUserId, game, date);
        const guessOrder = (existing?.guesses.length ?? 0) + 1;

        const player = this.players.getById(game, playerId);
        const playerName = player?.playerName ?? `#${playerId}`;

        const { status, body } = await this.api.submitBotGuess(game, discordUserId, playerId, guessOrder);

        if (status === 409) {
            return {
                run: existing ?? (await this.getOrCreateRun(discordUserId, guildId, channelId, game)),
                correct: false,
                alreadySolved: true,
                message: String(body?.message ?? 'Already solved for today.'),
            };
        }

        if (status !== 200) {
            return {
                run: existing ?? (await this.getOrCreateRun(discordUserId, guildId, channelId, game)),
                correct: false,
                alreadySolved: false,
                message: String(body?.message ?? `Erreur (${status})`),
            };
        }

        const correct = Boolean(body?.correct ?? body?.comparison?.correct ?? false);
        const fields = (body?.comparison?.fields ?? {}) as Record<string, number | null>;

        const guessRow: GuessRow = {
            playerId,
            playerName,
            fields,
            correct,
        };

        const run = this.repo.appendGuess(discordUserId, guildId, channelId, game, date, guessRow);

        if (correct) {
            this.repo.markSolved(discordUserId, game, date);
            const refreshed = this.repo.getRun(discordUserId, game, date);
            return {
                run: refreshed ?? run,
                correct: true,
                alreadySolved: false,
            };
        }

        return {
            run,
            correct: false,
            alreadySolved: false,
        };
    }
}
