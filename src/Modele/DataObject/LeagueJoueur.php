<?php

namespace TheFeed\Modele\DataObject;

use TheFeed\Modele\HTTP\Session;

class LeagueJoueur
{
    private string $nom;
    private string $nationalite;
    private string $naissance;
    private string $team;
    private string $role;

    /**
     * @param string $nom
     * @param string $nationalite
     * @param string $naissance
     * @param string $team
     * @param string $role
     */
    public function __construct(string $nom, string $nationalite, string $naissance, string $team, string $role)
    {
        $this->nom = $nom;
        $this->nationalite = $nationalite;
        $this->naissance = $naissance;
        $this->team = $team;
        $this->role = $role;
    }

    public function getNomPNG(): string
    {
        return $this->nom;
    }

    public function getNom() : string
    {
        $arr = array(".png");
        $n = str_replace($arr, "", $this->getNomPNG());

        $arr = array("_");
        return str_replace($arr, " ", $n);
    }

    public function getNationalite(): string
    {
        return $this->nationalite;
    }

    public function getNaissance(): string
    {
        return $this->naissance;
    }

    function age($date) {
        $age = date('Y') - $date;
        if (date('md') < date('md', strtotime($date))) {
            return $age - 1;
        }
        return $age;
    }

    public function getAge()
    {
        return $this->age($this->getNaissance());
    }

    public function getTeam(): string
    {
        return $this->team;
    }

    public function getRole(): string
    {
        return $this->role;
    }


    /**
     * @param LeagueJoueur $other
     * @return bool
     */
    public function equals(LeagueJoueur $other): bool
    {
        return $this->nom == $other->nom;
    }

    public function json(): array
    {
        return ["Nationalite" => $this->getNationalite(),
            "Date_Naissance" => $this->getAge(),
            "Role" => $this->getRole(),
            "Equipe" => $this->getTeam(),
            "Nom" => $this->getNom(),
            "Image" => $this->getNomPNG()];
    }

}