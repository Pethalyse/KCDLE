export default {
    props: {
        value : String,
        animation : {
            type : Number,
            default : -1,
        }
    },
    template : `
        <div>
          <simpleimg class="imgInfoJoueur jeuImg" :alt="value" :img="'LogoJeu/'+value"></simpleimg>
        </div>
    `,
    components: {
        simpleimg: () => import((`${window.global.JS_COMPONENT}simpleImg.js`)),
    },
    mounted() {
        if(this.animation < 0) return;
        setTimeout(() => {
            this.$el.classList.add("fade-in")
        }, 100 + 500 * this.animation)
    }
}