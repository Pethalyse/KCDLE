export default {
    props: {
        infobar : Array,
        guess : Array,
        result: Object,
        dle: String,
    },
    data() {
        return {

        }
    },
    components : {
        simpleimg : () => import((`${window.global.JS_COMPONENT}simpleImg.js`)),

        paysimg : () => import((`${window.global.JS_COMPONENT}playerTabComponents/paysImg.js`)),
        arrowimg : () => import((`${window.global.JS_COMPONENT}playerTabComponents/arrowImg.js`)),
        jeuimg : () => import((`${window.global.JS_COMPONENT}playerTabComponents/jeuImg.js`)),
        joueurimg : () => import((`${window.global.JS_COMPONENT}playerTabComponents/joueurImg.js`)),
        teamimg : () => import((`${window.global.JS_COMPONENT}playerTabComponents/teamImg.js`)),
        roletext : () => import((`${window.global.JS_COMPONENT}playerTabComponents/roleText.js`)),
        roleimg: () => import((`${window.global.JS_COMPONENT}playerTabComponents/roleImg.js`)),
    },
    template: `
      <div class="theBody">
        <div class="infoNom">
          <div v-for="(info, index) in infobar" :key="info.key" class="divInfo">
            <div class="divText">{{ info.text }}</div>
            <hr data-v-4cd5ba35="">
            <div :class="info.class">
                <component v-for="joueur in guess" :key="joueur.Image" class="divInfoJoueur"
                    :is="info.component" 
                    :value="joueur[info.key]"
                    :option="optionArrowComponent(joueur[info.key], info.key)"
                    :animation="index"
                    :class="verificationJoueurXResult(joueur[info.key], info.key)"
                    @end_animation="handleEndAnimation(joueur)"
                >
                </component>
            </div>
          </div>

        </div>
        <p id="reponse"></p>
      </div>
    `,
    methods: {
        verificationJoueurXResult(joueurValue, key){
            return `${this.dle}_guess_${joueurValue === this.result[key]}`
        },
        optionArrowComponent(joueurValue, key){
            if(this.result[key] > joueurValue)
                return -1;
            else if(this.result[key] < joueurValue)
                return 1;
            else return 0;
        },
        handleEndAnimation(joueur){
            this.$emit("end_animation", joueur)
        }
    }
}