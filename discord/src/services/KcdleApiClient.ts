import { GameId } from './GameSessionRepository.js';

export type DailyGameInfo = {
    id: number;
    game: GameId;
    selected_for_date: string;
};

export type PlayerListItem = {
    id: number;
    playerName: string;
};

export type GuessResponse = {
    correct: boolean;
    comparison: {
        correct: boolean;
        fields: Record<string, number | null>;
    };
    stats: {
        solvers_count: number;
        total_guesses: number;
        average_guesses: number | null;
    };
    unlocked_achievements: unknown[];
};

export class KcdleApiClient {
    private readonly baseUrl: string;
    private readonly botSecret: string;

    public constructor(baseUrl: string, botSecret: string) {
        this.baseUrl = baseUrl.replace(/\/$/, '');
        this.botSecret = botSecret;
    }

    public async getDaily(game: GameId): Promise<DailyGameInfo> {
        const url = `${this.baseUrl}/games/${game}/daily`;
        const res = await fetch(url, { method: 'GET' });
        if (!res.ok) {
            const text = await safeText(res);
            throw new Error(`KCDLE daily failed (${res.status}): ${text}`);
        }
        const json = (await res.json()) as any;
        return {
            id: Number(json.id),
            game,
            selected_for_date: String(json.selected_for_date),
        };
    }

    public async getPlayers(game: GameId, activeOnly: boolean): Promise<PlayerListItem[]> {
        const url = `${this.baseUrl}/games/${game}/players?active=${activeOnly ? '1' : '0'}`;
        const res = await fetch(url, { method: 'GET' });
        if (!res.ok) {
            const text = await safeText(res);
            throw new Error(`KCDLE players failed (${res.status}): ${text}`);
        }

        const json = (await res.json()) as any;
        const players = Array.isArray(json.players) ? json.players : [];

        return players
            .map((p: any) => {
                const id = Number(p.id);
                const name = String(p?.player?.name ?? p?.player?.pseudo ?? p?.player?.slug ?? '');
                return { id, playerName: name };
            })
            .filter((p: PlayerListItem) => Number.isFinite(p.id) && p.id > 0 && p.playerName.length > 0);
    }

    public async submitBotGuess(game: GameId, discordId: string, playerId: number, guessOrder: number): Promise<{ status: number; body: any }>
    {
        const url = `${this.baseUrl}/discord/bot/games/${game}/guess`;

        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Discord-Bot-Secret': this.botSecret,
            },
            body: JSON.stringify({
                discord_id: discordId,
                player_id: playerId,
                guesses: guessOrder,
            }),
        });

        const status = res.status;
        const body = await safeJson(res);
        return { status, body };
    }
}

async function safeJson(res: Response): Promise<any> {
    try {
        return await res.json();
    } catch {
        return { message: await safeText(res) };
    }
}

async function safeText(res: Response): Promise<string> {
    try {
        return await res.text();
    } catch {
        return '';
    }
}
