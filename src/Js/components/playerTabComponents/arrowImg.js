export default {
    props: {
        value : String|Number,
        option : Number,
        animation : {
            type : Number,
            default : -1,
        }
    },
    template : `
        <div>
          <simpleimg v-if="option !== 0" class="arrow" :alt="''" :img="'arrow-age.png'" :style="rotate()"></simpleimg>
          <p class="ageText" style="position: absolute;" :style="blackText()">{{value}}</p>
        </div>
    `,
    components: {
        simpleimg: () => import((`${window.global.JS_COMPONENT}simpleImg.js`)),
    },
    methods: {
        rotate(){
            return {rotate : `${90 * this.option}deg`}
        },
        blackText(){
            if(this.option === 0)
                return {color: "black"}
        },
    },
    mounted() {
        if(this.animation < 0) return;
        setTimeout(() => {
            this.$el.classList.add("fade-in")
        }, 100 + 500 * this.animation)
    }
}