<?php
/** @var UrlGenerator $generateurUrl */
use Symfony\Component\Routing\Generator\UrlGenerator;

$kcUrl = $generateurUrl->generate("afficherKcdle");
$lecUrl = $generateurUrl->generate("afficherLecdle");
$lflUrl = $generateurUrl->generate("afficherLfldle");
?>
<div id="app">
    <header class="header_HOME">
        <simpleimg :img="'HOMEDLE_Header.png'">
    </header>
    <div class="dle_body HOME">
        <div class="btn-container">
            <btngame v-for="val in data" :key="data.dle" :data="val"></btngame>
        </div>
    </div>

    <credit></credit>
</div>

<script type="module">
    new Vue({
        el : "#app",
        components: {
            simpleimg: () => import((`${window.global.JS_COMPONENT}simpleImg.js`)),
            btngame: () => import((`${window.global.JS_COMPONENT}btnGame.js`)),
            credit : () => import((`${window.global.JS_COMPONENT}credit.js`)),
        },
        data(){
            return{
                data : [
                    {
                        dle: "KCDLE",
                        url: <?= json_encode($kcUrl) ?>,
                        active : true,
                    },
                    {
                        dle: "LECDLE",
                        url: <?= json_encode($lecUrl) ?>,
                        active : true,
                    },
                    {
                        dle: "LFLDLE",
                        url: <?= json_encode($lflUrl) ?>,
                        active : true,
                    }
                ]
            }
        }
    })
</script>