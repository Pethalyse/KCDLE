import 'dotenv/config';
import { z } from 'zod';

const EnvSchema = z.object({
    DISCORD_BOT_TOKEN: z.string().min(1),
    DISCORD_APPLICATION_ID: z.string().min(1),
    DISCORD_DEV_GUILD_ID: z.string().min(1).optional().or(z.literal('')).transform((v) => (v ? v : undefined)),
    KCDLE_API_BASE_URL: z.string().url(),
    KCDLE_SITE_BASE_URL: z.string().url(),
    KCDLE_DISCORD_BOT_SECRET: z.string().min(1),
    BOT_SQLITE_PATH: z.string().min(1).default('./data/bot.sqlite'),
    BOT_LOG_LEVEL: z.string().min(1).default('info'),
    BOT_INTERNAL_PORT: z.coerce.number().int().positive().default(3001),
});

export type Env = z.infer<typeof EnvSchema>;

export const env: Env = EnvSchema.parse({
    DISCORD_BOT_TOKEN: process.env.DISCORD_BOT_TOKEN,
    DISCORD_APPLICATION_ID: process.env.DISCORD_APPLICATION_ID,
    DISCORD_DEV_GUILD_ID: process.env.DISCORD_DEV_GUILD_ID ?? undefined,
    KCDLE_API_BASE_URL: process.env.KCDLE_API_BASE_URL,
    KCDLE_SITE_BASE_URL : process.env.KCDLE_SITE_BASE_URL,
    KCDLE_DISCORD_BOT_SECRET: process.env.KCDLE_DISCORD_BOT_SECRET,
    BOT_SQLITE_PATH: process.env.BOT_SQLITE_PATH,
    BOT_LOG_LEVEL: process.env.BOT_LOG_LEVEL,
    BOT_INTERNAL_PORT: process.env.BOT_INTERNAL_PORT,
});
