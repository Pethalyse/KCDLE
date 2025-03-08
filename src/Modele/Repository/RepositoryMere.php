<?php

namespace TheFeed\Modele\Repository;

use TheFeed\Modele\ConnexionBDD;

abstract class RepositoryMere
{
    public static function addReussite($dle): void
    {
        $sql = "UPDATE `nbReussites` SET `nbPersonnes`= nbPersonnes+1 WHERE dle = ? AND date = CURRENT_DATE";
        (ConnexionBDD::getPdo()->prepare($sql))->execute([strtolower($dle)]);
    }

//    /**
//     * @param LeagueJoueur $random
//     * @param LeagueJoueur $guess
//     * @return array
//     */
//    protected static function comparerLOL(LeagueJoueur $random, LeagueJoueur $guess): array
//    {
//        return array("Nationalite" => $random->getNationalite() === $guess->getNationalite(),
//            "Date_Naissance" => $random->getAge() === $guess->getAge() ? 0 : ($random->getAge() > $guess->getAge() ? 1 : -1),
//            "Role" => $random->getRole() === $guess->getRole(),
//            "Equipe" => $random->getTeam() === $guess->getTeam(),
//            "Nom" => $random->getNom() === $guess->getNom());
//    }
//
//    protected static function comparerKC(Kcdle $random, Kcdle $guess): array
//    {
//        return array("Nationalite" => $random->getNationalite() === $guess->getNationalite(),
//            "Date_Naissance" => $random->getAge() === $guess->getAge() ? 0 : ($random->getAge() > $guess->getAge() ? 1 : -1),
//            "Role" => $random->getRole() === $guess->getRole(),
//            "TeamMaintenant" => $random->getTeamMaintenant() === $guess->getTeamMaintenant(),
//            "TeamAvant" => $random->getTeamAvant() === $guess->getTeamAvant(),
//            "Image" => $random->getImage() === $guess->getImage(),
//            "Titres" => $random->getTitres() === $guess->getTitres() ? 0 : ($random->getTitres() > $guess->getTitres() ? 1 : -1),
//            "Annee" => $random->getAnnee() === $guess->getAnnee() ? 0 : ($random->getAnnee() > $guess->getAnnee() ? 1 : -1),
//            "Jeu" => $random->getJeu() === $guess->getJeu()
//            );
//    }
}