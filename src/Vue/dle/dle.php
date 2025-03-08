<?php
/**
 * @var array $allJoueurs
 * @var array $joueurResult

 * @var int $nbReussites
 * @var String $dle
 *
 * @var Array $infoBar
 */

/** @var UrlGenerator $generateurUrl */
use Symfony\Component\Routing\Generator\UrlGenerator;

$homeUrl = $generateurUrl->generate("afficherHome");

$kcUrl = $generateurUrl->generate("afficherKcdle");
$lecUrl = $generateurUrl->generate("afficherLecdle");
$lflUrl = $generateurUrl->generate("afficherLfldle");
?>

<div id="app">
    <header :class="'header_' + dle">
        <div class="btn-home">
            <simpleimg class="logo" :alt="dle" :img="dle + '_page_Logo.png'" @onclick="goHome()"></simpleimg>
            <div id="nbTrouve">{{ printNbTrouveText() }}</div>
        </div>
        <searchbar v-if="!victoire"
            class="containt-name"
            :dle="dle"
            :joueurs="joueurs"
            :unwrittable="before_victoire"
            @click_card="handleClickCard"
        >
        </searchbar>
    </header>

    <div class="dle_body" :class="dle">
        <playertab
            :infobar="infoBar"
            :guess="guess"
            :result="joueurresult"
            :dle="dle"
            @end_animation="handleEndAnimation"
        ></playertab>

        <popupgg v-if="victoire"
            :infobar="infoBar"
            :result="joueurresult"
            :guess="guess"
            :dle="dle"
            :btndata="data"
        ></popupgg>
    </div>

    <credit></credit>
</div>

<script type="module">
    new Vue({
        el : "#app",
        components : {
            simpleimg : () => import((`${window.global.JS_COMPONENT}simpleImg.js`)),
            credit : () => import((`${window.global.JS_COMPONENT}credit.js`)),
            searchbar : () => import((`${window.global.JS_COMPONENT}searchBar.js`)),
            playertab : () => import((`${window.global.JS_COMPONENT}playerTab.js`)),
            popupgg : () => import((`${window.global.JS_COMPONENT}popupGG.js`)),
        },
        data(){
            return {
                homeUrl : <?= json_encode($homeUrl) ?>,

                joueurs : <?=json_encode($allJoueurs)?>,
                joueurresult : <?=json_encode($joueurResult)?>,

                infoBar : <?=json_encode($infoBar)?>,
                dle: <?=json_encode($dle)?>,
                nbReussites : parseInt(<?= json_encode($nbReussites)?>),

                guess : [],

                before_victoire : false,
                victoire : false,
                isHandle : true,

                data : [
                    {
                        dle: "KCDLE",
                        url: <?= json_encode($kcUrl) ?>,
                        active : <?=json_encode($dle)?> !== "KCDLE",
                    },
                    {
                        dle: "LECDLE",
                        url: <?= json_encode($lecUrl) ?>,
                        active : <?=json_encode($dle)?> !== "LECDLE",
                    },
                    {
                        dle: "LFLDLE",
                        url: <?= json_encode($lflUrl) ?>,
                        active : <?=json_encode($dle)?> !== "LFLDLE",
                    }
                ]
            }
        },
        methods : {
            goHome(){
                window.location.href = this.homeUrl;
            },

            printNbTrouveText(){
                if(this.nbReussites === 1) return this.nbReussites + " personne a trouvé le joueur";
                if(this.nbReussites > 0) return this.nbReussites + " personnes ont trouvé le joueur";
                return "Personne n'a trouvé le joueur"
            },

            calculerAge(dateNaissance) {
                const aujourdHui = new Date();
                const naissance = new Date(dateNaissance);

                let age = aujourdHui.getFullYear() - naissance.getFullYear();
                const mois = aujourdHui.getMonth() - naissance.getMonth();
                const jour = aujourdHui.getDate() - naissance.getDate();

                if (mois < 0 || (mois === 0 && jour < 0)) {
                    age--;
                }

                return age;
            },

            handleClickCard(e, handle = true){
                //ON VERIFIE A CHAQUE CLIQUE QUE SI ON PASSE A MINUIT AU MOMENT MEME ALORS CA FORCE LE RESET
                if(this.clearLocalStorageDaily()){
                    window.location.reload()
                    return;
                }

                const joueurTrouve = this.joueurs.find(joueur => joueur.Image === e.Image || joueur.Pseudo === e.Pseudo);
                if (joueurTrouve) e = { ...joueurTrouve };

                e.age = this.calculerAge(e.Date_naissance);
                this.guess.unshift(e);
                this.joueurs = this.joueurs.filter(joueur => !Object.entries(joueur).every(([key, value]) => value === e[key]));

                this.isVictoire(e, handle);

                if(!handle) return;
                localStorage.setItem(this.dle, JSON.stringify(this.guess)); //SET HISTORIQUE GUESS
            },

            handleEndAnimation(joueur) {
                this.victoire = this.victoire || this.before_victoire && Object.entries(joueur).every(([key, value]) => value === this.joueurresult[key]);
                if(!this.isHandle)
                    this.nbReussites += 1;
            },

            isVictoire(e, handle = true){
                if(!Object.entries(e).every(([key, value]) => value === this.joueurresult[key])) return

                localStorage.setItem(`${this.dle}_win`, "true"); //SET WIN
                this.before_victoire = true;

                if(!handle) return;
                fetch(`${window.global.BASE_PATH}web/add/reussite`, {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ dle: this.dle })
                })
                .catch(err => console.error(err));
                this.isHandle = false;
            },

            clearLocalStorageDaily() { //CLEAR
                const now = new Date();
                const todayLocal = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime();

                const lastClearLocal = parseInt(localStorage.getItem("lastClearTime") || "0", 10);

                if (lastClearLocal < todayLocal) {
                    localStorage.clear();
                    localStorage.setItem("lastClearTime", todayLocal.toString());
                    return true;
                }
            },
        },
        mounted() {
            this.joueurresult.age = this.calculerAge(this.joueurresult.Date_naissance);

            this.clearLocalStorageDaily(); //CLEAR CHAQUE JOUR

            // this.victoire = !!localStorage.getItem(`${this.dle}_win`); //EST-CE QU'ON A DEJA GAGNÉ OU PAS
            this.before_victoire = !!localStorage.getItem(`${this.dle}_win`);

            const storage = localStorage.getItem(this.dle) //RECUPÈRE L'HISTORIQUE DES GUESS
            if(!storage) return
            for(const item of JSON.parse(storage).reverse()){
                this.handleClickCard(item, false)
            }
        }
    })
</script>