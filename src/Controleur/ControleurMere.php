<?php

namespace TheFeed\Controleur;
class ControleurMere
{
    private static function afficherVue(string $cheminVue, array $parametres = []) : void {
        extract($parametres); // Crée des variables à partir du tableau $parametres
        require __DIR__ ."/../Vue/$cheminVue"; // Charge la vue
    }
    protected static function afficherVueGeneral(array $parametres = []) : void{
        self::afficherVue("vueGeneral.php", $parametres);
    }

}