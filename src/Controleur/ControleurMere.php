<?php

namespace TheFeed\Controleur;
use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TheFeed\Lib\Conteneur;

class ControleurMere
{
    protected static function afficherVue(string $cheminVue, array $parametres = []): Response
    {
        extract($parametres);
        ob_start();
        require __DIR__ . "/../Vue/$cheminVue";
        $corpsReponse = ob_get_clean();
        return new Response($corpsReponse);
    }

    protected static function afficherVueGeneral(string $chemin, array $parametres = []) : Response
    {
        $parametres["cheminVueBody"] = $chemin;
        return self::afficherVue("vueGeneral.php", $parametres);
    }

    public static function afficherErreur($messageErreur = "", $statusCode = 400): Response
    {
        $reponse = ControleurMere::afficherVueGeneral('erreur.php', [
            "messageErreur" => $messageErreur,
        ]);

        $reponse->setStatusCode($statusCode);
        return $reponse;
    }

    /**
     * @param string $route
     * @param array $param
     * @return RedirectResponse
     */
    protected static function rediriger(string $route = "", array $param = []): RedirectResponse
    {
        // L'en-tête 'Location' permet d'effectuer une redirection
        // https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Location
        return new RedirectResponse((Conteneur::recupererService("generateurUrl"))->generate($route, $param));
    }

    /**
     * @throws Exception
     */
    protected static function protectedRoute(Request $request): void
    {
        $path = $request->getPathInfo();

        // Ne bloque pas les routes qui commencent par /api/
        if (str_starts_with($path, '/api/')) {
            return;
        }

        // Vérifier uniquement les requêtes POST
        if ($request->isMethod('POST')) {
            $allowedOrigin = $request->getSchemeAndHttpHost(); // http://localhost:8000 ou https://mon-site.com
            $origin = $request->headers->get('Origin');
            $referer = $request->headers->get('Referer');

            // 🚨 Bloquer immédiatement si `Origin` et `Referer` sont absents (cas Postman, cURL, Docker)
            if (!$origin && !$referer) {
                throw new Exception("Accès interdit (Requête sans origine détectée)", 403);
            }

            // 🚨 Vérifier si `Origin` ou `Referer` ne correspond pas au site
            if (($origin && !str_starts_with($origin, $allowedOrigin)) || ($referer && !str_starts_with($referer, $allowedOrigin))) {
                throw new Exception("Accès interdit (Origine invalide)", 403);
            }
        }
    }
}