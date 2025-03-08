export default {
    props: {
        value : String,
        option: Number,
        animation : {
            type : Number,
            default : -1,
        }
    },
    template : `
        <div>
          <p class="roleText" style="position: absolute;" :style="colorText()">{{value}}</p>
        </div>
    `,
    methods: {
        colorText(){
            if(this.option === 0)
                return {color: "black"}
            return {color: "white"}
        },
    },
    mounted() {
        if(this.animation < 0) return;
        setTimeout(() => {
            this.$el.classList.add("fade-in")
        }, 100 + 500 * this.animation)
    }
}