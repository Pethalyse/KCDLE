export default {
    props: {
        dle: String,
        joueurs: Array,
        unwrittable : Boolean,
    },
    data(){
        return {
            joueursFiltres : [],
            inputValue : "",
        }
    },
    template: `
      <div>
        <div class="search-bar" :style="initBackgroundImage()">
          <input :disabled="unwrittable" class="sub" type="text" :placeholder="selectPlaceholder()" autofocus 
                 v-model="inputValue"
                 @input="handleInputBar"
                 @keyup.enter="handleClickCard(joueursFiltres.length > 0 ? joueursFiltres[0] : null)">
        </div>
        <div id="search">
            <playercard v-for="joueur in joueursFiltres"
                :key="joueur.Image"
                :joueur="joueur"
                @click_card="handleClickCard"
            ></playercard>
        </div>
      </div>
    `,
    components: {
        playercard : () => import((`${window.global.JS_COMPONENT}playerCard.js`)),
    },
    methods: {
        initBackgroundImage() {
            return {
                backgroundImage: `url('${window.global.BASE_PATH}ressources/images/${this.dle}_texte.png')`
            }
        },

        selectPlaceholder(){
            switch (this.dle) {
                case "KCDLE" : return "Entrez le nom d'un membre qui est/a été à la KarmineCorp";
                case "LECDLE" : return "Entrez le nom d'un joueur de LEC";
                case "LFLDLE" : return "Entrez le nom d'un joueur de LFL";
            }
        },

        handleInputBar(e)
        {
            const value = e.target.value;
            if(!value) {
                while (this.joueursFiltres.length > 0) {
                    this.joueursFiltres.pop();
                }
                return;
            }

            this.joueursFiltres = this.joueurs.filter(joueur => {
                return joueur.Pseudo.toLowerCase().startsWith(value.toLowerCase());
            });
        },

        handleClickCard(e){
            if(!e) return
            this.$emit("click_card", e);
            this.inputValue = "";
            while (this.joueursFiltres.length > 0) {
                this.joueursFiltres.pop();
            }
        }
    },
};

