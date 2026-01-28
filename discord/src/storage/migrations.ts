import type Database from 'better-sqlite3';

const SCHEMA_VERSION = 1;

export function migrate(db: Database.Database): void {
    db.exec(`
    CREATE TABLE IF NOT EXISTS bot_meta (
      key TEXT PRIMARY KEY,
      value TEXT NOT NULL
    );
  `);

    const row = db.prepare('SELECT value FROM bot_meta WHERE key = ?').get('schema_version') as { value: string } | undefined;
    const current = row ? Number.parseInt(row.value, 10) : 0;

    if (!Number.isFinite(current) || current < 0) {
        throw new Error('Invalid schema_version in bot_meta');
    }

    if (current >= SCHEMA_VERSION) {
        return;
    }

    if (current < 1) {
        db.exec(`
      CREATE TABLE IF NOT EXISTS guild_configs (
        guild_id TEXT PRIMARY KEY,
        default_channel_id TEXT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
      );

      CREATE TABLE IF NOT EXISTS user_daily_runs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        discord_user_id TEXT NOT NULL,
        guild_id TEXT NOT NULL,
        channel_id TEXT NOT NULL,
        game TEXT NOT NULL,
        date TEXT NOT NULL,
        guesses_json TEXT NOT NULL,
        solved_at TEXT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        UNIQUE(discord_user_id, game, date)
      );

      CREATE INDEX IF NOT EXISTS idx_user_daily_runs_lookup
        ON user_daily_runs(discord_user_id, game, date);
    `);
    }

    db.prepare('INSERT OR REPLACE INTO bot_meta(key, value) VALUES(?, ?)').run('schema_version', String(SCHEMA_VERSION));
}
