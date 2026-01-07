<?php

namespace TheFeed\Modele\Repository;

use PDO;
use TheFeed\Modele\ConnexionBDD;

class PaysRepository extends RepositoryMere
{
    public static function getAll(): bool|array
    {
        $sql = "SELECT * from pays";
        ($pdo = ConnexionBDD::getPdo()->prepare($sql))->execute();

        return $pdo->fetchAll(PDO::FETCH_ASSOC);
    }
}