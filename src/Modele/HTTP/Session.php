<?php

namespace TheFeed\Modele\HTTP;

use Exception;

class Session
{
    private static ?Session $instance = null;

    /**
     * @throws Exception
     */

    private function __construct()
    {
        if(session_start() === false)
            throw new Exception("La session n'a pas réussi à démarrer.");
    }

    public static function getInstance(): Session
    {
        if(is_null(Session::$instance)){
            try{
                Session::$instance = new Session();
            }catch (Exception $e){
                echo $e;
            }
        }

        return Session::$instance;
    }

    public function enregistrer(string $id, $value)
    {
        $_SESSION[$id] = $value;
    }

    public function lire(string $id)
    {
        return $_SESSION[$id] ?? null;
    }

    public function contient($id): bool
    {
        return isset($_SESSION[$id]);
    }

    public function supprimer($id): void
    {
        unset($_SESSION[$id]);
    }

    public function detruire() : void
    {
        session_unset();
        session_destroy();
        Session::$instance = null;
    }

}