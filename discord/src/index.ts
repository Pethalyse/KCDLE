import { Bot } from './Bot.js';

const bot = new Bot();

bot.start().catch((err) => {
    console.error(err);
    process.exit(1);
});
