<?php

namespace TheFeed\Controleur;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use TheFeed\Modele\Repository\EquipeRepository;
use TheFeed\Modele\Repository\JoueurRepository;
use TheFeed\Modele\Repository\PaysRepository;
use TheFeed\Modele\Repository\RoleRepository;

class ControleurAdmin extends ControleurMere
{
    #[Route(path: ['/X9Y7KpL4TqZ8V1Rm/B3X7LpQ9VzT1Y6Rm'], name:'afficherPanel', methods:["GET"])]
    static function afficherPanel(): Response
    {

        return self::afficherVueGeneral("admin/panel.php", [
            "JoueursKCDLE" => JoueurRepository::getAllKCDLE(),
            "JoueursDLE" => JoueurRepository::getAllDLE(),
            "Equipes" => EquipeRepository::getAll(),
            "Roles" => RoleRepository::getAll(),
            "Pays" => PaysRepository::getAll(),
        ]);
    }

}