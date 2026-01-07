export default{

    components: {
        simpleimg: () => import((`${window.global.JS_COMPONENT}simpleImg.js`)),
    },
    template : `
        <div class="credit">
            <div class="containt-containt-credit">
                <div class="containt-credit" v-for="user in users" :key="user.twitter">
                    <a :href="'https://twitter.com/'+user.twitter" target="_blank">
                        <simpleimg :alt="''" :img="user.img"></simpleimg>
                        {{user.text}}
                    </a>
                </div>
            </div>
        </div>
    `,
};