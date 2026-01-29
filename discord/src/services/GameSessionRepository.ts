import type Database from 'better-sqlite3';

export type GameId = 'kcdle' | 'lecdle' | 'lfldle';

export type GuessDisplay = {
    playerImageUrl: string | null;

    countryCode: string | null;
    countryName: string | null;

    age: number | null;
    gameName: string | null;

    firstOfficialYear: number | null;
    trophies: number | null;

    previousTeamName: string | null;
    currentTeamName: string | null;
    roleLabel: string | null;

    teamName: string | null;
    lolRole: string | null;
};

export type GuessRow = {
    playerId: number;
    playerName: string;
    fields: Record<string, number | null>;
    correct: boolean;
    display?: GuessDisplay;
};

export type DailyRun = {
    discordUserId: string;
    guildId: string;
    channelId: string;
    game: GameId;
    date: string;
    guesses: GuessRow[];
    solvedAt: string | null;
};

export class GameSessionRepository {
    private readonly db: Database.Database;

    public constructor(db: Database.Database) {
        this.db = db;
    }

    public getRun(discordUserId: string, game: GameId, date: string): DailyRun | null {
        const row = this.db
            .prepare('SELECT * FROM user_daily_runs WHERE discord_user_id = ? AND game = ? AND date = ?')
            .get(discordUserId, game, date) as any | undefined;

        if (!row) {
            return null;
        }

        const guesses = safeJsonParse<GuessRow[]>(row.guesses_json, []);

        return {
            discordUserId: String(row.discord_user_id),
            guildId: String(row.guild_id),
            channelId: String(row.channel_id),
            game: row.game as GameId,
            date: String(row.date),
            guesses,
            solvedAt: row.solved_at ? String(row.solved_at) : null,
        };
    }

    public upsertRun(run: DailyRun): void {
        const now = new Date().toISOString();

        this.db
            .prepare(
                `INSERT INTO user_daily_runs(discord_user_id, guild_id, channel_id, game, date, guesses_json, solved_at, created_at, updated_at)
         VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON CONFLICT(discord_user_id, game, date) DO UPDATE SET
           guild_id = excluded.guild_id,
           channel_id = excluded.channel_id,
           guesses_json = excluded.guesses_json,
           solved_at = excluded.solved_at,
           updated_at = excluded.updated_at`
            )
            .run(
                run.discordUserId,
                run.guildId,
                run.channelId,
                run.game,
                run.date,
                JSON.stringify(run.guesses),
                run.solvedAt,
                now,
                now
            );
    }

    public appendGuess(discordUserId: string, guildId: string, channelId: string, game: GameId, date: string, guess: GuessRow): DailyRun {
        const existing = this.getRun(discordUserId, game, date);

        const guesses = existing ? [...existing.guesses] : [];
        guesses.push(guess);

        const run: DailyRun = {
            discordUserId,
            guildId,
            channelId,
            game,
            date,
            guesses,
            solvedAt: existing?.solvedAt ?? null,
        };

        this.upsertRun(run);
        return run;
    }

    public markSolved(discordUserId: string, game: GameId, date: string): DailyRun | null {
        const existing = this.getRun(discordUserId, game, date);
        if (!existing) {
            return null;
        }

        const solvedAt = new Date().toISOString();
        const updated: DailyRun = {
            ...existing,
            solvedAt,
        };

        this.upsertRun(updated);
        return updated;
    }
}

function safeJsonParse<T>(value: unknown, fallback: T): T {
    if (typeof value !== 'string') {
        return fallback;
    }

    try {
        return JSON.parse(value) as T;
    } catch {
        return fallback;
    }
}
