export default {
    props: {
        joueur : Object
    },
    template: `
      <div class="containt" @click="handleClickCard">
          <simpleimg :alt="joueur.Pseudo" :img="'Joueurs/'+joueur.Image"></simpleimg>
          <div>{{joueur.Pseudo}}</div>
      </div>
    `,
    components : {
        simpleimg : () => import((`${window.global.JS_COMPONENT}simpleImg.js`)),
    },
    methods : {
        handleClickCard(e)
        {
            this.$emit("click_card", this.joueur);
        }
    }
}
