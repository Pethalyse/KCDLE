<?php
namespace TheFeed\Modele;
use TheFeed\Configuration\Configuration;
use PDO;
class ConnexionBDD
{
    private static ?ConnexionBDD $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $hostname = Configuration::getHostname();
        $port = Configuration::getPort();
        $databaseName = Configuration::getDatabase();
        $login = Configuration::getLogin();
        $password = Configuration::getPassword();

        // Connexion à la base de données
        // Le dernier argument sert à ce que toutes les chaines de caractères
        // en entrée et sortie de MySql soit dans le codage UTF-8
        $this->pdo = new PDO("mysql:host=$hostname;dbname=$databaseName", $login, $password,
            array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));

        // On active le mode d'affichage des erreurs, et le lancement d'exception en cas d'erreur
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    }

    public static function getInstance() : ConnexionBDD {
        if (is_null(ConnexionBDD::$instance))
            ConnexionBDD::$instance = new ConnexionBDD();
        return ConnexionBDD::$instance;
    }

    public static function getPdo(): PDO {
        return ConnexionBDD::getInstance()->pdo;
    }


}