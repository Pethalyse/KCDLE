import { REST, Routes } from 'discord.js';
import { env } from './config/env.js';
import { commandDefinitions } from './discord/commandDefinitions.js';

async function main(): Promise<void> {
    const rest = new REST({ version: '10' }).setToken(env.DISCORD_BOT_TOKEN);

    if (env.DISCORD_DEV_GUILD_ID) {
        await rest.put(Routes.applicationGuildCommands(env.DISCORD_APPLICATION_ID, env.DISCORD_DEV_GUILD_ID), {
            body: commandDefinitions,
        });
        console.log(`Registered commands in guild ${env.DISCORD_DEV_GUILD_ID}`);
        return;
    }

    await rest.put(Routes.applicationCommands(env.DISCORD_APPLICATION_ID), {
        body: commandDefinitions,
    });

    console.log('Registered global commands');
}

main().catch((err) => {
    console.error(err);
    process.exit(1);
});
