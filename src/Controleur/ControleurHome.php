<?php

namespace TheFeed\Controleur;

class ControleurHome extends ControleurMere
{

    public static function afficherHome()
    {
        self::afficherVueGeneral(["cheminVueBody" => "home/home.php"]);
    }
}