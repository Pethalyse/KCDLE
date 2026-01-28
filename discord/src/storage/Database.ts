import Database from 'better-sqlite3';
import fs from 'node:fs';
import path from 'node:path';

export class BotDatabase {
    private readonly db: Database.Database;

    public constructor(sqlitePath: string) {
        const abs = path.isAbsolute(sqlitePath) ? sqlitePath : path.join(process.cwd(), sqlitePath);
        fs.mkdirSync(path.dirname(abs), { recursive: true });
        this.db = new Database(abs);
        this.db.pragma('journal_mode = WAL');
        this.db.pragma('foreign_keys = ON');
    }

    public get connection(): Database.Database {
        return this.db;
    }

    public close(): void {
        this.db.close();
    }
}
