export default{
    props: {
        kcdle: {
            type: Boolean,
            default: true,
        },
        lfldle: {
            type: Boolean,
            default: true,
        },
        lecdle: {
            type: Boolean,
            default: true,
        },
    },
    components: {
        simpleimg: () => import((`${window.global.JS_COMPONENT}simpleImg.js`)),
    },
    template : `
        <div class="btn-game" @click="goGame()">
          <simpleimg :alt="data.dle" :img="data.dle + '_Barre.png'"></simpleimg>
        </div>
    `,
    methods:{
        goGame(){
            window.location.href = this.data.url;
        },
    }
};