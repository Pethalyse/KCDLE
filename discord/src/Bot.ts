import {
    Client,
    Events,
    GatewayIntentBits,
    MessageFlags,
    type AutocompleteInteraction,
    type ChatInputCommandInteraction,
    type Interaction,
} from 'discord.js';
import { env } from './config/env.js';
import { BotDatabase } from './storage/Database.js';
import { migrate } from './storage/migrations.js';
import { KcdleApiClient } from './services/KcdleApiClient.js';
import { GameSessionRepository, type GameId } from './services/GameSessionRepository.js';
import { PlayerCache } from './services/PlayerCache.js';
import { GameSessionService } from './services/GameSessionService.js';
import { GuessRenderer } from './services/GuessRenderer.js';

export class Bot {
    private readonly client: Client;
    private readonly db: BotDatabase;
    private readonly api: KcdleApiClient;
    private readonly players: PlayerCache;
    private readonly sessions: GameSessionService;
    private readonly renderer: GuessRenderer;

    public constructor() {
        this.client = new Client({ intents: [GatewayIntentBits.Guilds] });

        this.db = new BotDatabase(env.BOT_SQLITE_PATH);
        migrate(this.db.connection);

        this.api = new KcdleApiClient(env.KCDLE_API_BASE_URL, env.KCDLE_DISCORD_BOT_SECRET);
        const repo = new GameSessionRepository(this.db.connection);
        this.players = new PlayerCache(this.api);
        this.sessions = new GameSessionService(this.api, repo, this.players);
        this.renderer = new GuessRenderer();

        this.client.on(Events.ClientReady, () => {
            console.log(`Logged in as ${this.client.user?.tag ?? 'unknown'}`);
        });

        this.client.on(Events.InteractionCreate, (i) => this.onInteraction(i));
    }

    public async start(): Promise<void> {
        await this.players.warmup();
        await this.client.login(env.DISCORD_BOT_TOKEN);
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

        const game = interaction.options.getString('game', true) as GameId;
        const focused = interaction.options.getFocused(true);

        if (focused.name !== 'player') {
            return;
        }

        const items = this.players.search(game, String(focused.value ?? ''), 20);

        await interaction.respond(
            items.map((p) => ({
                name: p.playerName,
                value: String(p.id),
            }))
        );
    }

    private async handleCommand(interaction: ChatInputCommandInteraction): Promise<void> {
        const guildId = interaction.guildId;
        const channelId = interaction.channelId;
        const discordUserId = interaction.user.id;

        if (!guildId) {
            await interaction.reply({ content: 'Ce bot ne fonctionne que dans un serveur.', flags: MessageFlags.Ephemeral });
            return;
        }

        if (interaction.commandName === 'play') {
            const game = interaction.options.getString('game', true) as GameId;
            const run = await this.sessions.getOrCreateRun(discordUserId, guildId, channelId, game);
            const board = this.renderer.renderBoard(run);

            await interaction.reply({ content: board, flags: MessageFlags.Ephemeral });
            return;
        }

        if (interaction.commandName === 'guess') {
            const game = interaction.options.getString('game', true) as GameId;
            const playerRaw = interaction.options.getString('player', true);
            const playerId = Number.parseInt(playerRaw, 10);

            if (!Number.isFinite(playerId) || playerId <= 0) {
                await interaction.reply({ content: 'Player invalide.', flags: MessageFlags.Ephemeral });
                return;
            }

            const result = await this.sessions.submitGuess(discordUserId, guildId, channelId, game, playerId);

            if (result.alreadySolved) {
                const board = this.renderer.renderBoard(result.run);
                await interaction.reply({
                    content: `${result.message ?? "Déjà terminé pour aujourd'hui."}\n\n${board}`,
                    flags: MessageFlags.Ephemeral,
                });
                return;
            }

            if (result.message) {
                await interaction.reply({ content: result.message, flags: MessageFlags.Ephemeral });
                return;
            }

            const board = this.renderer.renderBoard(result.run);

            await interaction.reply({ content: board, flags: MessageFlags.Ephemeral });

            if (result.correct) {
                const publicMsg = this.buildPublicSolvedMessage(interaction.user.id, result.run);

                const ch = await interaction.client.channels.fetch(channelId).catch(() => null);
                if (ch && ch.isSendable()) {
                    await ch.send({ content: publicMsg }).catch(() => undefined);
                }
            }

            return;
        }

        await interaction.reply({ content: 'Commande inconnue.', flags: MessageFlags.Ephemeral });
    }

    private buildPublicSolvedMessage(
        discordUserId: string,
        run: { game: GameId; date: string; guesses: { playerName: string }[] }
    ): string {
        const mention = `<@${discordUserId}>`;
        const gameLabel = run.game === 'kcdle' ? 'KCDLE' : run.game === 'lecdle' ? 'LECDLE' : 'LFLDLE';
        const count = run.guesses.length;
        const guesses = run.guesses.map((g, i) => `${i + 1}. ${g.playerName}`).join('\n');

        return `${mention} a trouvé le ${gameLabel} du ${run.date} en ${count} guess${count > 1 ? 'es' : ''} !\n\n${guesses}`;
    }
}
