<?php

namespace TheFeed\Controleur;

use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use TheFeed\Modele\Repository\KCDLERepository;
use TheFeed\Modele\Repository\LECRepository;
use TheFeed\Modele\Repository\LFLRepository;
use TheFeed\Modele\Repository\RepositoryMere;

class ControleurDle extends ControleurMere
{
    #[Route(path: ['/', '/home'], name:'afficherHome', methods:["GET"])]
    static function afficherHome(): Response
    {
        return self::afficherVueGeneral("home/home.php");
    }

    #[Route(path: ['/kcdle'], name:'afficherKcdle', methods:["GET"])]
    static function afficherKcdle(): Response
    {
        $allJoueurs = KCDLERepository::getAllJoueurs();
        $joueurResult = KCDLERepository::random();
        $nbReussites = KCDLERepository::getNbReussites();
        $infoBar = [
            ["class" => "nationalite", "text" => "Nationalité", "key" => "Nationalite", "component" => "paysimg"],
            ["class" => "age", "text" => "Âge", "key" => "age", "component" => "arrowimg"],
            ["class" => "jeu", "text" => "Jeu", "key" => "Jeu", "component" => "jeuimg"],
            ["class" => "arrivee", "text" => "Arrivée", "key" => "Annee", "component" => "arrowimg"],
            ["class" => "titres", "text" => "Titre(s)", "key" => "Titres", "component" => "arrowimg"],
            ["class" => "avantkc", "text" => "AvantKC", "key" => "TeamAvant", "component" => "teamimg"],
            ["class" => "maintenant", "text" => "Maintenant", "key" => "TeamMaintenant", "component" => "teamimg"],
            ["class" => "role", "text" => "Rôle", "key" => "Role", "component" => "roletext"],
            ["class" => "joueur", "text" => "Joueur", "key" => "Image", "component" => "joueurimg"],
        ];
        return self::afficherVueGeneral("dle/dle.php",
            [
                "dle" => "KCDLE",
                "nbReussites" => $nbReussites,
                "allJoueurs" => $allJoueurs,
                "infoBar" => $infoBar,
                "joueurResult" => $joueurResult,
            ]);
    }

    static function LecAndLflInfoBar(): array
    {
        return [
            ["class" => "nationalite", "text" => "Nationalité", "key" => "Nationalite", "component" => "paysimg"],
            ["class" => "age", "text" => "Âge", "key" => "age", "component" => "arrowimg"],
            ["class" => "role", "text" => "Rôle", "key" => "Role", "component" => "roleimg"],
            ["class" => "equipe", "text" => "Équipe", "key" => "Equipe", "component" => "teamimg"],
            ["class" => "joueur", "text" => "Joueur", "key" => "Image", "component" => "joueurimg"],
        ];
    }

    #[Route(path: ['/lecdle'], name:'afficherLecdle', methods:["GET"])]
    static function afficherLecdle(): Response
    {
        $allJoueurs = LECRepository::getAllJoueurs();
        $joueurResult = LECRepository::random();
        $nbReussites = LECRepository::getNbReussites();
        $infoBar = self::LecAndLflInfoBar();
        return self::afficherVueGeneral("dle/dle.php",
            [
                "dle" => "LECDLE",
                "nbReussites" => $nbReussites,
                "allJoueurs" => $allJoueurs,
                "infoBar" => $infoBar,
                "joueurResult" => $joueurResult,
            ]);
    }

    #[Route(path: ['/lfldle'], name:'afficherLfldle', methods:["GET"])]
    static function afficherLfldle(): Response
    {
        $allJoueurs = LFLRepository::getAllJoueurs();
        $joueurResult = LFLRepository::random();
        $nbReussites = LFLRepository::getNbReussites();
        $infoBar = self::LecAndLflInfoBar();
        return self::afficherVueGeneral("dle/dle.php",
            [
                "dle" => "LFLDLE",
                "nbReussites" => $nbReussites,
                "allJoueurs" => $allJoueurs,
                "infoBar" => $infoBar,
                "joueurResult" => $joueurResult,
            ]);
    }

    #[Route(path: ['/add/reussite'], name:'addReussite', methods:["POST"])]
    static function addReussite(Request $request): JsonResponse
    {
        try {
            self::protectedRoute($request);

            $data = json_decode($request->getContent(), true);
            if ($data === null) {
                return new JsonResponse(["error" => "JSON invalide"], 400);
            }
            RepositoryMere::addReussite($data["dle"]);
            return new JsonResponse(["success" => true]);
        }catch (Exception $e){
            return new JsonResponse(["error" => $e->getMessage()], $e->getCode());
        }
    }
}