<?php
/**
 * @var LeagueJoueur $joueur;
 * @var LeagueJoueur $j;
 * @var LeagueJoueur $l;
 * 
 */

use TheFeed\Modele\DataObject\LeagueJoueur;
use TheFeed\Modele\HTTP\Session;
use TheFeed\Modele\Repository\LFLRepository;

?>

<header class="header_lfl">
    <div class="headerBody">
      <div class="btn-home" >
        <img class="logo" src="../ressources/images/LFLDle_Logo.png" onclick="goHome()">
        <div id="nbTrouve"><?php echo $nbReussite ?> personnes ont trouvé le joueur</div>
      </div>
    </div>
    <div class="containt-name">
      <div class="search-bar-lfl">
        <input class="sub" type="text" placeholder="Entrez le nom d'un joueur de LFL">
      </div>
      <div id="search"></div>
      
    </div>
</header>

<body class="lfl">
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
<script>updateStorage("lfl"); setup("lfl")</script>


