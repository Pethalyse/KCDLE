<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

// Vérification de la méthode de requête
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo "Méthode non autorisée";
    exit;
} else{
    TheFeed\Controleur\RouteurURLService::traiterRequete();
}