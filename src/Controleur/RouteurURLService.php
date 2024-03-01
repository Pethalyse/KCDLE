<?php
namespace TheFeed\Controleur;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use TheFeed\Modele\Repository\KCDLERepository;
use TheFeed\Modele\Repository\LECRepository;
use TheFeed\Modele\Repository\LFLRepository;

class RouteurURLService
{
    public static function traiterRequete() {
        $requete = Request::createFromGlobals();
        $contexteRequete = (new RequestContext())->fromRequest($requete);

        $routes = new RouteCollection();

        //////////LFL////////////
            $route = new Route("/lflGuess/{guessNom}/{fake}", [
                "_controller" => [LFLRepository::class, "guess"],
            ]);
            $routes->add("guessLFL", $route);

            $route = new Route("/lflSearch/{like}", [
                "_controller" => [LFLRepository::class, "search"],
            ]);
            $routes->add("searchLFL", $route);

            $route = new Route("/lflFromNom/{nom}", [
                "_controller" => [LFLRepository::class, "fromNom"],
            ]);
            $routes->add("fromNomLFL", $route);

            $route = new Route("/lflFromNomTab/{tab}", [
                "_controller" => [LFLRepository::class, "fromNomTab"],
            ]);
            $routes->add("fromNomTabLFL", $route);

            $route = new Route("/lflMultiGuess/{tab}", [
                "_controller" => [LFLRepository::class, "multiGuess"],
            ]);
            $routes->add("multiGuessLFL", $route);


        //////////LEC////////////
            $route = new Route("/lecGuess/{guessNom}/{fake}", [
                "_controller" => [LECRepository::class, "guess"],
            ]);
            $routes->add("guessLEC", $route);

            $route = new Route("/lecSearch/{like}", [
                "_controller" => [LECRepository::class, "search"],
            ]);
            $routes->add("searchLEC", $route);

            $route = new Route("/lecFromNom/{nom}", [
                "_controller" => [LECRepository::class, "fromNom"],
            ]);
            $routes->add("fromNomLEC", $route);

            $route = new Route("/lecFromNomTab/{tab}", [
                "_controller" => [LECRepository::class, "fromNomTab"],
            ]);
            $routes->add("fromNomTabLEC", $route);

            $route = new Route("/lecMultiGuess/{tab}", [
                "_controller" => [LECRepository::class, "multiGuess"],
            ]);
            $routes->add("multiGuessLEC", $route);



        //////////KC////////////
            $route = new Route("/kcGuess/{guessNom}/{fake}", [
                "_controller" => [KCDLERepository::class, "guess"],
            ]);
            $routes->add("guessKC", $route);

            $route = new Route("/kcSearch/{like}", [
                "_controller" => [KCDLERepository::class, "search"],
            ]);
            $routes->add("searchKC", $route);

            $route = new Route("/kcFromNom/{nom}", [
                "_controller" => [KCDLERepository::class, "fromNom"],
            ]);
            $routes->add("fromNomKC", $route);

            $route = new Route("/kcFromNomTab/{tab}", [
                "_controller" => [KCDLERepository::class, "fromNomTab"],
            ]);
            $routes->add("fromNomTabKC", $route);

            $route = new Route("/kcMultiGuess/{tab}", [
                "_controller" => [KCDLERepository::class, "multiGuess"],
            ]);
            $routes->add("multiGuessKC", $route);


        $associateurUrl = new UrlMatcher($routes, $contexteRequete);
        $donneesRoute = $associateurUrl->match($requete->getPathInfo());

        $requete->attributes->add($donneesRoute);

        $resolveurDeControleur = new ControllerResolver();
        $controleur = $resolveurDeControleur->getController($requete);

        $resolveurDArguments = new ArgumentResolver();
        $arguments = $resolveurDArguments->getArguments($requete, $controleur);

        call_user_func_array($controleur, $arguments);
    }
}