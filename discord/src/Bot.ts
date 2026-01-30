import {
    ActionRowBuilder,
    ButtonBuilder,
    ButtonStyle,
    Client,
    Events,
    GatewayIntentBits,
    MessageFlags,
    PermissionFlagsBits,
    type AutocompleteInteraction,
    type ChatInputCommandInteraction,
    type Interaction,
} from 'discord.js';
import { Buffer } from 'buffer';
import { env } from './config/env.js';
import { BotDatabase } from './storage/Database.js';
import { migrate } from './storage/migrations.js';
import { KcdleApiClient } from './services/KcdleApiClient.js';
import { type GameId } from './services/GameSessionRepository.js';
import { PlayerCache } from './services/PlayerCache.js';
import { GameSessionService } from './services/GameSessionService.js';
import { GuessRenderer } from './services/GuessRenderer.js';
import { GuildConfigRepository } from './services/GuildConfigRepository.js';
import { createServer, type IncomingMessage, type ServerResponse } from 'node:http';

type AutocompleteChoice = {
    name: string;
    value: string;
};

export class Bot {
    private readonly client: Client;
    private readonly db: BotDatabase;
    private readonly api: KcdleApiClient;
    private readonly players: PlayerCache;
    private readonly sessions: GameSessionService;
    private readonly renderer: GuessRenderer;
    private readonly guildConfigs: GuildConfigRepository;
    private internalServerStarted: boolean;

    public constructor() {
        this.client = new Client({ intents: [GatewayIntentBits.Guilds] });

        this.db = new BotDatabase(env.BOT_SQLITE_PATH);
        migrate(this.db.connection);

        this.api = new KcdleApiClient(env.KCDLE_API_BASE_URL, env.KCDLE_DISCORD_BOT_SECRET);
        this.guildConfigs = new GuildConfigRepository(this.db.connection);

        this.internalServerStarted = false;

        this.players = new PlayerCache(this.api);
        this.sessions = new GameSessionService(this.api, this.players);
        this.renderer = new GuessRenderer();

        this.client.on(Events.ClientReady, () => {
            console.log(`Logged in as ${this.client.user?.tag ?? 'unknown'}`);
        });

        this.client.on(Events.InteractionCreate, (i) => this.onInteraction(i));
    }

    public async start(): Promise<void> {
        await this.players.warmup();
        await this.client.login(env.DISCORD_BOT_TOKEN);

        this.startInternalServer();
    }

    private async onInteraction(interaction: Interaction): Promise<void> {
        try {
            if (interaction.isAutocomplete()) {
                await this.handleAutocomplete(interaction);
                return;
            }

            if (interaction.isChatInputCommand()) {
                await this.handleCommand(interaction);
            }
        } catch (err) {
            console.error(err);

            if (interaction.isRepliable()) {
                const content = 'Une erreur est survenue.';

                if (interaction.deferred || interaction.replied) {
                    await interaction.followUp({ content, flags: MessageFlags.Ephemeral }).catch(() => undefined);
                } else {
                    await interaction.reply({ content, flags: MessageFlags.Ephemeral }).catch(() => undefined);
                }
            }
        }
    }

    private async handleAutocomplete(interaction: AutocompleteInteraction): Promise<void> {
        if (interaction.commandName !== 'guess') {
            return;
        }

        if (!interaction.guildId) {
            await interaction.respond([]).catch(() => undefined);
            return;
        }

        const focused = interaction.options.getFocused(true);

        if (focused.name === 'game') {
            const query = String(focused.value ?? '');
            const items = await this.buildGameAutocompleteChoices(interaction.user.id, interaction.guildId, interaction.channelId, query);
            await interaction.respond(items).catch(() => undefined);
            return;
        }

        if (focused.name !== 'player') {
            return;
        }

        const gameRaw = interaction.options.getString('game');
        if (!gameRaw || !isGameId(gameRaw)) {
            await interaction.respond([]).catch(() => undefined);
            return;
        }

        const run = await this.sessions.getTodayRun(interaction.user.id, interaction.guildId, interaction.channelId, gameRaw);
        const alreadyGuessed = new Set<number>((run.guesses ?? []).map((g) => g.playerId));

        const items = this.players
            .search(gameRaw, String(focused.value ?? ''), 50)
            .filter((p) => !alreadyGuessed.has(p.id))
            .slice(0, 20);

        await interaction
            .respond(
                items.map((p) => ({
                    name: p.playerName,
                    value: String(p.id),
                }))
            )
            .catch(() => undefined);
    }

    private async handleCommand(interaction: ChatInputCommandInteraction): Promise<void> {
        const guildId = interaction.guildId;
        const channelId = interaction.channelId;
        const discordUserId = interaction.user.id;

        if (!guildId) {
            await interaction.reply({ content: 'Ce bot ne fonctionne que dans un serveur.', flags: MessageFlags.Ephemeral });
            return;
        }

        if (interaction.commandName === 'init') {
            if (!interaction.memberPermissions?.has(PermissionFlagsBits.Administrator)) {
                await interaction.reply({
                    content: 'Commande réservée aux administrateurs du serveur.',
                    flags: MessageFlags.Ephemeral,
                });
                return;
            }

            this.guildConfigs.setDefaultChannelId(guildId, channelId);
            await interaction.reply({
                content: 'Les annonces de victoire seront envoyées dans ce salon.',
                flags: MessageFlags.Ephemeral,
            });
            return;
        }

        if (interaction.commandName === 'link') {
            const site = this.getPublicWebsiteBaseUrl();
            const linkUrl = `${site.replace(/\/$/, '')}/discord/link?return_to=%2Fprofile`;

            const row = new ActionRowBuilder<ButtonBuilder>().addComponents(
                new ButtonBuilder().setLabel('Lier mon compte').setStyle(ButtonStyle.Link).setURL(linkUrl)
            );

            await interaction.reply({
                content:
                    "Clique sur le bouton pour lier ton compte Discord au site. Si tu n'es pas connecté, tu seras redirigé vers la connexion/inscription puis la liaison se fera automatiquement.",
                components: [row],
                flags: MessageFlags.Ephemeral,
            });
            return;
        }

        if (interaction.commandName === 'play') {
            const game = interaction.options.getString('game', true) as GameId;
            const run = await this.sessions.getTodayRun(discordUserId, guildId, channelId, game);

            if (run.guesses.length === 0) {
                const board = this.renderer.renderBoard(run, { includeHistory: false });
                await interaction.reply({ content: board.content, flags: MessageFlags.Ephemeral });
                return;
            }

            for (let i = 0; i < run.guesses.length; i++) {
                const partialRun = {
                    ...run,
                    guesses: run.guesses.slice(0, i + 1),
                    solvedAt: this.computeSolvedAtForIndex(run, i),
                };

                const board = this.renderer.renderBoard(partialRun, { includeHistory: false });
                const payload = await this.prepareEmbeds(board.embeds);

                if (i === 0) {
                    await interaction.reply({
                        content: board.content,
                        embeds: payload.embeds,
                        files: payload.files,
                        flags: MessageFlags.Ephemeral,
                    });
                } else {
                    await interaction.followUp({
                        content: board.content,
                        embeds: payload.embeds,
                        files: payload.files,
                        flags: MessageFlags.Ephemeral,
                    });
                }
            }

            return;
        }

        if (interaction.commandName === 'guess') {
            const gameRaw = interaction.options.getString('game', true);
            if (!isGameId(gameRaw)) {
                await interaction.reply({ content: 'Game invalide.', flags: MessageFlags.Ephemeral });
                return;
            }

            const game = gameRaw;
            const playerRaw = interaction.options.getString('player', true);
            const playerId = Number.parseInt(playerRaw, 10);

            if (!Number.isFinite(playerId) || playerId <= 0) {
                await interaction.reply({ content: 'Player invalide.', flags: MessageFlags.Ephemeral });
                return;
            }

            const result = await this.sessions.submitGuess(discordUserId, guildId, channelId, game, playerId);

            if (result.alreadySolved) {
                await interaction.reply({
                    content: result.message ?? "Déjà terminé pour aujourd'hui.",
                    flags: MessageFlags.Ephemeral,
                });
                return;
            }

            if (result.message) {
                await interaction.reply({ content: result.message, flags: MessageFlags.Ephemeral });
                return;
            }

            const board = this.renderer.renderBoard(result.run, { includeHistory: false });
            const payload = await this.prepareEmbeds(board.embeds);

            await interaction.reply({
                content: board.content,
                embeds: payload.embeds,
                files: payload.files,
                flags: MessageFlags.Ephemeral,
            });

            if (result.correct) {
                const publicMsg = this.buildPublicSolvedMessage(interaction.user.id, result.run);

                const targetChannelId = this.guildConfigs.getDefaultChannelId(guildId) ?? channelId;

                const ch = await this.fetchSendableChannel(interaction, targetChannelId, channelId);
                if (ch) {
                    await ch.send({ content: publicMsg }).catch(() => undefined);
                }
            }

            return;
        }

        await interaction.reply({ content: 'Commande inconnue.', flags: MessageFlags.Ephemeral });
    }

    private async fetchSendableChannel(
        interaction: ChatInputCommandInteraction,
        preferredChannelId: string,
        fallbackChannelId: string
    ): Promise<{ send: (options: { content: string }) => Promise<unknown> } | null> {
        const preferred = await interaction.client.channels.fetch(preferredChannelId).catch(() => null);
        if (preferred && preferred.isSendable()) {
            return preferred;
        }

        if (preferredChannelId === fallbackChannelId) {
            return null;
        }

        const fallback = await interaction.client.channels.fetch(fallbackChannelId).catch(() => null);
        if (fallback && fallback.isSendable()) {
            return fallback;
        }

        return null;
    }

    private async buildGameAutocompleteChoices(
        discordUserId: string,
        guildId: string,
        channelId: string,
        query: string
    ): Promise<AutocompleteChoice[]> {
        const q = query.trim().toLowerCase();

        const all: Array<{ name: string; value: GameId }> = [
            { name: 'KCDLE', value: 'kcdle' },
            { name: 'LECDLE', value: 'lecdle' },
            { name: 'LFLDLE', value: 'lfldle' },
        ];

        const candidates = all.filter((g) => {
            if (!q.length) {
                return true;
            }

            return g.name.toLowerCase().includes(q) || g.value.toLowerCase().includes(q);
        });

        const resolved = await Promise.all(
            candidates.map(async (g) => {
                try {
                    const today = await this.api.getDiscordTodayRun(g.value, discordUserId);
                    if (today.solved) {
                        return null;
                    }
                } catch {
                }

                return {
                    name: g.name,
                    value: g.value,
                } as AutocompleteChoice;
            })
        );

        return resolved.filter((x): x is AutocompleteChoice => x !== null).slice(0, 20);
    }

    private async prepareEmbeds(embeds: any[]): Promise<{ embeds: any[]; files?: any[] }> {
        const cloned = embeds.map((e) => ({
            ...e,
            fields: Array.isArray(e.fields) ? e.fields.map((f: any) => ({ ...f })) : undefined,
            thumbnail: e.thumbnail ? { ...e.thumbnail } : undefined,
        }));

        const target = cloned.find((e) => e?.thumbnail?.url);
        const thumbUrl = target?.thumbnail?.url ? String(target.thumbnail.url) : '';

        if (!thumbUrl || !this.shouldAttachThumbnail(thumbUrl)) {
            return { embeds: cloned };
        }

        try {
            const res = await fetch(thumbUrl);
            if (!res.ok) {
                return { embeds: cloned };
            }

            const buf = Buffer.from(await res.arrayBuffer());
            const name = 'player.png';

            if (target?.thumbnail) {
                target.thumbnail.url = `attachment://${name}`;
            }

            return { embeds: cloned, files: [{ attachment: buf, name }] };
        } catch {
            return { embeds: cloned };
        }
    }

    private shouldAttachThumbnail(url: string): boolean {
        const u = String(url).trim();
        if (!u) {
            return false;
        }

        if (u.startsWith('attachment://')) {
            return false;
        }

        if (u.startsWith('http://')) {
            return true;
        }

        if (u.startsWith('https://localhost') || u.startsWith('https://127.') || u.startsWith('https://0.0.0.0')) {
            return true;
        }

        return u.startsWith('http://localhost') || u.startsWith('http://127.') || u.startsWith('http://0.0.0.0');
    }

    private computeSolvedAtForIndex(run: { guesses: { correct: boolean }[]; solvedAt: string | null }, index: number): string | null {
        const slice = run.guesses.slice(0, index + 1);
        const solved = slice.some((g) => Boolean(g.correct));
        return solved ? run.solvedAt : null;
    }

    private buildPublicSolvedMessage(discordUserId: string, run: { game: GameId; guesses: unknown[] }): string {
        const mention = `<@${discordUserId}>`;
        const gameLabel = run.game === 'kcdle' ? 'KCDLE' : run.game === 'lecdle' ? 'LECDLE' : 'LFLDLE';
        const count = Array.isArray(run.guesses) ? run.guesses.length : 0;

        return `${mention} a trouvé le ${gameLabel} en ${count} guess${count > 1 ? 'es' : ''} !`;
    }

    private startInternalServer(): void {
        if (this.internalServerStarted) {
            return;
        }

        this.internalServerStarted = true;

        const server = createServer(async (req, res) => {
            await this.handleInternalRequest(req, res).catch(() => {
                try {
                    res.statusCode = 500;
                    res.end();
                } catch {
                }
            });
        });

        server.listen(env.BOT_INTERNAL_PORT, '0.0.0.0', () => {
            console.log(`Internal server listening on 0.0.0.0:${env.BOT_INTERNAL_PORT}`);
        });
    }

    private async handleInternalRequest(req: IncomingMessage, res: ServerResponse): Promise<void> {
        const url = String(req.url ?? '');

        if (req.method !== 'POST' || url !== '/internal/announce-solved') {
            res.statusCode = 404;
            res.end();
            return;
        }

        const secret = String(req.headers['x-kcdle-bot-secret'] ?? '').trim();
        if (!secret || secret !== env.KCDLE_DISCORD_BOT_SECRET) {
            res.statusCode = 401;
            res.end();
            return;
        }

        const body = await this.readJsonBody(req);
        const discordId = typeof body?.discord_id === 'string' ? body.discord_id.trim() : '';
        const game = typeof body?.game === 'string' ? body.game.trim() : '';
        const guessesCount = Number.parseInt(String(body?.guesses_count ?? ''), 10);

        if (!discordId || !isGameId(game) || !Number.isFinite(guessesCount) || guessesCount <= 0) {
            res.statusCode = 422;
            res.end();
            return;
        }

        const message = this.buildPublicSolvedMessage(discordId, { game, guesses: new Array(guessesCount) });

        const targets = this.guildConfigs.listDefaultChannels();
        await Promise.all(
            targets.map(async (t) => {
                const ch = await this.client.channels.fetch(t.channelId).catch(() => null);
                if (!ch || !ch.isSendable()) {
                    return;
                }

                await ch.send({ content: message }).catch(() => undefined);
            })
        );

        res.statusCode = 204;
        res.end();
    }

    private async readJsonBody(req: IncomingMessage): Promise<any> {
        const chunks: Buffer[] = [];

        return await new Promise((resolve) => {
            req.on('data', (chunk) => {
                try {
                    chunks.push(Buffer.isBuffer(chunk) ? chunk : Buffer.from(chunk));
                } catch {
                }
            });

            req.on('end', () => {
                const raw = Buffer.concat(chunks).toString('utf8').trim();
                if (!raw.length) {
                    resolve(null);
                    return;
                }

                try {
                    resolve(JSON.parse(raw));
                } catch {
                    resolve(null);
                }
            });

            req.on('error', () => resolve(null));
        });
    }

    private getPublicWebsiteBaseUrl(): string {
        try {
            const url = new URL(env.KCDLE_SITE_BASE_URL);
            return url.origin;
        } catch {
            return String(env.KCDLE_SITE_BASE_URL).replace(/\/?api\/?$/, '').replace(/\/$/, '');
        }
    }
}

function isGameId(value: string): value is GameId {
    return value === 'kcdle' || value === 'lecdle' || value === 'lfldle';
}
