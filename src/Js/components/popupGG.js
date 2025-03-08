export default {
    props: {
        infobar: Array,
        result: Object,
        guess : Array,
        dle: String,
        btndata: Array,
    },
    data(){
        return {
            headerText : `J'ai joué au ${this.dle} sur http://kcdle.fr/ et voici mes résultats :\n`,
            resultText : "",
            hashtagText : "#KCORP",
        }
    },
    components: {
        simpleimg: () => import((`${window.global.JS_COMPONENT}simpleImg.js`)),
        btngame: () => import((`${window.global.JS_COMPONENT}btnGame.js`)),
    },
    template : `
        <div class="popup-gg">
            <div class="gg-text">Bravo ! Tu as trouvé le joueur</div>
            <div class="historique-visuel">
                {{ headerText }}
                <div style="display: flex; flex-direction: column-reverse">
                  <p v-for="(result, index) in resultTexts" :key="guess[index].Image">{{ result }}</p>
                </div>
                {{hashtagText}}
            </div>
            <div class="gg-text-link">Partage ta réussite sur les réseaux :</div>
            <simpleimg class="x_logo" :alt="'X'" :img="'x_logo.png'" @onclick="genererLienPartageTwitter"></simpleimg>
            <simpleimg class="x_logo" :alt="'Copier'" :img="'copy.png'" style="padding-left: 5px;" @onclick="genererCopy"></simpleimg>

            <div class="btn-container" style="height: auto; margin-top: 7%; font-size: 20px; font-weight: bold;">
                <div class="gg-text" style="margin-bottom: 2%">Découvrez aussi les autres dle !</div>
                <btngame v-for="btn in btndata" :key="btn.dle" :data="btn"></btngame>
            </div>
        </div>
    `,
    computed: {
        resultTexts() {
            return this.guess.map(joueur => this.createResultText(joueur));
        }
    },
    methods: {
        createResultText(joueur){
            let text = "";
            this.infobar.forEach(value => {
                if(joueur[value.key] === this.result[value.key]){
                    text += "🟩"
                }
                else{
                    text += "🟥"
                }
            });

            this.resultText =`${text}\n${this.resultText}`
            return text;
        },

        genererLienPartageTwitter(e) {
            const twitterShareLink = 'https://twitter.com/intent/tweet?text=' +
                encodeURIComponent(this.headerText + this.resultText + this.hashtagText);
            window.open(twitterShareLink, '_blank');
        },
        genererCopy(e){
            navigator.clipboard.writeText(this.headerText + this.resultText + this.hashtagText)
                .then(() => console.log("Texte copié avec succès !"))
                .catch(err => console.error("Erreur lors de la copie :", err))
        }
    },
    mounted() {
        this.$el.scrollIntoView({ behavior: 'smooth' });
        if(this.animation < 0) return;
        setTimeout(() => {
            this.$el.classList.add("fade-in")
        }, 500)
    }
}