<?php
/**
 * @var Array $JoueursKCDLE
 * @var Array $JoueursDLE
 * @var Array $Equipes
 * @var Array $Pays
 * @var Array $Roles
 */
?>

<div id="app">
    <header id="admin_header">
        <div>
            <button @click="show='joueurskcdle'">Joueurs</button>
            <button>Équipes</button>
            <button>Pays</button>
            <button>Rôles</button>
        </div>
        <div v-if="show==='joueurskcdle' || show==='joueursdle'">
            <button @click="show='joueurskcdle'">KCDLE</button>
            <button @click="show='joueursdle'">DLE</button>
        </div>
    </header>
    <div id="admin_body">
        <div class="clickable_elements">
            <joueur v-if="show==='joueurskcdle'" v-for="joueur in joueurskcdle" :key="joueur.Image" :joueur="joueur"></joueur>
            <joueur v-if="show==='joueursdle'" v-for="joueur in joueursdle" :key="joueur.Image" :joueur="joueur"></joueur>
        </div>
    </div>
</div>

<script type="module">
    new Vue({
        el : "#app",
        data(){
            return{
                joueurskcdle: <?= json_encode($JoueursKCDLE) ?>,
                joueursdle: <?= json_encode($JoueursDLE) ?>,
                equipes: <?= json_encode($Equipes) ?>,
                pays: <?= json_encode($Pays) ?>,
                roles: <?= json_encode($Roles) ?>,

                show: "joueurskcdle",
            }
        },
        components: {
            joueur: () => import((`${window.global.JS_COMPONENT}playerCard.js`)),
        },
    })
</script>
