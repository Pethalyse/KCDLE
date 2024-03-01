<?php

namespace TheFeed\Controleur;
class RouteurQueryString
{
    public static function traiterRequete(){
        if(isset($_GET['controleur']))
            $controleur = $_GET["controleur"];
        else $controleur = $_POST['controleur'] ?? "home";


        $nomDeClasseControleur = "LFLdle\Controleur\Controleur" .ucfirst($controleur);

        if(class_exists($nomDeClasseControleur)){

            // On recupère l'action passée dans l'URL
            $action = $_GET["action"] ?? $_POST['action'] ?? "afficherHome";

            if(in_array($action, get_class_methods($nomDeClasseControleur))){
                $nomDeClasseControleur::$action();
            }else{
                ControleurHome::afficherHome();
            }
        }else{
            ControleurHome::afficherHome();
        }
    }

}