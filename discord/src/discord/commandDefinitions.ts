import {
    ApplicationCommandOptionType,
    PermissionFlagsBits,
    type RESTPostAPIChatInputApplicationCommandsJSONBody,
} from 'discord.js';

const gameChoices = [
    { name: 'KCDLE', value: 'kcdle' },
    { name: 'LECDLE', value: 'lecdle' },
    { name: 'LFLDLE', value: 'lfldle' },
];

export const commandDefinitions: RESTPostAPIChatInputApplicationCommandsJSONBody[] = [
    {
        name: 'init',
        description: "Initialiser le bot dans ce salon (les annonces de victoire seront envoyées ici)",
        default_member_permissions: PermissionFlagsBits.Administrator.toString(),
    },
    {
        name: 'link',
        description: 'Lier ton compte KCDLE à ton Discord (donne le lien vers le site)',
    },
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
                autocomplete: true,
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
