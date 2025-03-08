export default{
    components: {
        simpleimg: () => import((`${window.global.JS_COMPONENT}simpleImg.js`)),
    },
    data(){
        return {
            users : [
                {
                    twitter : "Pethalyse",
                    img : "credits/yanhis-rond.png",
                    text : "Yanhis (dev)",
                },
                {
                    twitter : "angel_mln",
                    img : "credits/angel-rond.png",
                    text : "Angel",
                },
                {
                    twitter : "Lucky30__",
                    img : "credits/lucky-rond.png",
                    text : "Lucky",
                },
                {
                    twitter : "ben_thoo",
                    img : "credits/bentho-rond.png",
                    text : "Bentho",
                }
            ]
        }
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

