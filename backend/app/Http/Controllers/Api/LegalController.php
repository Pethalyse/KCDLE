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
            'last_updated' => '2025-12-04',

            'editor' => [
                'title' => '1. Éditeur du site',
                'paragraphs' => [
                    "Le site KCDLE est un projet personnel, non commercial, développé par un particulier.",
                    "Éditeur du site : Yanhis Mezence",
                    "Courriel : yanhismez@icloud.com",
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

            'intellectual_property' => [
                'title' => '3. Propriété intellectuelle',
                'paragraphs' => [
                    "Le site KCDLE est un projet de fans, sans but lucratif. Il n’est pas affilié, sponsorisé ou validé par la Karmine Corp, Riot Games, ni par aucune structure esport citée sur le site.",
                    "League of Legends, Riot Games et tous les éléments associés sont des marques déposées de Riot Games, Inc.",
                    "Les noms, logos et marques des équipes, ligues et organisations esport restent la propriété exclusive de leurs détenteurs respectifs.",
                    "Les visuels originaux créés pour le KCDLE (interfaces, icônes, assets graphiques spécifiques, etc.) sont protégés par le droit d’auteur. Toute reproduction ou réutilisation sans autorisation est interdite.",
                ],
            ],

            'personal_data' => [
                'title' => '4. Données personnelles',
                'paragraphs' => [
                    "Le traitement des données personnelles liées à l’utilisation du site KCDLE est décrit dans la Politique de confidentialité.",
                    "Cette Politique de confidentialité est consultable à l’adresse suivante : /confidentialite.",
                    "Pour toute question ou demande relative à vos données personnelles, vous pouvez nous contacter à l’adresse : yanhismez@icloud.com.",
                ],
            ],

            'cookies' => [
                'title' => '5. Cookies',
                'paragraphs' => [
                    "Le site peut utiliser des cookies techniques et, le cas échéant, des cookies publicitaires ou de mesure d’audience.",
                    "Les modalités de gestion des cookies et des traceurs sont détaillées dans la Politique de confidentialité, qui précise notamment les finalités et les bases légales des traitements concernés.",
                ],
            ],

            'responsibility' => [
                'title' => '6. Responsabilité',
                'paragraphs' => [
                    "L’éditeur du site met tout en œuvre pour proposer des informations exactes et à jour. Toutefois, aucune garantie n’est apportée quant à l’exhaustivité ou l’exactitude des données (notamment celles concernant les joueurs, équipes, compétitions ou résultats).",
                    "L’éditeur ne saurait être tenu responsable :",
                    "- d’éventuelles erreurs ou omissions dans le contenu du site ;",
                    "- d’une indisponibilité temporaire ou prolongée du site ;",
                    "- de dommages directs ou indirects liés à l’utilisation du site ou des informations qui y sont proposées ;",
                    "- du contenu des sites externes vers lesquels des liens hypertextes peuvent renvoyer.",
                    "Le site KCDLE est fourni à titre gratuit, « en l’état », sans aucune garantie de résultat.",
                ],
            ],

            'law' => [
                'title' => '7. Droit applicable',
                'paragraphs' => [
                    "Les présentes mentions légales sont régies par le droit français.",
                    "En cas de litige et à défaut d’accord amiable, les tribunaux français seront seuls compétents.",
                ],
            ],

            'contact' => [
                'title' => '8. Contact',
                'paragraphs' => [
                    "Pour toute question concernant le site, son fonctionnement ou ses mentions légales, vous pouvez nous écrire à l’adresse suivante :",
                    "Adresse e-mail : yanhismez@icloud.com",
                ],
            ],
        ]);
    }
}
