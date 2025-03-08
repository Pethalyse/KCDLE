export default {
    props: {
        value: String,
        animation : {
            type : Number,
            default : -1,
        }
    },
    template : `
        <div>
          <simpleimg class="imgInfoJoueur teteImg" :alt="value" :img="'Joueurs/'+value"></simpleimg>
        </div>
    `,
    components: {
        simpleimg: () => import((`${window.global.JS_COMPONENT}simpleImg.js`)),
    },
    mounted() {
        if(this.animation < 0) return;
        setTimeout(() => {
            this.$el.classList.add("fade-in")
            this.$emit("end_animation")
        }, 100 + 500 * this.animation)
    }
}