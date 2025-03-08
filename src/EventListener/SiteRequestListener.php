<?php

namespace TheFeed\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\JsonResponse;

#[AsEventListener(event: 'kernel.request')]
class SiteRequestListener
{
    public function onKernelRequest(RequestEvent $event): void
    {
        dump("Listener exécuté !");

        $request = $event->getRequest();
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
                $event->setResponse(new JsonResponse([
                    "message" => "Accès interdit (Requête sans origine détectée)"
                ], 403));
                return;
            }

            // 🚨 Vérifier si `Origin` ou `Referer` ne correspond pas au site
            if (($origin && !str_starts_with($origin, $allowedOrigin)) || ($referer && !str_starts_with($referer, $allowedOrigin))) {
                $event->setResponse(new JsonResponse([
                    "message" => "Accès interdit (Origine invalide)"
                ], 403));
                return;
            }
        }
    }
}


