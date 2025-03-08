<?php

namespace TheFeed\Modele\Repository;

use PDO;
use TheFeed\Modele\ConnexionBDD;

class KCDLERepository extends RepositoryMere
{
    public static function getAllJoueurs(): bool|array
    {
        $sql = "SELECT * from JoueursKCDLE";
        ($pdo = ConnexionBDD::getPdo()->prepare($sql))->execute();
        return $pdo->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getNbReussites() : int
    {
        $sql = "SELECT nbPersonnes from nbReussites WHERE Dle = 'kcdle' AND date = '" .date("Y-m-d") ."'";

        ($pdo = ConnexionBDD::getPdo()->prepare($sql))->execute();

        return $pdo->fetch()[0];
    }

    public static function random(): bool|array
    {
        $sql = "SELECT * FROM JoueursKCDLE where Image = (select Image from joueurdujourkcdle WHERE date = '" .date("Y-m-d") ."')";

        ($pdo = ConnexionBDD::getPdo()->prepare($sql))->execute();
        return $pdo->fetch(PDO::FETCH_ASSOC);
    }

//    public static function applySearch(string $like)
//    {
//        $sql = "SELECT * from JoueursKCDLE WHERE Pseudo LIKE :l ORDER BY Pseudo; ";
//
//        $pdo = ConnexionBDD::getPdo()->prepare($sql);
//        $pdo->execute(array("l" => $like ."%"));
//        return $pdo->fetchAll(PDO::FETCH_ASSOC);
//    }
//
//    public static function applyGuess(string $guessNom, string $fake = ""): array
//    {
//        $random = self::random();
//        $guess = self::recupererFromNom($guessNom);
//
//        if($random->equals($guess))
//        {
//            if($fake === "nofake"){
//                $sql = "UPDATE `nbReussites` SET `nbPersonnes`= nbPersonnes+1 WHERE dle = 'kcdle' AND date = CURRENT_DATE";
//                (ConnexionBDD::getPdo()->prepare($sql))->execute();
//            }
//
//            return array("Nationalite" => true,
//                "Date_Naissance" => 0,
//                "Role" => true,
//                "TeamMaintenant" => true,
//                "TeamAvant" => true,
//                "Image" => true,
//                "Titres" => 0,
//                "Annee" => 0,
//                "Jeu" => true);
//        }else{
//            return self::comparerKC($random, $guess);
//        }
//    }
//
//    public static function guess(string $guessNom, string $fake){
//        echo json_encode(self::applyGuess($guessNom, $fake));
//    }
//    public static function search(string $like){
//        echo json_encode(self::applySearch($like));
//    }
//
//    public static function fromNom(string $nom){
//        echo json_encode(self::recupererFromNom($nom)->json());
//    }
//
//    public static function fromNomTab(string $tab){
//        $explode = explode(" ", $tab);
//        $tab = array();
//        foreach ($explode as $val){
//            $tab[] = self::recupererFromNom($val)->json();
//        }
//        echo json_encode($tab);
//    }
//
//    public static function multiGuess(string $tab){
//        $explode = explode(" ", $tab);
//        $tab = array();
//        foreach ($explode as $val){
//            $tab[] = self::applyGuess($val);
//        }
//        echo json_encode($tab);
//    }
}