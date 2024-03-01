<?php
namespace TheFeed\Controleur;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Matcher\UrlMatcher;

class RouteurURL
{
    public static function traiterRequete() {
        $requete = Request::createFromGlobals();
        $contexteRequete = (new RequestContext())->fromRequest($requete);

        $routes = new RouteCollection();

        // Route afficherLFL
        $route = new Route("/lfldle", [
            "_controller" => [ControleurLFL::class, "afficherLFL"],
        ]);
        $routes->add("afficherLFL", $route);

        // Route afficherLEC
        $route = new Route("/lecdle", [
            "_controller" => [ControleurLEC::class, "afficherLEC"],
        ]);
        $routes->add("afficherLEC", $route);

        // Route afficherKC
        $route = new Route("/kcdle", [
            "_controller" => [ControleurKcdle::class, "afficherKc"],
        ]);
        $routes->add("afficherKC", $route);

        // Route afficheHOME1
        $route = new Route("/", [
            "_controller" => [ControleurHome::class, "afficherHome"],
        ]);
        $routes->add("afficherHome1", $route);

        // Route afficherHOME2
        $route = new Route("/home", [
            "_controller" => [ControleurHome::class, "afficherHome"],
        ]);
        $routes->add("afficherHome2", $route);

        $associateurUrl = new UrlMatcher($routes, $contexteRequete);
        $donneesRoute = $associateurUrl->match($requete->getPathInfo());

        call_user_func($donneesRoute["_controller"]);
    }
}