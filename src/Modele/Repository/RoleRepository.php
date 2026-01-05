<?php

namespace TheFeed\Modele\Repository;

use PDO;
use TheFeed\Modele\ConnexionBDD;

class RoleRepository extends RepositoryMere
{
    public static function getAll(): bool|array
    {
        $sql = "SELECT * from rôle";
        ($pdo = ConnexionBDD::getPdo()->prepare($sql))->execute();

        return $pdo->fetchAll(PDO::FETCH_ASSOC);
    }
}