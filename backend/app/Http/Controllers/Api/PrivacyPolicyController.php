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
            'last_updated' => '2025-12-04',
            'sections' => [
                [
                    'id' => 'intro',
                    'title' => '1. Introduction',
                    'paragraphs' => [
                        "La présente Politique de confidentialité a pour objectif d’informer les utilisateurs du site KCDLE (ci-après « le Site ») sur la manière dont leurs données personnelles peuvent être collectées, traitées et protégées.",
                        "KCDLE est un projet non officiel, gratuit et sans but lucratif, développé par des fans de la Karmine Corp et de la scène esport. Le Site n’est affilié ni à la Karmine Corp, ni à Riot Games, ni à aucune autre structure esport mentionnée.",
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
                        "Le responsable du traitement est chargé de déterminer les finalités et les moyens des traitements réalisés via le Site, et d’assurer leur conformité aux textes en vigueur.",
                    ],
                ],
                [
                    'id' => 'data-collected',
                    'title' => '3. Données collectées',
                    'paragraphs' => [
                        "Le Site ne nécessite pas la création de compte utilisateur à ce jour. Aucune inscription n’est obligatoire pour jouer à KCDLE.",
                        "Toutefois, certaines données peuvent être collectées automatiquement ou par l’intermédiaire de services tiers (hébergement, publicité, mesures d’audience).",
                    ],
                ],
                [
                    'id' => 'technical-data',
                    'title' => '3.1 Données techniques et logs',
                    'paragraphs' => [
                        "Lors de votre navigation, certaines informations techniques peuvent être automatiquement enregistrées dans les journaux (logs) du serveur, notamment :",
                        "- Adresse IP (pour des raisons de sécurité et de diagnostic technique),",
                        "- Date et heure de la requête,",
                        "- URL consultées,",
                        "- Type de navigateur et système d’exploitation.",
                        "Ces données sont utilisées exclusivement pour assurer la sécurité, la stabilité et le bon fonctionnement du Site (détection d’erreurs, analyse d’éventuelles attaques, etc.). Elles ne sont pas exploitées à des fins commerciales.",
                    ],
                ],
                [
                    'id' => 'cookies',
                    'title' => '3.2 Cookies, stockage local et technologies similaires',
                    'paragraphs' => [
                        "Le Site peut utiliser :",
                        "- des cookies techniques, nécessaires au bon fonctionnement du Site et à la sécurité ;",
                        "- le stockage local (localStorage) du navigateur pour mémoriser vos parties (ex. joueurs déjà devinés) et certaines préférences d’affichage.",
                        "Ces informations sont stockées localement sur votre appareil et ne sont pas transmises au serveur sous forme de données personnelles identifiables.",
                    ],
                ],
                [
                    'id' => 'ads',
                    'title' => '3.3 Publicité et partenaires publicitaires',
                    'paragraphs' => [
                        "Le Site est amené à afficher de la publicité, fournie par un ou plusieurs partenaires publicitaires tiers.",
                        "Ces partenaires peuvent utiliser des cookies ou technologies similaires pour :",
                        "- afficher des annonces,",
                        "- limiter le nombre d’affichages d’une même publicité,",
                        "- mesurer l’efficacité de leurs campagnes,",
                        "- personnaliser les annonces en fonction de votre navigation (profilage publicitaire).",
                        "Conformément au RGPD, la mise en place de publicité personnalisée peut nécessiter votre consentement explicite, via une bannière ou un module de gestion des cookies. Vous pourrez alors accepter ou refuser certaines catégories de cookies (notamment les cookies publicitaires).",
                        "Les conditions détaillées de traitement par ces tiers (ex. Google, réseaux publicitaires) sont précisées dans leurs propres politiques de confidentialité, que nous vous invitons à consulter.",
                    ],
                ],
                [
                    'id' => 'contact-data',
                    'title' => '3.4 Données transmises lors d’un contact',
                    'paragraphs' => [
                        "Si vous nous contactez directement (par exemple par e-mail à l’adresse yanhismez@icloud.com), les données suivantes peuvent être traitées :",
                        "- adresse e-mail,",
                        "- contenu de votre message,",
                        "- toute information que vous choisissez de communiquer.",
                        "Ces données sont utilisées uniquement pour traiter votre demande et vous répondre. Elles ne sont ni revendues, ni utilisées à des fins de prospection commerciale.",
                    ],
                ],
                [
                    'id' => 'purposes',
                    'title' => '4. Finalités des traitements',
                    'paragraphs' => [
                        "Les données collectées via le Site sont utilisées pour les finalités suivantes :",
                        "- assurer le bon fonctionnement technique du Site et du jeu KCDLE ;",
                        "- sécuriser l’infrastructure (prévention des abus, attaques, spam, etc.) ;",
                        "- mémoriser localement vos progrès dans le jeu (via le stockage local du navigateur) ;",
                        "- afficher de la publicité (le cas échéant) et mesurer sa performance ;",
                        "- produire des statistiques globales et anonymisées de fréquentation ;",
                        "- répondre aux demandes de contact que vous nous adressez.",
                        "Aucune donnée n’est utilisée pour créer un profil utilisateur nominatif sur le Site et aucune donnée n’est vendue à des tiers.",
                    ],
                ],
                [
                    'id' => 'legal-basis',
                    'title' => '5. Base légale des traitements',
                    'paragraphs' => [
                        "Les traitements mis en œuvre reposent sur les bases légales suivantes :",
                        "- l’intérêt légitime du responsable du traitement pour assurer la sécurité et le bon fonctionnement du Site ;",
                        "- l’exécution d’un service demandé par l’utilisateur (accès au Site et au jeu KCDLE) ;",
                        "- le consentement de l’utilisateur pour les cookies et traceurs non essentiels, notamment ceux liés à la publicité personnalisée ou à certaines mesures d’audience.",
                    ],
                ],
                [
                    'id' => 'retention',
                    'title' => '6. Durée de conservation des données',
                    'paragraphs' => [
                        "Les données techniques (logs serveur) sont conservées pendant une durée n’excédant pas 90 jours, sauf obligation légale ou nécessité de conservation liée à une enquête de sécurité.",
                        "Les données issues du stockage local (localStorage) restent sur votre appareil tant que vous ne les supprimez pas (par exemple en vidant les données de votre navigateur).",
                        "Les éventuels échanges par e-mail sont conservés pour la durée nécessaire au traitement de votre demande, puis peuvent être archivés pour une durée raisonnable, notamment à des fins de suivi ou de preuve.",
                    ],
                ],
                [
                    'id' => 'recipients',
                    'title' => '7. Destinataires des données',
                    'paragraphs' => [
                        "Les données collectées peuvent être accessibles, dans la limite de leurs attributions :",
                        "- au responsable du traitement (développeur du Site) ;",
                        "- aux prestataires d’hébergement et de maintenance technique du Site ;",
                        "- à nos partenaires publicitaires (uniquement pour les données de navigation pertinentes à leurs services, lorsque vous y avez consenti).",
                        "Les données ne sont en aucun cas revendues à des tiers. Aucun transfert non encadré à des entités non autorisées n’est effectué.",
                    ],
                ],
                [
                    'id' => 'transfers',
                    'title' => '8. Transferts hors Union européenne',
                    'paragraphs' => [
                        "Le Site est hébergé sur des infrastructures pouvant être situées au sein de l’Union européenne.",
                        "Dans le cadre de l’utilisation de services tiers (hébergement, publicité, analyse d’audience), certaines données techniques peuvent être transférées vers des pays situés en dehors de l’Union européenne.",
                        "Dans ce cas, nous nous efforçons de sélectionner des prestataires offrant des garanties appropriées, conformes au RGPD (clauses contractuelles types, décisions d’adéquation, etc.) et/ou des mécanismes de protection supplémentaires.",
                    ],
                ],
                [
                    'id' => 'security',
                    'title' => '9. Sécurité des données',
                    'paragraphs' => [
                        "Nous mettons en œuvre des mesures techniques et organisationnelles raisonnables pour protéger vos données contre la perte, l’accès non autorisé, la divulgation ou la destruction :",
                        "- utilisation du protocole HTTPS ;",
                        "- restriction des accès aux seules personnes autorisées ;",
                        "- surveillance de base des erreurs et des accès anormaux ;",
                        "- mises à jour régulières des composants techniques.",
                        "Toutefois, aucun système n’est parfaitement sécurisé et nous ne pouvons garantir une sécurité absolue des informations transmises via Internet.",
                    ],
                ],
                [
                    'id' => 'rights',
                    'title' => '10. Vos droits',
                    'paragraphs' => [
                        "Conformément au RGPD, vous disposez des droits suivants sur vos données personnelles :",
                        "- droit d’accès : obtenir la confirmation que des données vous concernant sont ou ne sont pas traitées, et en recevoir une copie ;",
                        "- droit de rectification : demander la correction de données inexactes ou incomplètes ;",
                        "- droit à l’effacement : demander la suppression de vos données, dans les limites prévues par la loi ;",
                        "- droit à la limitation du traitement : demander la suspension temporaire du traitement dans certains cas ;",
                        "- droit d’opposition : vous opposer, pour des raisons tenant à votre situation particulière, à certains traitements fondés sur l’intérêt légitime ;",
                        "- droit de déposer une réclamation auprès d’une autorité de contrôle, et notamment de la CNIL (www.cnil.fr) en France.",
                        "Pour exercer vos droits, vous pouvez nous contacter à l’adresse e-mail suivante : yanhismez@icloud.com",
                    ],
                ],
                [
                    'id' => 'minors',
                    'title' => '11. Utilisation par des mineurs',
                    'paragraphs' => [
                        "Le Site est accessible à des utilisateurs mineurs. Aucun contenu sensible n’est proposé, et aucune inscription n’est requise.",
                        "Aucune donnée personnelle typiquement sensible (santé, orientation, etc.) n’est collectée.",
                        "Les parents ou représentants légaux peuvent nous contacter s’ils souhaitent demander des précisions ou l’exercice de droits concernant un mineur.",
                    ],
                ],
                [
                    'id' => 'accounts-future',
                    'title' => '12. Évolutions futures (comptes utilisateurs)',
                    'paragraphs' => [
                        "À ce jour, le Site ne propose pas de création de compte utilisateur.",
                        "Si, à l’avenir, un système de comptes (profil joueur, historique en ligne, etc.) est mis en place, cette Politique de confidentialité sera mise à jour pour détailler les nouvelles catégories de données collectées, leurs finalités, leurs durées de conservation et vos droits associés.",
                        "Nous vous informerons alors de manière claire de toute modification substantielle de cette Politique.",
                    ],
                ],
                [
                    'id' => 'changes',
                    'title' => '13. Modifications de la présente Politique',
                    'paragraphs' => [
                        "La présente Politique de confidentialité peut être amenée à évoluer, notamment en fonction des évolutions légales, techniques ou fonctionnelles du Site.",
                        "En cas de changement important, une information pourra être affichée sur le Site afin de vous permettre d’en prendre connaissance.",
                        "La date de dernière mise à jour est indiquée en haut de cette Politique.",
                    ],
                ],
                [
                    'id' => 'contact',
                    'title' => '14. Contact',
                    'paragraphs' => [
                        "Pour toute question relative à cette Politique de confidentialité, à vos données personnelles ou pour exercer vos droits, vous pouvez nous contacter à l’adresse suivante :",
                        "Adresse e-mail : yanhismez@icloud.com",
                    ],
                ],
            ],
        ]);
    }
}
