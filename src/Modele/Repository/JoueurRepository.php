<?php

namespace TheFeed\Modele\Repository;

use PDO;
use TheFeed\Modele\ConnexionBDD;

class JoueurRepository extends RepositoryMere
{
    public static function getAllKCDLE(): bool|array
    {
        $sql = "SELECT * from JoueursKCDLE";
        ($pdo = ConnexionBDD::getPdo()->prepare($sql))->execute();

        return $pdo->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getAllDLE(): bool|array
    {
        $sql2 = "SELECT * from joueurs order by active DESC, Dle, Equipe, Role";
        ($pdo2 = ConnexionBDD::getPdo()->prepare($sql2))->execute();

        return  $pdo2->fetchAll(PDO::FETCH_ASSOC);
    }
}