import {GameId} from './GameSessionRepository.js';
import type {KcdleApiClient, PlayerListItem} from './KcdleApiClient.js';

export class PlayerCache {
    private readonly api: KcdleApiClient;
    private readonly byGame: Map<GameId, PlayerListItem[]> = new Map();
    private readonly indexedByGame: Map<GameId, Map<string, PlayerListItem>> = new Map();

    public constructor(api: KcdleApiClient) {
        this.api = api;
    }

    public async warmup(): Promise<void> {
        await Promise.all([this.load('kcdle'), this.load('lecdle'), this.load('lfldle')]);
    }

    public async load(game: GameId): Promise<void> {
        const players = await this.api.getPlayers(game, true);
        this.byGame.set(game, players);

        const idx = new Map<string, PlayerListItem>();
        for (const p of players) {
            const name = normalize(p.playerName);
            idx.set(name, p);
        }
        this.indexedByGame.set(game, idx);
    }

    public search(game: GameId, query: string, limit: number): PlayerListItem[] {
        const q = normalize(query);
        if (!q) {
            return this.getAll(game).slice(0, limit);
        }

        const all = this.getAll(game);
        return all
            .map((p) => ({p, s: score(normalize(p.playerName), q)}))
            .filter((x) => x.s > 0)
            .sort((a, b) => b.s - a.s)
            .slice(0, limit)
            .map((x) => x.p);
    }

    public getById(game: GameId, id: number): PlayerListItem | null {
        const all = this.getAll(game);
        return all.find((p) => p.id === id) ?? null;
    }

    public getAll(game: GameId): PlayerListItem[] {
        return this.byGame.get(game) ?? [];
    }
}

function normalize(v: string): string {
    return v
        .toLowerCase()
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .replace(/[^a-z0-9]+/g, ' ')
        .trim();
}

function score(candidate: string, query: string): number {
    if (candidate === query) {
        return 1000;
    }

    if (candidate.startsWith(query)) {
        return 500;
    }

    if (candidate.includes(` ${query}`)) {
        return 300;
    }

    if (candidate.includes(query)) {
        return 200;
    }

    return 0;
}
