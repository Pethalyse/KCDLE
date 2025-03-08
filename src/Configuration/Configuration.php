<?php

namespace TheFeed\Configuration;
class Configuration
{
    static private array $databaseConfiguration = array(
        'hostname' => '172.17.0.1',
        'database' => 'dbs12498527',
        'port' => '3306',
        'login' => 'root',
        'password' => 'rootpassword'
    );

    static public function getPath() : string{
        return "http://localhost/kcdle/";
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