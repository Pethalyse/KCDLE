<?php
/**
 * @var LeagueJoueur $joueur;
 * @var array $allJoueurs
 * @var LeagueJoueur $j;
 */

use TheFeed\Modele\DataObject\LeagueJoueur;

?>

<header class="header_lec">
    <div class="btn-home" >
      	<img class="logo" src="../ressources/images/LECDle_Logo.png" onclick="goHome()">
      	<div id="nbTrouve"><?php echo $nbReussite ?> personnes ont trouvé le joueur</div>
    </div>

    <div class="containt-name">
        <div class="search-bar-lec">
            <input class="sub" type="text" placeholder="Entrez le nom d'un joueur de LEC">
        </div>
        <div id="search"></div>
    </div>
</header>

<body class="lec">
    <main>
        <div class="theBody">
            <div class="infoNom">
                <div class="divInfo">
                    <div class="divText">Nationalité</div>
                    <hr data-v-4cd5ba35="">
                    <div class="nationalite"></div>
                </div>

                <div class="divInfo">
                    <div class="divText">Âge</div>
                    <hr data-v-4cd5ba35="">
                    <div class="age"></div>
                </div>

                <div class="divInfo">
                    <div class="divText">Rôle</div>
                    <hr data-v-4cd5ba35="">
                    <div class="role"></div>
                </div>

                <div class="divInfo">
                    <div class="divText">Équipe</div>
                    <hr data-v-4cd5ba35="">
                    <div class="equipe"></div>
                </div>

                <div class="divInfo">
                    <div class="divText">Joueur</div>
                    <hr data-v-4cd5ba35="">
                    <div class="joueur"></div>
                </div>

            </div>
            <p id="reponse"></p>

            <div id="loading" style="display: none; width: 10vw; max-width: 50px" >
                <img src="../ressources/images/loading.gif" style="width: 100%">
            </div>

        </div>

    </main>
</body>

<script src="../src/Js/changementPage.js"></script>
<script>updateStorage("lec"); setup("lec")</script>


