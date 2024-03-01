<?php

namespace TheFeed\Modele\DataObject;

class Kcdle
{
    private string $image;
    private string $jeu;
    private string $nom;
    private string $naissance;
    private string $nationalite;
    private string $teamAvant;
    private string $teamMaintenant;
    private int $annee;
    private int $titres;
    private string $role;

    /**
     * @param string $image
     * @param string $jeu
     * @param string $nom
     * @param string $naissance
     * @param string $nationalite
     * @param string $teamAvant
     * @param string $teamMaintenant
     * @param int $annee
     * @param int $titres
     * @param string $role
     */
    public function __construct(string $image, string $jeu, string $nom, string $naissance, string $nationalite, string $teamAvant, string $teamMaintenant, int $annee, int $titres, string $role)
    {
        $this->image = $image;
        $this->jeu = $jeu;
        $this->nom = $nom;
        $this->naissance = $naissance;
        $this->nationalite = $nationalite;
        $this->teamAvant = $teamAvant;
        $this->teamMaintenant = $teamMaintenant;
        $this->annee = $annee;
        $this->titres = $titres;
        $this->role = $role;
    }

    public function getImage(): string
    {
        return $this->image;
    }

    public function getJeu(): string
    {
        return $this->jeu;
    }

    public function getNom(): string
    {
        return $this->nom;
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

    public function getNationalite(): string
    {
        return $this->nationalite;
    }

    public function getTeamAvant(): string
    {
        return $this->teamAvant;
    }

    public function getTeamMaintenant(): string
    {
        return $this->teamMaintenant;
    }

    public function getAnnee(): int
    {
        return $this->annee;
    }

    public function getTitres(): int
    {
        return $this->titres;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    /**
     * @param Kcdle $other
     * @return bool
     */
    public function equals(Kcdle $other): bool
    {
        return $this->image == $other->image;
    }

    public function json(): array
    {
        return ["Nationalite" => $this->getNationalite(),
            "Date_Naissance" => $this->getAge(),
            "Role" => $this->getRole(),
            "Nom" => $this->getNom(),
            "Image" => $this->getImage(),
            "Titres" => $this->getTitres(),
            "Annee" => $this->getAnnee(),
            "TeamMaintenant" => $this->getTeamMaintenant(),
            "TeamAvant" => $this->getTeamAvant(),
            "Jeu" => $this->getJeu()
            ];
    }


}