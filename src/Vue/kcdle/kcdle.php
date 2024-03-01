<?php
/**
 * @var Kcdle $joueur;
 * @var array $allJoueurs
 * @var Kcdle $j;
 */

use TheFeed\Modele\DataObject\Kcdle;

?>

<header class="header_kc">
  <div class="btn-home" >
    <img class="logo" src="../ressources/images/KCDLE_page_Logo.png" onclick="goHome()">
    <div id="nbTrouve"><?php echo $nbReussite ?> personnes ont trouvé le joueur</div>
  </div>
  <div class="containt-name">
    <div class="search-bar-kc">
      <input class="sub" type="text" placeholder="Entrez le nom d'un membre qui est/a été à la KarmineCorp">
    </div>
    <div id="search"></div>
  </div>

</header>

<body class="kc">
    <main>
        <div class="theBody">
            <div class="infoNom">
              	<div class="decalage" style="width: 100vw;"></div>
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
                    <div class="divText">Jeu</div>
                    <hr data-v-4cd5ba35="">
                  	<div class="jeu"></div>
                </div>

                <div class="divInfo">
                    <div class="divText">Arrivée</div>
                    <hr data-v-4cd5ba35="">
                  	<div class="arrivee"></div>
                </div>

                <div class="divInfo">
                    <div class="divText">Titre(s)</div>
                    <hr data-v-4cd5ba35="">
                  	<div class="titres"></div>
                </div>

                <div class="divInfo">
                    <div class="divText">AvantKC</div>
                    <hr data-v-4cd5ba35="">
                  	<div class="avantkc"></div>
                </div>

                <div class="divInfo">
                    <div class="divText">Maintenant</div>
                    <hr data-v-4cd5ba35="">
                  	<div class="maintenant"></div>
                </div>

                <div class="divInfo">
                    <div class="divText">Rôle</div>
                    <hr data-v-4cd5ba35="">
                  	<div class="role"></div>
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
<script>setup("kc")</script>

