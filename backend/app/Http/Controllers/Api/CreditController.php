<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class CreditController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'contributors' => [
                [
                    'name'        => 'Yanhis',
                    'slug'        => 'yanhis',
                    'twitter'     => 'Pethalyse',
                    'role'        => 'Créateur & développeur principal',
                    'description' => "Développement backend et frontend, architecture générale du site et refonte Vue.js.",
                    'avatar'      => asset('storage/credits/yanhis-rond.png'),
                ],
                [
                    'name'        => 'Lucky',
                    'slug'        => 'lucky',
                    'twitter'     => 'Lucky30__',
                    'role'        => 'Communication & retours joueurs',
                    'description' => "Communication autour du KCDLE et remontée des retours des joueurs (bugs, améliorations possibles).",
                    'avatar'      => asset('storage/credits/lucky-rond.png'),
                ],
                [
                    'name'        => 'Angel',
                    'slug'        => 'angel',
                    'twitter'     => 'angel_mln',
                    'role'        => 'Visuels & collecte de données',
                    'description' => "Création des images et des visuels du site, aide à la récupération et à la vérification de nombreuses données utilisées dans le KCDLE.",
                    'avatar'      => asset('storage/credits/angel-rond.png'),
                ],
                [
                    'name'        => 'Bentho',
                    'slug'        => 'bentho',
                    'twitter'     => 'ben_thoo',
                    'role'        => 'Collecte de données',
                    'description' => "Contribution majeure à la récupération et à la validation des données (joueurs, équipes, historiques…) utilisées par le site.",
                    'avatar'      => asset('storage/credits/bentho-rond.png'),
                ],
            ],
            'technologies' => [
                'PHP 8 – API backend structurée',
                'Framework Laravel pour exposition de l’API',
                'Vue.js 3 en frontend',
                'Vite pour le bundling et dev server',
                'TypeScript pour plus de robustesse côté front',
                'Docker & Docker Compose pour l’environnement complet',
                'PostgreSQL comme base de données',
                'Reverse proxy Nginx en production',
            ],

            'disclaimers' => [
                "Le KCDLE est un projet non officiel réalisé par des fans de la Karmine Corp.",
                "Ce site n’est en aucun cas affilié, sponsorisé ou validé par la Karmine Corp.",
                "Ce site n’est affilié à aucune des structures esport mentionnées (LFL, LEC, équipes, organisations…).",
                "League of Legends, Riot Games et tous les éléments associés sont des marques déposées de Riot Games, Inc.",
                "Les logos, images, noms de joueurs et d’équipes sont la propriété de leurs détenteurs respectifs.",
                "Les données (joueurs, équipes, statistiques…) sont fournies à titre indicatif et peuvent comporter des erreurs.",
                "Ce projet est gratuit et créé dans un but purement communautaire.",
            ],
        ]);
    }
}
