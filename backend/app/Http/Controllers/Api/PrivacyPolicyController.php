<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PrivacyPolicyController extends Controller
{
    /**
     * Return the privacy policy content as a static JSON payload.
     *
     * This endpoint exposes a structured, front-end ready representation of the
     * application's privacy policy, including:
     * - a global title and last update date,
     * - an ordered list of sections, each containing:
     *   - an identifier (id),
     *   - a display title,
     *   - an ordered list of paragraphs.
     *
     * The returned content is static and does not depend on authentication,
     * request parameters, or database state.
     *
     * Response JSON structure:
     * - 'title'        => string
     * - 'last_updated' => string (YYYY-MM-DD)
     * - 'sections'     => array<int, array{
     *       id:string,
     *       title:string,
     *       paragraphs:array<int, string>
     *   }>
     *
     * @return JsonResponse JSON response containing the privacy policy content.
     */
    public function show(): JsonResponse
    {
        return response()->json([
            'title' => 'Politique de confidentialité',
            'last_updated' => '2026-01-06',
            'sections' => [
                [
                    'id' => 'intro',
                    'title' => '1. Introduction',
                    'paragraphs' => [
                        "La présente Politique de confidentialité a pour objectif d’informer les utilisateurs du site KCDLE (ci-après « le Site ») sur la manière dont leurs données personnelles peuvent être collectées, traitées et protégées.",
                        "KCDLE est un projet non officiel, développé par des fans. Le Site n’est affilié ni à la Karmine Corp, ni à Riot Games, ni à aucune autre structure esport mentionnée.",
                        "Nous accordons une importance particulière à la protection de vos données personnelles et nous veillons à respecter le Règlement (UE) 2016/679 (RGPD) et la loi Informatique et Libertés.",
                    ],
                ],
                [
                    'id' => 'controller',
                    'title' => '2. Responsable du traitement',
                    'paragraphs' => [
                        "Le responsable du traitement des données collectées via le Site est :",
                        "Yanhis Mezence – Développeur du projet KCDLE.",
                        "Adresse e-mail de contact : yanhismez@icloud.com",
                        "Le responsable du traitement détermine les finalités et les moyens des traitements réalisés via le Site, et s’assure de leur conformité aux textes en vigueur.",
                    ],
                ],
                [
                    'id' => 'data-collected',
                    'title' => '3. Données collectées',
                    'paragraphs' => [
                        "Le Site propose désormais la création de compte afin d’accéder à certaines fonctionnalités (profil, statistiques, groupes d’amis, succès, PVP).",
                        "Selon votre usage, les catégories de données suivantes peuvent être traitées : données de compte, données de jeu, données techniques, et données liées aux cookies/traceurs (selon votre consentement).",
                    ],
                ],
                [
                    'id' => 'account-data',
                    'title' => '3.1 Données de compte',
                    'paragraphs' => [
                        "Lors de la création d’un compte, le Site traite les informations suivantes :",
                        "- pseudo (nom d’utilisateur),",
                        "- adresse e-mail,",
                        "- mot de passe (stocké sous forme hachée, jamais en clair),",
                        "- statut de vérification de l’e-mail (date/heure de vérification le cas échéant).",
                        "Pour permettre votre connexion sur l’application, un jeton d’authentification peut être généré côté serveur et stocké localement sur votre appareil (stockage local du navigateur).",
                    ],
                ],
                [
                    'id' => 'game-data',
                    'title' => '3.2 Données de jeu et de profil',
                    'paragraphs' => [
                        "Afin de fournir les fonctionnalités du Site, des données de jeu peuvent être enregistrées en base de données, notamment :",
                        "- résultats de vos parties quotidiennes (ex. victoire, nombre d’essais),",
                        "- historique de guesses associés à une partie (ordre des guesses et identifiants des joueurs),",
                        "- progression et déverrouillage de succès (achievements) avec date/heure de déverrouillage,",
                        "- appartenance à des groupes d’amis (friend groups), rôle dans le groupe (ex. membre / owner),",
                        "- fonctionnalités PVP : participation à une file (queue), matchs PVP, événements de match (actions, progression de round, fin de match, etc.),",
                        "- fonctionnalités de lobby PVP privé : création/rejoindre un lobby, code de lobby, événements liés au lobby, et lien éventuel vers un match PVP.",
                        "Ces données servent à afficher votre profil, vos statistiques, et à assurer le bon déroulement des modes de jeu.",
                    ],
                ],
                [
                    'id' => 'technical-data',
                    'title' => '3.3 Données techniques et logs',
                    'paragraphs' => [
                        "Lors de votre navigation, certaines informations techniques peuvent être automatiquement enregistrées dans les journaux (logs) du serveur, notamment :",
                        "- adresse IP (principalement pour la sécurité et le diagnostic technique),",
                        "- date et heure des requêtes,",
                        "- endpoints consultés,",
                        "- informations techniques liées au navigateur et au système.",
                        "Ces données sont utilisées pour assurer la sécurité, la stabilité et le bon fonctionnement du Site (détection d’erreurs, lutte contre les abus, prévention d’attaques, etc.).",
                    ],
                ],
                [
                    'id' => 'cookies',
                    'title' => '3.4 Cookies, stockage local et technologies similaires',
                    'paragraphs' => [
                        "Le Site utilise des technologies de stockage local et peut utiliser des cookies/traceurs selon vos choix :",
                        "- stockage local pour la session applicative (ex. jeton d’authentification) et certaines données de confort (ex. consentement cookies),",
                        "- stockage local pour mémoriser vos parties quotidiennes et préférences (ex. progression côté navigateur).",
                        "Le Site propose un gestionnaire de consentement vous permettant d’accepter ou de refuser les catégories « mesure d’audience » et « publicités personnalisées ».",
                        "Les traceurs non essentiels (ex. mesure d’audience, personnalisation publicitaire) ne sont chargés qu’après votre choix.",
                    ],
                ],
                [
                    'id' => 'analytics',
                    'title' => '3.5 Mesure d’audience (analytics)',
                    'paragraphs' => [
                        "Le Site peut utiliser un outil de mesure d’audience afin de comprendre la fréquentation et améliorer l’expérience utilisateur.",
                        "Cette mesure d’audience est conditionnée à votre consentement via le gestionnaire de cookies/traceurs. Vous pouvez refuser ou retirer votre consentement à tout moment via l’interface dédiée.",
                    ],
                ],
                [
                    'id' => 'ads',
                    'title' => '3.6 Publicité',
                    'paragraphs' => [
                        "Le Site peut afficher de la publicité afin de contribuer au financement de l’hébergement et des frais techniques.",
                        "Selon le fournisseur publicitaire et la configuration choisie, le Site peut diffuser : (1) des publicités non personnalisées (contextuelles), ou (2) des publicités personnalisées.",
                        "Les publicités non personnalisées peuvent être affichées sans nécessiter l’activation de cookies/traceurs publicitaires.",
                        "La personnalisation de la publicité (utilisation de cookies ou d’identifiants publicitaires) est conditionnée à votre consentement via le gestionnaire de cookies/traceurs (« publicités personnalisées »).",
                        "Nous vous invitons à consulter la politique de confidentialité du fournisseur publicitaire concerné pour connaître le détail de leurs traitements.",
                    ],
                ],
                [
                    'id' => 'purposes',
                    'title' => '4. Finalités des traitements',
                    'paragraphs' => [
                        "Les données traitées via le Site ont pour finalités :",
                        "- créer et gérer votre compte (authentification, vérification e-mail, sécurité),",
                        "- fournir les fonctionnalités de jeu (quotidien, statistiques, succès, groupes, PVP, lobbies),",
                        "- assurer la sécurité et prévenir les abus (anti-spam, anti-fraude, détection d’activités anormales),",
                        "- améliorer le Site via la mesure d’audience (si vous y consentez),",
                        "- diffuser de la publicité non personnalisée (le cas échéant),",
                        "- activer la personnalisation publicitaire (si vous y consentez),",
                        "- répondre à vos demandes si vous nous contactez.",
                    ],
                ],
                [
                    'id' => 'legal-basis',
                    'title' => '5. Base légale des traitements',
                    'paragraphs' => [
                        "Les traitements mis en œuvre reposent sur les bases légales suivantes :",
                        "- exécution du service demandé par l’utilisateur (création de compte, accès aux fonctionnalités),",
                        "- intérêt légitime (sécurité du Site, prévention des abus, stabilité technique, et diffusion de publicités non personnalisées le cas échéant),",
                        "- consentement pour les cookies/traceurs non essentiels (mesure d’audience et personnalisation publicitaire, selon votre choix).",
                    ],
                ],
                [
                    'id' => 'retention',
                    'title' => '6. Durée de conservation',
                    'paragraphs' => [
                        "Données de compte : conservées tant que le compte est actif, sauf suppression demandée ou obligation légale.",
                        "Données de jeu (résultats, guesses, succès, groupes, PVP) : conservées pour fournir l’historique et les statistiques, et supprimées en cas de suppression du compte lorsque cela est techniquement applicable.",
                        "Logs serveur : conservés pour une durée limitée (en principe n’excédant pas 90 jours), sauf nécessité de sécurité spécifique.",
                        "Stockage local (navigateur) : conservé sur votre appareil tant que vous ne le supprimez pas (paramètres du navigateur).",
                    ],
                ],
                [
                    'id' => 'recipients',
                    'title' => '7. Destinataires des données',
                    'paragraphs' => [
                        "Les données peuvent être accessibles, dans la limite de leurs attributions :",
                        "- au responsable du traitement (développeur),",
                        "- aux prestataires d’hébergement et de maintenance technique,",
                        "- aux fournisseurs d’analytics si vous y consentez (données de navigation nécessaires à leurs services),",
                        "- aux fournisseurs de publicité (diffusion de publicités non personnalisées) et, si vous y consentez, personnalisation publicitaire selon le fournisseur.",
                        "Les données ne sont pas revendues.",
                    ],
                ],
                [
                    'id' => 'transfers',
                    'title' => '8. Transferts hors Union européenne',
                    'paragraphs' => [
                        "Certains services tiers (analytics / publicité) peuvent impliquer des transferts de données hors Union européenne.",
                        "Dans ce cas, nous nous efforçons de sélectionner des prestataires offrant des garanties appropriées conformes au RGPD (clauses contractuelles types, décisions d’adéquation, ou mécanismes équivalents).",
                    ],
                ],
                [
                    'id' => 'security',
                    'title' => '9. Sécurité des données',
                    'paragraphs' => [
                        "Nous mettons en œuvre des mesures techniques et organisationnelles raisonnables pour protéger vos données :",
                        "- utilisation du protocole HTTPS,",
                        "- contrôle d’accès aux systèmes,",
                        "- limitation des données strictement nécessaires,",
                        "- mises à jour régulières des composants.",
                        "Aucun système n’étant parfaitement sécurisé, une sécurité absolue ne peut être garantie.",
                    ],
                ],
                [
                    'id' => 'rights',
                    'title' => '10. Vos droits',
                    'paragraphs' => [
                        "Conformément au RGPD, vous disposez notamment des droits suivants : accès, rectification, effacement, limitation, opposition, portabilité (selon le cas).",
                        "Vous pouvez également retirer votre consentement aux cookies/traceurs non essentiels à tout moment via le gestionnaire de cookies.",
                        "Pour exercer vos droits, vous pouvez nous contacter à : yanhismez@icloud.com",
                        "Vous pouvez aussi déposer une réclamation auprès de la CNIL (www.cnil.fr).",
                    ],
                ],
                [
                    'id' => 'minors',
                    'title' => '11. Utilisation par des mineurs',
                    'paragraphs' => [
                        "Le Site est accessible à des utilisateurs mineurs. Aucune donnée personnelle sensible n’est volontairement collectée.",
                        "Les parents ou représentants légaux peuvent nous contacter pour toute demande concernant un mineur.",
                    ],
                ],
                [
                    'id' => 'changes',
                    'title' => '12. Modifications de la présente Politique',
                    'paragraphs' => [
                        "La présente Politique peut évoluer en fonction des changements légaux, techniques ou fonctionnels du Site.",
                        "La date de dernière mise à jour figure en haut de cette page. En cas de modification substantielle, une information pourra être affichée sur le Site.",
                    ],
                ],
                [
                    'id' => 'contact',
                    'title' => '13. Contact',
                    'paragraphs' => [
                        "Pour toute question relative à cette Politique de confidentialité, à vos données personnelles ou pour exercer vos droits :",
                        "Adresse e-mail : yanhismez@icloud.com",
                    ],
                ],
            ],
        ]);
    }
}
