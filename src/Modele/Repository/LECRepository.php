<?php

namespace TheFeed\Modele\Repository;

use PDO;
use TheFeed\Modele\ConnexionBDD;
use TheFeed\Modele\DataObject\LeagueJoueur;

class LECRepository extends RepositoryMere
{
    private static function creerDepuisTableau($tab): LeagueJoueur
    {
        return new LeagueJoueur($tab[1], $tab[2], $tab[3], $tab[4], $tab[5]);
    }
  
  	public static function getNbReussites()
    {
        $sql = "SELECT nbPersonnes from nbReussites WHERE Dle = 'lecdle' AND date = '" .date("Y-m-d") ."'";

        ($pdo = ConnexionBDD::getPdo()->prepare($sql))->execute();

        return $pdo->fetch()[0];
    }

    public static function random(): LeagueJoueur
    {
        $sql = "SELECT * FROM joueurs where Dle = 'LEC' AND ID = (select ID from joueurdujourlec WHERE date = '" .date("Y-m-d") ."')";

        ($pdo = ConnexionBDD::getPdo()->prepare($sql))->execute();
        return self::creerDepuisTableau($pdo->fetch());
    }

    public static function recupererFromNom($nom): LeagueJoueur
    {
        $sql = "SELECT * from joueurs where Nom = :nom AND Dle = 'LEC'";
        $tab = array(":nom" => $nom);

        ($pdo = ConnexionBDD::getPdo()->prepare($sql))->execute($tab);
        return self::creerDepuisTableau($pdo->fetch());
    }

    public static function applySearch(string $like)
    {
        $sql = "SELECT * from joueurs WHERE nom LIKE :l AND dle = 'LEC' ORDER BY nom; ";

        $pdo = ConnexionBDD::getPdo()->prepare($sql);
        $pdo->execute(array("l" => $like ."%"));
        return $pdo->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function applyGuess(string $guessNom, string $fake = ""): array
    {
        $random = self::random();
        $guess = self::recupererFromNom($guessNom);

        if($random->equals($guess))
        {
            if($fake === "nofake"){
                $sql = "UPDATE `nbReussites` SET `nbPersonnes`= nbPersonnes+1 WHERE dle = 'lecdle' AND date = CURRENT_DATE ";
                (ConnexionBDD::getPdo()->prepare($sql))->execute();
            }

            return array("Nationalite" => true,"Date_Naissance" => 0, "Role" => true, "Equipe" => true, "Nom" => true);
        }else{
            return self::comparerLOL($random, $guess);
        }
    }

    public static function guess(string $guessNom, string $fake){
        echo json_encode(self::applyGuess($guessNom, $fake));
    }
    public static function search(string $like){
        echo json_encode(self::applySearch($like));
    }

    public static function fromNom(string $nom){
        echo json_encode(self::recupererFromNom($nom)->json());
    }

    public static function fromNomTab(string $tab){
        $explode = explode(" ", $tab);
        $tab = array();
        foreach ($explode as $val){
            $tab[] = self::recupererFromNom($val)->json();
        }
        echo json_encode($tab);
    }

    public static function multiGuess(string $tab){
        $explode = explode(" ", $tab);
        $tab = array();
        foreach ($explode as $val){
            $tab[] = self::applyGuess($val);
        }
        echo json_encode($tab);
    }
}