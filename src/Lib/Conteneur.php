<?php

namespace TheFeed\Lib;

class Conteneur
{
    private static array $listeServices;

    public static function ajouterService(string $nom, $service) : void {
        Conteneur::$listeServices[$nom] = $service;
    }

    /**
     * assistantUrl pour url de fichier
     * generateurUrl pour url de fonction
     */
    public static function recupererService(string $nom) {
        return Conteneur::$listeServices[$nom];
    }
}