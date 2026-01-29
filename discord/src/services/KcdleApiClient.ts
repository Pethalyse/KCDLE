import { GameId } from './GameSessionRepository.js';

export type DailyGameInfo = {
    id: number;
    game: GameId;
    selected_for_date: string;
};

export type PlayerListItem = {
    id: number;
    playerName: string;

    imageUrl: string | null;

    countryCode: string | null;
    countryName: string | null;
    countryFlagUrl: string | null;

    birthdate: string | null;

    roleLabel: string | null;
    roleCode: string | null;

    gameName: string | null;
    gameLogoUrl: string | null;

    firstOfficialYear: number | null;
    trophies: number | null;

    previousTeamName: string | null;
    previousTeamLogoUrl: string | null;

    currentTeamName: string | null;
    currentTeamLogoUrl: string | null;

    teamName: string | null;
    teamLogoUrl: string | null;

    lolRole: string | null;
    lolRoleUrl: string | null;
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

export type DiscordTodayRun = {
    game: GameId;
    selected_for_date: string;
    solved: boolean;
    guesses: number;
    history: Array<{
        guess_order: number;
        player_id: number;
        player_name: string;
        correct: boolean;
        fields: Record<string, number | null>;
    }>;
};

export class KcdleApiClient {
    private readonly baseUrl: string;
    private readonly botSecret: string;
    private readonly assetBaseUrl: string;

    public constructor(baseUrl: string, botSecret: string) {
        this.baseUrl = baseUrl.replace(/\/$/, '');
        this.botSecret = botSecret;
        this.assetBaseUrl = this.computeAssetBaseUrl(this.baseUrl);
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
            .map((p: any) => this.mapPlayer(p))
            .filter((p: PlayerListItem | null): p is PlayerListItem => !!p);
    }

    public async submitBotGuess(
        game: GameId,
        discordId: string,
        playerId: number,
        guessOrder: number
    ): Promise<{ status: number; body: any }> {
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

    public async getDiscordTodayRun(game: GameId, discordId: string): Promise<DiscordTodayRun> {
        const url = `${this.baseUrl}/discord/bot/games/${game}/today?discord_id=${encodeURIComponent(discordId)}`;

        const res = await fetch(url, {
            method: 'GET',
            headers: {
                'X-Discord-Bot-Secret': this.botSecret,
            },
        });

        if (!res.ok) {
            const text = await safeText(res);
            throw new Error(`KCDLE discord today failed (${res.status}): ${text}`);
        }

        const json = (await safeJson(res)) as any;

        const selectedForDate = String(json?.selected_for_date ?? json?.selectedForDate ?? '').trim();
        const solved = Boolean(json?.solved ?? false);
        const guesses = Number(json?.guesses ?? 0);

        const historyRaw = Array.isArray(json?.history) ? json.history : [];
        const history = historyRaw
            .map((g: any) => {
                const guessOrder = Number(g?.guess_order ?? g?.guessOrder ?? 0);
                const playerId = Number(g?.player_id ?? g?.playerId ?? 0);
                const playerName = String(g?.player_name ?? g?.playerName ?? '').trim();
                const correct = Boolean(g?.correct ?? false);
                const fields = (g?.fields ?? {}) as Record<string, number | null>;

                if (!Number.isFinite(guessOrder) || guessOrder <= 0) {
                    return null;
                }

                if (!Number.isFinite(playerId) || playerId <= 0) {
                    return null;
                }

                return {
                    guess_order: Math.trunc(guessOrder),
                    player_id: Math.trunc(playerId),
                    player_name: playerName || `#${playerId}`,
                    correct,
                    fields,
                };
            })
            .filter((x: any) => x !== null)
            .sort((a: any, b: any) => a.guess_order - b.guess_order);

        return {
            game,
            selected_for_date: selectedForDate,
            solved,
            guesses: Number.isFinite(guesses) ? Math.trunc(guesses) : history.length,
            history,
        };
    }

    private mapPlayer(p: any): PlayerListItem | null {
        const wrapperId = toInt(p?.id);
        if (!wrapperId) {
            return null;
        }

        const playerNode = p?.player ?? p?.player_data ?? p?.playerData ?? null;
        if (!playerNode) {
            return null;
        }

        const countryNode = playerNode?.country ?? p?.country ?? null;
        const roleNode = playerNode?.role ?? p?.role ?? null;
        const wrapperGame = p?.game ?? playerNode?.game ?? null;

        const currentTeam = p?.current_team ?? p?.currentTeam ?? null;
        const previousTeam = p?.previous_team ?? p?.previousTeam ?? null;
        const lolTeam = p?.team ?? playerNode?.team ?? null;

        const nameRaw =
            playerNode?.display_name ?? playerNode?.displayName ?? playerNode?.name ?? playerNode?.pseudo ?? playerNode?.slug ?? p?.playerName;
        const playerName = String(nameRaw ?? '').trim();
        if (!playerName) {
            return null;
        }

        const birthdateRaw = playerNode?.birthdate ?? playerNode?.birth_date ?? playerNode?.birthday ?? null;
        const birthdate = birthdateRaw ? String(birthdateRaw).slice(0, 10) : null;

        const imageRaw =
            playerNode?.image_url ??
            playerNode?.imageUrl ??
            playerNode?.image ??
            playerNode?.picture_url ??
            playerNode?.pictureUrl ??
            playerNode?.avatar_url ??
            playerNode?.avatarUrl ??
            null;

        const imageUrl = this.toAbsoluteUrl(imageRaw);

        const countryCodeRaw = playerNode?.country_code ?? playerNode?.countryCode ?? null;
        const countryCode = countryCodeRaw ? String(countryCodeRaw) : null;

        const countryNameRaw = countryNode?.name ?? countryNode?.label ?? null;
        const countryName = countryNameRaw ? String(countryNameRaw) : null;

        const countryFlagUrl = this.toAbsoluteUrl(countryNode?.flag_url ?? countryNode?.flagUrl ?? null);

        const roleLabelRaw = roleNode?.label ?? roleNode?.name ?? null;
        const roleLabel = roleLabelRaw ? String(roleLabelRaw) : null;
        const roleCodeRaw = roleNode?.code ?? null;
        const roleCode = roleCodeRaw ? String(roleCodeRaw) : null;

        const gameNameRaw = wrapperGame?.name ?? wrapperGame?.label ?? null;
        const gameName = gameNameRaw ? String(gameNameRaw) : null;
        const gameLogoUrl = this.toAbsoluteUrl(wrapperGame?.logo_url ?? wrapperGame?.logoUrl ?? null);

        const firstOfficialYear = p?.first_official_year !== undefined && p?.first_official_year !== null ? Number(p.first_official_year) : null;
        const trophies = p?.trophies_count !== undefined && p?.trophies_count !== null ? Number(p.trophies_count) : null;

        const previousTeamNameRaw = previousTeam?.display_name ?? previousTeam?.displayName ?? previousTeam?.name ?? null;
        const previousTeamName = previousTeamNameRaw ? String(previousTeamNameRaw) : null;
        const previousTeamLogoUrl = this.toAbsoluteUrl(previousTeam?.logo_url ?? previousTeam?.logoUrl ?? null);

        const currentTeamNameRaw = currentTeam?.display_name ?? currentTeam?.displayName ?? currentTeam?.name ?? null;
        const currentTeamName = currentTeamNameRaw ? String(currentTeamNameRaw) : null;
        const currentTeamLogoUrl = this.toAbsoluteUrl(currentTeam?.logo_url ?? currentTeam?.logoUrl ?? null);

        const teamNameRaw = lolTeam?.display_name ?? lolTeam?.displayName ?? lolTeam?.name ?? null;
        const teamName = teamNameRaw ? String(teamNameRaw) : null;
        const teamLogoUrl = this.toAbsoluteUrl(lolTeam?.logo_url ?? lolTeam?.logoUrl ?? null);

        const lolRoleRaw = p?.lol_role ?? p?.lolRole ?? null;
        const lolRole = lolRoleRaw ? String(lolRoleRaw) : null;
        const lolRoleUrl = this.toAbsoluteUrl(p?.lol_role_url ?? p?.lolRoleUrl ?? null);

        return {
            id: wrapperId,
            playerName,
            imageUrl,
            countryCode,
            countryName,
            countryFlagUrl,
            birthdate,
            roleLabel,
            roleCode,
            gameName,
            gameLogoUrl,
            firstOfficialYear: Number.isFinite(firstOfficialYear as any) ? firstOfficialYear : null,
            trophies: Number.isFinite(trophies as any) ? trophies : null,
            previousTeamName,
            previousTeamLogoUrl,
            currentTeamName,
            currentTeamLogoUrl,
            teamName,
            teamLogoUrl,
            lolRole,
            lolRoleUrl,
        };
    }

    private computeAssetBaseUrl(baseUrl: string): string {
        const m = /^(https?:\/\/[^\/]+)(\/.*)?$/.exec(baseUrl);
        if (!m) {
            return baseUrl;
        }

        const origin = this.preferHttps(m[1]);
        const path = m[2] ?? '';

        if (/\/api\/?$/.test(path)) {
            return origin;
        }

        return origin;
    }

    private toAbsoluteUrl(value: unknown): string | null {
        if (!value) {
            return null;
        }

        const raw = String(value).trim();
        if (!raw) {
            return null;
        }

        if (/^https?:\/\//i.test(raw)) {
            return this.preferHttps(raw);
        }

        if (raw.startsWith('//')) {
            return `https:${raw}`;
        }

        if (raw.startsWith('/')) {
            return `${this.assetBaseUrl}${raw}`;
        }

        return `${this.assetBaseUrl}/${raw}`;
    }

    private preferHttps(url: string): string {
        const u = String(url);
        if (!u.startsWith('http://')) {
            return u;
        }

        const host = u.replace(/^http:\/\//, '').split('/')[0] ?? '';
        if (host.startsWith('localhost') || host.startsWith('127.') || host.startsWith('0.0.0.0')) {
            return u;
        }

        return `https://${u.substring('http://'.length)}`;
    }
}

function toInt(v: unknown): number | null {
    const n = Number(v);
    if (!Number.isFinite(n) || n <= 0) {
        return null;
    }
    return Math.trunc(n);
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
