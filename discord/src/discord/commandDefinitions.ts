import {
    ApplicationCommandOptionType,
    type RESTPostAPIChatInputApplicationCommandsJSONBody,
} from 'discord.js';

const gameChoices = [
    { name: 'KCDLE', value: 'kcdle' },
    { name: 'LECDLE', value: 'lecdle' },
    { name: 'LFLDLE', value: 'lfldle' },
];

export const commandDefinitions: RESTPostAPIChatInputApplicationCommandsJSONBody[] = [
    {
        name: 'play',
        description: 'Jouer au daily (affiche ton tableau en éphémère)',
        options: [
            {
                name: 'game',
                description: 'Quel jeu ?',
                type: ApplicationCommandOptionType.String,
                required: true,
                choices: gameChoices,
            },
        ],
    },
    {
        name: 'guess',
        description: 'Envoyer un guess et mettre à jour ton tableau (éphémère)',
        options: [
            {
                name: 'game',
                description: 'Quel jeu ?',
                type: ApplicationCommandOptionType.String,
                required: true,
                choices: gameChoices,
            },
            {
                name: 'player',
                description: 'Joueur à proposer',
                type: ApplicationCommandOptionType.String,
                required: true,
                autocomplete: true,
            },
        ],
    },
];
