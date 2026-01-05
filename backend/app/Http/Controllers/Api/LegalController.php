<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class LegalController extends Controller
{
    /**
     * Return the legal notice ("Mentions légales") content as a static JSON payload.
     *
     * This endpoint exposes a structured, front-end ready representation of the
     * application's legal notice, including:
     * - a global title and last update date,
     * - multiple sections (editor, hosting, intellectual property, personal data,
     *   cookies, responsibility, applicable law, contact),
     * - for each section: a title and an ordered list of paragraphs.
     *
     * The returned content is static and does not depend on authentication,
     * request parameters, or database state.
     *
     * Response JSON structure:
     * - 'title'        => string
     * - 'last_updated' => string (YYYY-MM-DD)
     * - '<section_key>' => array{
     *       title:string,
     *       paragraphs:array<int, string>
     *   }
     *
     * @return JsonResponse JSON response containing the legal notice content.
     */
    public function show(): JsonResponse
    {
        return response()->json([
            'title' => 'Mentions légales',
            'last_updated' => '2026-01-05',

            'editor' => [
                'title' => '1. Éditeur du site',
                'paragraphs' => [
                    "Le site KCDLE est un projet web édité à titre personnel.",
                    "Éditeur : Yanhis Mezence",
                    "Contact : yanhismez@icloud.com",
                    "Conformément à l’article 6 III-2 de la loi pour la Confiance dans l’Économie Numérique (LCEN), l’éditeur non professionnel a communiqué ses informations d’identité à l’hébergeur du site.",
                ],
            ],

            'host' => [
                'title' => '2. Hébergement',
                'paragraphs' => [
                    "Le site KCDLE est hébergé par :",
                    "OVHcloud",
                    "Siège social : 2 rue Kellermann, 59100 Roubaix, France",
                    "Téléphone : +33 9 72 10 10 07",
                    "Site web : https://www.ovhcloud.com",
                ],
            ],

            'service' => [
                'title' => '3. Description du service',
                'paragraphs' => [
                    "KCDLE propose des jeux de devinettes autour de l’esport (dont des modes de jeu quotidiens), ainsi que des fonctionnalités communautaires et compétitives.",
                    "Certaines fonctionnalités nécessitent un compte (profil, statistiques, succès, groupes d’amis, PVP et lobbies privés).",
                    "Le Site peut afficher de la publicité et utiliser un outil de mesure d’audience, sous réserve de votre consentement via le gestionnaire de cookies/traceurs.",
                ],
            ],

            'intellectual_property' => [
                'title' => '4. Propriété intellectuelle',
                'paragraphs' => [
                    "KCDLE est un projet de fans. Il n’est pas affilié, sponsorisé ou validé par la Karmine Corp, Riot Games, ni par aucune structure esport citée sur le site.",
                    "League of Legends, Riot Games et tous les éléments associés sont des marques déposées de Riot Games, Inc.",
                    "Les noms, logos et marques des équipes, ligues et organisations esport restent la propriété exclusive de leurs détenteurs respectifs.",
                    "Les contenus originaux créés pour KCDLE (interface, code, organisation, éléments graphiques originaux, etc.) sont protégés. Toute reproduction ou réutilisation non autorisée est interdite.",
                ],
            ],

            'personal_data' => [
                'title' => '5. Données personnelles',
                'paragraphs' => [
                    "Le traitement des données personnelles liées à l’utilisation du site KCDLE est décrit dans la Politique de confidentialité.",
                    "La Politique de confidentialité est consultable à l’adresse suivante : /confidentialite.",
                    "Pour toute question ou demande relative à vos données personnelles : yanhismez@icloud.com.",
                ],
            ],

            'cookies' => [
                'title' => '6. Cookies et traceurs',
                'paragraphs' => [
                    "Le site utilise des traceurs strictement nécessaires (ex. stockage local pour la session applicative) et peut proposer des traceurs de mesure d’audience et/ou publicitaires.",
                    "Les traceurs non essentiels sont soumis à votre consentement via le gestionnaire de cookies/traceurs, et vous pouvez modifier votre choix à tout moment.",
                ],
            ],

            'responsibility' => [
                'title' => '7. Responsabilité',
                'paragraphs' => [
                    "L’éditeur met tout en œuvre pour assurer l’accès et le bon fonctionnement du Site, mais n’apporte aucune garantie d’absence d’erreurs ou d’interruptions.",
                    "Les informations affichées (joueurs, équipes, compétitions, données de jeu) peuvent évoluer et contenir des imprécisions. Elles sont fournies « en l’état ».",
                    "L’éditeur ne saurait être tenu responsable :",
                    "- d’éventuelles erreurs ou omissions ;",
                    "- d’une indisponibilité temporaire ou prolongée du Site ;",
                    "- de dommages directs ou indirects liés à l’utilisation du Site ;",
                    "- du contenu de sites tiers accessibles via des liens externes.",
                ],
            ],

            'law' => [
                'title' => '8. Droit applicable',
                'paragraphs' => [
                    "Les présentes mentions légales sont régies par le droit français.",
                    "En cas de litige et à défaut d’accord amiable, les tribunaux français seront seuls compétents.",
                ],
            ],

            'contact' => [
                'title' => '9. Contact',
                'paragraphs' => [
                    "Pour toute question concernant le site, son fonctionnement, ou les mentions légales :",
                    "Adresse e-mail : yanhismez@icloud.com",
                ],
            ],
        ]);
    }
}
