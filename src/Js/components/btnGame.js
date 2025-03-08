export default{
    props: {
        data : Object
    },
    components: {
        simpleimg: () => import((`${window.global.JS_COMPONENT}simpleImg.js`)),
    },
    template : `
        <div class="btn-game" @click="goGame()" v-if="data.active">
          <simpleimg :alt="data.dle" :img="data.dle + '_Barre.png'"></simpleimg>
        </div>
    `,
    methods:{
        goGame(){
            window.location.href = this.data.url;
        },
    }
};