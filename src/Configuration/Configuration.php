<?php

namespace TheFeed\Configuration;
class Configuration
{
    static private array $databaseConfiguration = array(
        'hostname' => 'db5015045884.hosting-data.io',
        'database' => 'dbs12498527',
        'port' => '3306',
        'login' => 'dbu4126087',
        'password' => '8mpRwAY!y2W$QAD'
    );

    static public function getPath() : string{
        return "https://kcdle.fr/site/web/";
    }
    static public function getLogin() : string {
        return Configuration::$databaseConfiguration['login'];
    }

    static public function getPassword() : string {
        return Configuration::$databaseConfiguration['password'];
    }

    static public function getHostname() : string {
        return Configuration::$databaseConfiguration['hostname'];
    }

    static public function getPort() : string {
        return Configuration::$databaseConfiguration['port'];
    }

    static public function getDatabase() : string {
        return Configuration::$databaseConfiguration['database'];
    }

}
