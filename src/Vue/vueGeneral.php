<?php
/**
 * @var string $imgProfil
 * @var string $cheminVueBody
 */

use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\Routing\Generator\UrlGenerator;
use TheFeed\Configuration\Configuration;
use TheFeed\Lib\Conteneur;

/** @var UrlGenerator $generateurUrl */
$generateurUrl = Conteneur::recupererService("generateurUrl");
/** @var UrlHelper $assistantUrl */
$assistantUrl = Conteneur::recupererService("assistantUrl");


define("BASE_PATH", Configuration::getPath()); // Changez en fonction de votre structure de projet
define("JS_COMPONENT", Configuration::getPath() . "src/Js/components/"); // Changez en fonction de votre structure de projet
?>

<!DOCTYPE html>

<html lang="fr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <link href='https://fonts.googleapis.com/css?family=Syne' rel='stylesheet'>
    <meta name="description" content="Essayez de deviner le joueur de la lfl et de la lec parmis tout les joueurs League of legends de la ligue actuelle, ou devinez un joueur de la Karmine Corp parmi tout les joueurs. " />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
  	<link rel="icon" type="image/x-icon" href="<?= $assistantUrl->getAbsoluteUrl("../ressources/images/LogoEquipe/KarmineCorp.png") ?>">
  	<link rel="stylesheet" type="text/css" media="screen and (min-width: 1281px)" href="<?= $assistantUrl->getAbsoluteUrl("../ressources/css/style.css") ?>"
  	<link rel="stylesheet" media="screen and (max-width: 1280px)" href="<?= $assistantUrl->getAbsoluteUrl("../ressources/css/petite_resolution.css") ?>" />

    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-1185166602985441" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/vue@2.6.12/dist/vue.js"></script>

    <title>KCdle - Guess the EUW League of legends or Karmine Corp player</title>
    <script>
        window.global = {
            JS_COMPONENT: "<?php echo JS_COMPONENT; ?>",
            BASE_PATH : "<?php echo BASE_PATH; ?>"
        };
    </script>
</head>

<body>
    <?php require __DIR__ . "/{$cheminVueBody}"; ?>
</body>

<footer>
</footer>

