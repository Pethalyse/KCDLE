import { DailyRun, GameId, GuessRow } from './GameSessionRepository.js';

export class GuessRenderer {
    public renderBoard(run: DailyRun): string {
        const title = `🎮 ${label(run.game)} — ${run.date}`;
        const header = run.guesses.length === 0 ? 'Aucun guess pour l\'instant. Utilise /guess.' : '';
        const lines: string[] = [];

        for (let i = 0; i < run.guesses.length; i++) {
            const row = run.guesses[i];
            lines.push(this.renderRow(run.game, i + 1, row));
        }

        const status = run.solvedAt ? '✅ Trouvé !' : '🕵️ En cours';

        const body = [...(header ? [header] : []), ...lines].join('\n');

        return [title, status, '', '```', body || '—', '```'].join('\n');
    }

    public renderRow(game: GameId, order: number, row: GuessRow): string {
        const icons = this.renderFieldIcons(game, row.fields);
        const name = truncate(row.playerName, 18);
        return `${String(order).padStart(2, '0')}. ${name.padEnd(18, ' ')} | ${icons}`;
    }

    public renderFieldIcons(game: GameId, fields: Record<string, number | null>): string {
        const schema = fieldSchema(game);
        return schema
            .map((f) => {
                const v = fields[f.key];
                return `${f.icon}${formatHint(v, f.mode)}`;
            })
            .join(' ');
    }
}

type FieldMode = 'eq' | 'cmp';

type FieldDef = {
    key: string;
    icon: string;
    mode: FieldMode;
};

function fieldSchema(game: GameId): FieldDef[] {
    if (game === 'kcdle') {
        return [
            { key: 'country', icon: '🌍', mode: 'eq' },
            { key: 'birthday', icon: '🎂', mode: 'cmp' },
            { key: 'game', icon: '🎮', mode: 'eq' },
            { key: 'first_official_year', icon: '📅', mode: 'cmp' },
            { key: 'trophies', icon: '🏆', mode: 'cmp' },
            { key: 'previous_team', icon: '⬅️', mode: 'eq' },
            { key: 'current_team', icon: '➡️', mode: 'eq' },
            { key: 'role', icon: '🧩', mode: 'eq' },
        ];
    }

    return [
        { key: 'country', icon: '🌍', mode: 'eq' },
        { key: 'birthday', icon: '🎂', mode: 'cmp' },
        { key: 'team', icon: '🏷️', mode: 'eq' },
        { key: 'lol_role', icon: '🧩', mode: 'eq' },
    ];
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

function truncate(v: string, max: number): string {
    if (v.length <= max) {
        return v;
    }
    return v.slice(0, Math.max(0, max - 1)) + '…';
}
