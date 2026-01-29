import type Database from 'better-sqlite3';

export class GuildConfigRepository {
    private readonly db: Database.Database;

    public constructor(db: Database.Database) {
        this.db = db;
    }

    public getDefaultChannelId(guildId: string): string | null {
        const row = this.db
            .prepare('SELECT default_channel_id FROM guild_configs WHERE guild_id = ?')
            .get(guildId) as { default_channel_id: string | null } | undefined;

        if (!row) {
            return null;
        }

        const value = row.default_channel_id ? String(row.default_channel_id).trim() : '';
        return value.length ? value : null;
    }

    public setDefaultChannelId(guildId: string, channelId: string | null): void {
        const now = new Date().toISOString();
        const existing = this.db
            .prepare('SELECT guild_id FROM guild_configs WHERE guild_id = ?')
            .get(guildId) as { guild_id: string } | undefined;

        const createdAt = existing ? this.getCreatedAt(guildId) : now;

        this.db
            .prepare(
                `INSERT INTO guild_configs(guild_id, default_channel_id, created_at, updated_at)
                 VALUES(?, ?, ?, ?)
                 ON CONFLICT(guild_id) DO UPDATE SET
                   default_channel_id = excluded.default_channel_id,
                   updated_at = excluded.updated_at`
            )
            .run(guildId, channelId, createdAt, now);
    }

    private getCreatedAt(guildId: string): string {
        const row = this.db
            .prepare('SELECT created_at FROM guild_configs WHERE guild_id = ?')
            .get(guildId) as { created_at: string } | undefined;

        return row?.created_at ? String(row.created_at) : new Date().toISOString();
    }
}
