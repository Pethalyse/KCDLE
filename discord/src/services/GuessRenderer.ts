import type { APIEmbed, APIEmbedField } from 'discord.js';
import type { DailyRun, GameId, GuessDisplay, GuessRow } from './GameSessionRepository.js';

export type RenderBoardOptions = {
    includeHistory?: boolean;
};

export type RenderedBoard = {
    content: string;
    embeds: APIEmbed[];
};

export class GuessRenderer {
    public renderBoard(run: DailyRun, options: RenderBoardOptions = {}): RenderedBoard {
        const includeHistory = Boolean(options.includeHistory ?? false);

        const status = run.solvedAt ? '✅ Trouvé !' : '🕵️ En cours';
        const header = `🎮 ${label(run.game)}\n${status}\n\nGuess: **${run.guesses.length}**`;

        if (run.guesses.length === 0) {
            return {
                content: `${header}\n\nAucun guess pour l'instant. Utilise /guess.`,
                embeds: [],
            };
        }

        const latest = run.guesses[run.guesses.length - 1];
        const guessEmbed = this.buildLatestEmbed(run, latest);

        const embeds: APIEmbed[] = [guessEmbed];

        if (includeHistory) {
            const historyEmbed = this.buildHistoryEmbed(run);
            if (historyEmbed) {
                embeds.push(historyEmbed);
            }
        }

        return {
            content: header,
            embeds,
        };
    }

    private buildLatestEmbed(run: DailyRun, row: GuessRow): APIEmbed {
        const title = `${row.playerName}${row.correct ? ' ✅' : ''}`;

        const schema = fieldSchema(run.game);
        const display = (row.display ?? null) as GuessDisplay | null;

        const fields: APIEmbedField[] = schema.map((f) => {
            const value = formatValue(display, f.valueKey);
            const hint = formatHint(row.fields?.[f.hintKey], f.mode);

            return {
                name: `${f.icon} ${f.label}`,
                value: `${value} ${hint}`,
                inline: true,
            };
        });

        return {
            title,
            fields,
            thumbnail: display?.playerImageUrl ? { url: display.playerImageUrl } : undefined,
            footer: {
                text: `#${run.guesses.length} • ${label(run.game)}`,
            },
        };
    }

    private buildHistoryEmbed(run: DailyRun): APIEmbed | null {
        if (run.guesses.length <= 1) {
            return null;
        }

        const max = 12;
        const start = Math.max(0, run.guesses.length - max);
        const slice = run.guesses.slice(start);

        const lines = slice.map((g, idx) => {
            const order = start + idx + 1;
            const suffix = g.correct ? ' ✅' : '';
            return `**${order}.** ${escapeMarkdown(g.playerName)}${suffix}`;
        });

        return {
            title: 'Historique',
            description: lines.join('\n'),
        };
    }
}

type FieldMode = 'eq' | 'cmp';

type FieldDef = {
    hintKey: string;
    valueKey: ValueKey;
    label: string;
    icon: string;
    mode: FieldMode;
};

type ValueKey =
    | 'country'
    | 'age'
    | 'game'
    | 'arrival'
    | 'trophies'
    | 'previous'
    | 'current'
    | 'role'
    | 'team'
    | 'lol_role';

function fieldSchema(game: GameId): FieldDef[] {
    if (game === 'kcdle') {
        return [
            { hintKey: 'country', valueKey: 'country', label: 'Nationalité', icon: '🌍', mode: 'eq' },
            { hintKey: 'birthday', valueKey: 'age', label: 'Âge', icon: '🎂', mode: 'cmp' },
            { hintKey: 'game', valueKey: 'game', label: 'Jeu', icon: '🎮', mode: 'eq' },
            { hintKey: 'first_official_year', valueKey: 'arrival', label: 'Arrivée', icon: '📅', mode: 'cmp' },
            { hintKey: 'trophies', valueKey: 'trophies', label: 'Trophées', icon: '🏆', mode: 'cmp' },
            { hintKey: 'previous_team', valueKey: 'previous', label: 'Avant KC', icon: '⬅️', mode: 'eq' },
            { hintKey: 'current_team', valueKey: 'current', label: 'Maintenant', icon: '➡️', mode: 'eq' },
            { hintKey: 'role', valueKey: 'role', label: 'Rôle', icon: '🧩', mode: 'eq' },
        ];
    }

    return [
        { hintKey: 'country', valueKey: 'country', label: 'Nationalité', icon: '🌍', mode: 'eq' },
        { hintKey: 'birthday', valueKey: 'age', label: 'Âge', icon: '🎂', mode: 'cmp' },
        { hintKey: 'team', valueKey: 'team', label: 'Équipe', icon: '🏷️', mode: 'eq' },
        { hintKey: 'lol_role', valueKey: 'lol_role', label: 'Rôle', icon: '🧩', mode: 'eq' },
    ];
}

function formatValue(display: GuessDisplay | null, key: ValueKey): string {
    if (!display) {
        return '—';
    }

    switch (key) {
        case 'country': {
            const v = (display.countryName ?? display.countryCode ?? '').trim();
            return v.length ? escapeMarkdown(v) : '—';
        }
        case 'age': {
            const v = display.age;
            return Number.isFinite(v) ? String(v) : '—';
        }
        case 'game': {
            const v = (display.gameName ?? '').trim();
            return v.length ? escapeMarkdown(v) : '—';
        }
        case 'arrival': {
            const v = display.firstOfficialYear;
            return Number.isFinite(v) ? String(v) : '—';
        }
        case 'trophies': {
            const v = display.trophies;
            return Number.isFinite(v) ? String(v) : '—';
        }
        case 'previous': {
            const v = (display.previousTeamName ?? '').trim();
            return v.length ? escapeMarkdown(v) : '—';
        }
        case 'current': {
            const v = (display.currentTeamName ?? '').trim();
            return v.length ? escapeMarkdown(v) : '—';
        }
        case 'role': {
            const v = (display.roleLabel ?? '').trim();
            return v.length ? escapeMarkdown(v) : '—';
        }
        case 'team': {
            const v = (display.teamName ?? '').trim();
            return v.length ? escapeMarkdown(v) : '—';
        }
        case 'lol_role': {
            const v = (display.lolRole ?? '').trim();
            return v.length ? escapeMarkdown(v) : '—';
        }
    }
}

function formatHint(value: number | null | undefined, mode: FieldMode): string {
    if (value === null || value === undefined) {
        return '❔';
    }

    if (value === 1) {
        return '✅';
    }

    if (mode === 'eq') {
        return '❌';
    }

    if (value === 0) {
        return '⬆️';
    }

    if (value === -1) {
        return '⬇️';
    }

    return '❔';
}

function label(game: GameId): string {
    return game === 'kcdle' ? 'KCDLE' : game === 'lecdle' ? 'LECDLE' : 'LFLDLE';
}

function escapeMarkdown(v: string): string {
    return v.replace(/([\\`*_~|>])/g, '\\$1');
}
