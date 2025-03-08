export default{
    props: {
        img : String,
        alt : {
            type: String,
            default: '',
        }
    },
    template : `
        <img :alt="alt" :src="generateImg()" @click="handleClick"/>
    `,
    methods:{
        generateImg(){
            return `${window.global.BASE_PATH}ressources/images/${this.img}`
        },
        handleClick(e){
            this.$emit("onclick", e)
        }
    }
};