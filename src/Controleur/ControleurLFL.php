<?php

namespace TheFeed\Controleur;

use TheFeed\Modele\Repository\LFLRepository;

class ControleurLFL extends ControleurMere
{
    public static function afficherLFL(){
      	$nbReussite = LFLRepository::getNbReussites();
        self::afficherVueGeneral(["cheminVueBody" => "lfl/lfl.php", "nbReussite" => $nbReussite]);
    }

}