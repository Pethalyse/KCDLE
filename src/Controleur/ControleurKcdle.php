<?php

namespace TheFeed\Controleur;

use TheFeed\Modele\Repository\KCDLERepository;

class ControleurKcdle extends ControleurMere
{
    public static function afficherKc(){
      $nbReussite = KCDLERepository::getNbReussites();
      self::afficherVueGeneral(["cheminVueBody" => "kcdle/kcdle.php", "nbReussite" => $nbReussite]);
    }
}
