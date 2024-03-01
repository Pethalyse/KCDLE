<?php

namespace TheFeed\Controleur;

use TheFeed\Modele\Repository\LECRepository;

class ControleurLEC extends ControleurMere
{
    public static function afficherLEC(){
      	$nbReussite = LECRepository::getNbReussites();
        self::afficherVueGeneral(["cheminVueBody" => "lec/lec.php", "nbReussite" => $nbReussite]);
    }

}