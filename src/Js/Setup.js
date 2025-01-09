//////////////////////////////HISTORIQUE//////////////////////////////////////
function planifierNettoyage() {
    // Obtenir l'heure actuelle
    let fuseauCible = 'Europe/Paris';

    if(!localStorage.getItem("resetKCdle")){
        localStorage.setItem("resetKCdle", new Date(0).getTime().toString());
    }

    const saveTime = new Date(Number(localStorage.getItem("resetKCdle")));
    const time = new Date(saveTime.getFullYear(), saveTime.getMonth(), saveTime.getDate(), 0, 0, 0);
    const saveMtnTime = new Date();
    const horaire = saveMtnTime.toLocaleString('fr-FR', { timeZone: fuseauCible }).split(" ")[0].split("/")
    const n_maintenant = new Date(parseInt(horaire[2]), parseInt(horaire[1])-1, parseInt(horaire[0]), 0, 0, 0);

    if(n_maintenant > time){
        nettoyerLocalStorage();
        localStorage.setItem("resetKCdle", n_maintenant.getTime().toString());
    }else{
        if(!localStorage.getItem('lecdle') && verifierFinJeu("lec")){
            localStorage.removeItem('lecdle')
            localStorage.removeItem('finlec')
        }

        if(!localStorage.getItem('lfldle') && verifierFinJeu("lfl")){
            localStorage.removeItem('lfldle')
            localStorage.removeItem('finlfl')
        }

        if(!localStorage.getItem('kcdle') && verifierFinJeu("kc")){
            localStorage.removeItem('kcdle')
            localStorage.removeItem('finkc')
        }
    }
}

// Fonction pour supprimer les données à minuit
function nettoyerLocalStorage() {
    // Code pour nettoyer le localStorage
    localStorage.clear();
    console.log("LocalStorage nettoyé à", new Date().toLocaleString());
}

function enregistrerActivite(key, activite)
{
    let activites = JSON.parse(localStorage.getItem(key)) || [];
    activites.push(activite);
    localStorage.setItem(key, JSON.stringify(activites));
}

function enregistrerActiviteDle(activite, dle)
{
    enregistrerActivite(dle+'dle', activite);
}
function enregistrerFinJeu(dle){
    enregistrerActivite('fin'+dle, true);
}
function activitesEnregistrees(key){
    return localStorage.getItem(key) ? JSON.parse(localStorage.getItem(key)) : [];
}
function verifierFinJeu(dle){
    return activitesEnregistrees('fin'+dle)[0] === true;
}

function activitesEnregistreesDle(dle){
    return activitesEnregistrees(dle+'dle');
}

//////////////////////////////////////////////////////////////////////////////


////////////////////////////ASYNCR///////////////////////////////////////////

async function search(s, dle)
{
    let t = '../src/Modele/Service/serviceFrontal.php/' +dle+ 'Search/'+s;
    const req = await fetch(t)
    return await req.json();
}

async function guess(s, fake, dle)
{
    let t = '../src/Modele/Service/serviceFrontal.php/'+dle+'Guess/'+s+'/'+fake;
    const req = await fetch(t)
    return await req.json();
}

async function recupFromNom(s, dle)
{
    let t = '../src/Modele/Service/serviceFrontal.php/'+dle+'FromNom/'+s;
    const req = await fetch(t)
    return await req.json();
}

async function recupFromNomTab(s, dle)
{
    let t = '../src/Modele/Service/serviceFrontal.php/'+dle+'FromNomTab/'+s;
    const req = await fetch(t)
    return await req.json();
}

async function multiGuess(s, dle)
{
    let t = '../src/Modele/Service/serviceFrontal.php/'+dle+'MultiGuess/'+s;
    const req = await fetch(t)
    return await req.json();
}
/////////////////////////////////////////////////////////////////////////////

///////////////////////////////FUNCTIONS////////////////////////////////////
function createOption(dle)
{
    const itemSub = document.querySelector(".sub");
    let sub = itemSub.value;
    // document.querySelector("#search").innerHTML = "";

    if(sub.length > 0)
    {
        let histo = activitesEnregistreesDle(dle);

        search(sub, dle).then((l) => {
            document.querySelector("#search").innerHTML = "";
            for(const post of l)
            {
                let ver = true;
                for(const j of histo)
                {
                    if(dle !== "kc" && j === post.Nom)
                    {
                        ver = false;
                        break;
                    }
                    else if(dle === "kc" && j === post.Pseudo)
                    {
                        ver = false;
                        break;
                    }
                }

                if(ver){
                    let containt = document.createElement("div");

                    let img = document.createElement("img");
                    let div = document.createElement("div");
                    let nom = "";
                    if(dle !== "kc"){
                        let equipe = post.Equipe.replace(".png", "");
                        img.src = "../ressources/images/"+equipe+"/"+post.Nom;
                        div.innerHTML = post.Nom.replace(".png", "").replace("_", " ");
                        nom = post.Nom;

                    }else{
                        img.src = "../ressources/images/KCDLE/"+post.Image;
                        div.innerHTML = post.Pseudo;
                        nom = post.Pseudo;
                    }

                    containt.appendChild(img);
                    containt.appendChild(div);
                    containt.className = "containt"
                    containt.onclick = function(){
                        enregistrerActiviteDle(nom, dle);
                        verification(nom, dle);
                    }
                    document.querySelector("#search").appendChild(containt);
                }
            }
        })
    }
    else
    {
        document.querySelector("#search").innerHTML = "";
    }
}

function verification(i, dle, fake = "nofake"){
    const sub = document.querySelector(".sub");
    const search = document.querySelector("#search");

    if(sub){sub.value = ""; sub.disabled = true;}
    if(search){search.innerHTML = "";}

    guess(i, fake, dle).then((resulat) => {
        recupFromNom(i, dle).then((info) => {
            appelVerification(resulat, info, dle)
        })
    })
}

function appelVerification(resultat, info, dle){
    const sub = document.querySelector(".sub");

    let colorW = "";
    let colorL = "";
    if(dle === "kc"){
        colorW = "#b7fffa";
        colorL = "#003b94";
    }else if(dle === "lfl"){
        colorW = "#b7fffa";
        colorL = "#F47857";
    }else if(dle === "lec"){
        colorW = "#b7fffa";
        colorL = "rgb(88 34 174)";
    }

    let time = 100;

    //pays
    let img = document.createElement("img");
    img.src = "../ressources/images/LogoPays/"+info.Nationalite;

    img.className = "imgInfoJoueur paysImg";


    let div3 = document.createElement("div");
    div3.appendChild(img);
    div3.className = "divInfoJoueur";

    if(resultat.Nationalite){
        div3.style.backgroundColor = colorW;
    }else{
        div3.style.backgroundColor = colorL;
    }

    document.querySelector(".nationalite").prepend(div3);
    setTimeout(function() {
        div3.classList.add("fade-in");
    }, time);
    time += 500;


    //age
    let p = document.createElement("p");
    p.innerHTML = info.Date_Naissance.toString();
    p.className = "ageText";
    p.style.position = "absolute";

    let div4 = document.createElement("div");

    if(resultat.Date_Naissance === 0){
        div4.style.backgroundColor = colorW;
        p.style.color = "black"
    }else{
        img = document.createElement("img");
        img.src = "../ressources/images/arrow-age.png";
        img.className = "arrow";
        div4.style.backgroundColor = colorL;
        if(resultat.Date_Naissance === 1)
        {
            img.style.rotate = "-90deg";
        }else{
            img.style.rotate = "90deg";
        }
        div4.appendChild(img);
    }

    div4.appendChild(p);
    div4.className = "divInfoJoueur"
    document.querySelector(".age").prepend(div4);
    setTimeout(function() {
        div4.classList.add("fade-in");
    }, time);
    time += 500;

    //JEU
    if(dle === "kc"){
        img = document.createElement("img");
        img.src =  "../ressources/images/kcdle/" + info.Jeu;
        img.alt = info.Jeu;

        img.className = "imgInfoJoueur jeuImg";

        let div7 = document.createElement("div");
        div7.appendChild(img);
        div7.className = "divInfoJoueur"
        if(resultat.Jeu){
            div7.style.backgroundColor = colorW;
        }else{
            div7.style.backgroundColor = colorL;
        }
        document.querySelector(".jeu").prepend(div7);
        setTimeout(function() {
            div7.classList.add("fade-in");
        }, time);
        time += 500;

        //Rejoint
        let p2 = document.createElement("p");
        p2.innerHTML = info.Annee;
        p2.className = "rejointText";
        p2.style.position = "absolute";

        let div8 = document.createElement("div");

        if(resultat.Annee === 0){
            div8.style.backgroundColor = colorW;
            p2.style.color = "black"
        }else{
            img = document.createElement("img");
            img.src = "../ressources/images/arrow-age.png";
            img.className = "arrow";
            div8.style.backgroundColor = colorL;
            if(resultat.Annee === 1)
            {
                img.style.rotate = "-90deg";
            }else{
                img.style.rotate = "90deg";
            }
            div8.appendChild(img);
        }

        div8.appendChild(p2);
        div8.className = "divInfoJoueur"
        document.querySelector(".arrivee").prepend(div8);
        setTimeout(function() {
            div8.classList.add("fade-in");
        }, time);
        time += 500;

        //TITRE
        let p3 = document.createElement("p");
        p3.innerHTML = info.Titres;
        p3.className = "nbTitreText";
        p3.style.position = "absolute";

        let div9 = document.createElement("div");

        if( resultat.Titres === 0){
            div9.style.backgroundColor = colorW;
            p3.style.color = "black"
        }else{
            img = document.createElement("img");
            img.src = "../ressources/images/arrow-age.png";
            img.className = "arrow";
            div9.style.backgroundColor = colorL;
            if(resultat.Titres === 1)
            {
                img.style.rotate = "-90deg";
            }else{
                img.style.rotate = "90deg";
            }
            div9.appendChild(img);
        }

        div9.appendChild(p3);
        div9.className = "divInfoJoueur"
        document.querySelector(".titres").prepend(div9);
        setTimeout(function() {
            div9.classList.add("fade-in");
        }, time);
        time += 500;

        //EquipeAVANT
        img = document.createElement("img");
        img.src = "../ressources/images/LogoEquipe/"+info.TeamAvant;
        img.alt = info.TeamAvant;

        img.className = "imgInfoJoueur";

        let div5 = document.createElement("div");
        div5.appendChild(img);
        div5.className = "divInfoJoueur"
        if(resultat.TeamAvant){
            div5.style.backgroundColor = colorW;
        }else{
            div5.style.backgroundColor = colorL;
        }
        document.querySelector(".avantkc").prepend(div5);
        setTimeout(function() {
            div5.classList.add("fade-in");
        }, time);
        time += 500;

        //EquipeMAINTENANT
        img = document.createElement("img");
        img.src = "../ressources/images/LogoEquipe/"+info.TeamMaintenant;
        img.alt = info.TeamMaintenant;

        img.className = "imgInfoJoueur";

        let div15 = document.createElement("div");
        div15.appendChild(img);
        div15.className = "divInfoJoueur"
        if(resultat.TeamMaintenant){
            div15.style.backgroundColor = colorW;
        }else{
            div15.style.backgroundColor = colorL;
        }
        document.querySelector(".maintenant").prepend(div15);
        setTimeout(function() {
            div15.classList.add("fade-in");
        }, time);
        time += 500;
    }


    //Role

    let div6 = document.createElement("div");
    if(dle === "kc"){
        let p15 = document.createElement("p");
        p15.innerHTML = info.Role;
        p15.style.position = "absolute";
        p15.className = "roleText";

        if(resultat.Role)
            p15.style.color = "black"

        div6.appendChild(p15);
    }else{
        img = document.createElement("img");
        img.src =  "../ressources/images/LogoRole/" + info.Role;
        img.className = "imgInfoJoueur roleImg";

        div6.appendChild(img);
    }

    div6.className = "divInfoJoueur"
    if(resultat.Role){
        div6.style.backgroundColor = colorW;
    }else{
        div6.style.backgroundColor = colorL;
    }
    document.querySelector(".role").prepend(div6);
    setTimeout(function() {
        div6.classList.add("fade-in");
    }, time);
    time += 500

    //Equipe
    if(dle !== "kc"){
        img = document.createElement("img");
        img.src = "../ressources/images/LogoEquipe/"+info.Equipe;

        img.className = "imgInfoJoueur";

        let div5 = document.createElement("div");
        div5.appendChild(img);
        div5.className = "divInfoJoueur"
        if(resultat.Equipe){
            div5.style.backgroundColor = colorW;
        }else{
            div5.style.backgroundColor = colorL;
        }
        document.querySelector(".equipe").prepend(div5);
        setTimeout(function() {
            div5.classList.add("fade-in");
        }, time);
        time += 500;
    }


    //tete du gars
    let nom = false;
    if(dle === "kc"){
        img = document.createElement("img");
        img.src = "../ressources/images/KCDLE/"+info.Image;
        img.alt = info.Pseudo;
        nom = resultat.Image;
    }else{
        img = document.createElement("img");
        let equipe = info.Equipe.replace(".png", "");
        img.src = "../ressources/images/"+equipe+"/"+info.Image;
        img.alt = info.Nom;
        nom = resultat.Nom;
    }

    img.className = "imgInfoJoueur teteImg";

    let div2 = document.createElement("div");
    div2.appendChild(img);
    div2.className = "divInfoJoueur"
    if(nom){
        div2.style.backgroundColor = colorW;
    }else{
        div2.style.backgroundColor = colorL;
    }

    document.querySelector(".joueur").prepend(div2);
    setTimeout(function() {
        div2.classList.add("fade-in");
        if(nom)
        {
            enregistrerFinJeu(dle);
            finJeu(dle);
            creerLink(dle).then()
        }
    }, time);

    if(nom){
        if(sub){sub.disabled = true;}
    }
    else{
        if(sub){
            sub.disabled = false;
            sub.focus()
        }
    }
}

async function creerLink(dle){

    let historic = activitesEnregistreesDle(dle);

    let div1 = document.createElement("div");
    div1.className = "popup-gg";

    let div2 = document.createElement("div");
    div2.className = "gg-text";
    div2.innerHTML = "Bravo ! Tu as trouvé le joueur";

    let div3 = document.createElement("div");
    div3.className = "gg-text-link";
    div3.innerHTML = "Partage ta réussite sur les réseaux :";

    let img = document.createElement('img');
    img.className = "x_logo";
    img.src="../ressources/images/x_logo.png"

    creerText(historic, dle).then(r => {
        img.onclick = function(){
            genererLienPartageTwitter(r)
        }
    })

    let img2 = document.createElement('img');
    img2.className = "x_logo";
    img2.src="../ressources/images/copy.png"
    img2.style.paddingLeft = "5px"

    creerText(historic, dle).then(r => {
        img2.onclick = function () {
            genererCopy(r)
        }
    })

    let div4 = document.createElement("div");
    div4.className = "historique-visuel";
    div4.innerHTML = (await creerText(historic, dle)).replaceAll("\n", "<br>");

    div1.appendChild(div2);
    div1.appendChild(div4);
    div1.appendChild(div3);
    div1.appendChild(img);
    div1.appendChild(img2);


    setTimeout(function() {
        div1.classList.add("fade-in");
    }, 500);

    document.querySelector('main').appendChild(div1)
    div1.scrollIntoView({ behavior: 'smooth' });
}
////////////////////////////////////////////////////////////////////////////

async function processHistoric(historic, dle) {

    if(historic.length > 0){
        let tab = "";
        const sub = document.querySelector(".sub");
        sub.disabled = true;

        document.getElementById("loading").style.display = "block";
        for (const histo of historic) {
            tab += histo + " ";
        }

        recupFromNomTab(tab, dle).then(values => {
            multiGuess(tab, dle).then(guess =>{
                if(values.length === guess.length){

                    document.getElementById("loading").style.display = "none";
                    sub.disabled = false;

                    for(let i = 0; i<values.length; i++){
                        appelVerification(guess[i], values[i], dle);
                    }

                }else{
                    console.error("Error ! DM Pethalyse !")
                }
            })
        })
    }

}

function setup(dle){

    processHistoric(activitesEnregistreesDle(dle), dle).then();

    if(verifierFinJeu(dle)){
        finJeu(dle);
    }
    let inputTimeout;
    if(document.querySelector(".sub")){ document.querySelector(".sub").addEventListener("input", function(){
        clearTimeout(inputTimeout);
        inputTimeout = setTimeout(function () {
            // Appeler la fonction après 1 seconde sans nouvelle saisie
            createOption(dle);
        }, 100); // 1000 ms = 1 seconde
    }
    )}
    if(document.querySelector(".sub")){ document.querySelector(".sub").addEventListener("keypress", function(event) {
        // If the user presses the "Enter" key on the keyboard
        if (event.key === "Enter") {
            clearTimeout(inputTimeout);
            const search = document.querySelector("#search");
            if(search && search.children.length > 0){
                search.children.item(0).click();
            }

        }
    })}
}

function finJeu(dle) {

    if(document.querySelector(".search-bar-"+dle)){
        document.querySelector(".search-bar-"+dle).remove();
    }

}

/////////////////////////////////////////////////////PARTAGE DE LIEN//////////////////////////////////////////

function genererLienPartageTwitter(r) {

    // Construire le lien de partage sur Twitter
    const twitterShareLink = 'https://twitter.com/intent/tweet?text=' + encodeURIComponent(r);

    // Ouvrir la fenêtre de partage sur Twitter
    window.open(twitterShareLink, '_blank');
}

async function creerText(histo, dle){
    let tweetText = "J'ai joué au " +dle.toUpperCase() + "dle sur http://kcdle.fr/ et voici mes résultats : \n";
    if(dle === "kc"){
        tweetText = await creerLienPartageKC(histo, tweetText, dle)
    }else{
        tweetText = await creerLienPartage(histo, tweetText, dle)
    }

    return tweetText;
}

async function creerLienPartage(histo, tweetText, dle) {
    for (const p of histo) {
        await guess(p, "fake", dle).then((post) => {
            if (post.Nationalite) {
                tweetText += "🟩";
            } else {
                tweetText += "🟥";
            }

            if (post.Date_Naissance === 0) {
                tweetText += "🟩";
            } else {
                tweetText += "🟥";
            }

            if (post.Role) {
                tweetText += "🟩";
            } else {
                tweetText += "🟥";
            }


            if (post.Equipe) {
                tweetText += "🟩";
            } else {
                tweetText += "🟥";
            }


            if (post.Nom) {
                tweetText += "🟩";
            } else {
                tweetText += "🟥";
            }

            tweetText += "\n";

        })
    }

    tweetText += "#KCORP"

    return tweetText;
}

async function creerLienPartageKC(histo, tweetText, dle) {
    for (const p of histo) {
        await guess(p, "fake", dle).then((post) => {
            if (post.Nationalite) {
                tweetText += "🟩";
            } else {
                tweetText += "🟥";
            }

            if (post.Date_Naissance === 0) {
                tweetText += "🟩";
            } else {
                tweetText += "🟥";
            }

            if (post.Jeu) {
                tweetText += "🟩";
            } else {
                tweetText += "🟥";
            }

            if (post.Annee === 0) {
                tweetText += "🟩";
            } else {
                tweetText += "🟥";
            }

            if (post.Titres === 0) {
                tweetText += "🟩";
            } else {
                tweetText += "🟥";
            }

            if (post.TeamAvant) {
                tweetText += "🟩";
            } else {
                tweetText += "🟥";
            }

            if (post.TeamMaintenant) {
                tweetText += "🟩";
            } else {
                tweetText += "🟥";
            }

            if (post.Role) {
                tweetText += "🟩";
            } else {
                tweetText += "🟥";
            }

            if (post.Image) {
                tweetText += "🟩";
            } else {
                tweetText += "🟥";
            }

            tweetText += "\n";

        })
    }

    tweetText += "#KCORP"

    return tweetText;
}

function genererCopy(text){

    try {

        let area = document.createElement("textarea");
        area.value = text
        document.body.appendChild(area);
        // Ajouter le texte dans le presse-papiers
        area.select()
        document.execCommand("copy");
        document.body.removeChild(area);

        alert("Texte ajouté dans le presse-papiers !");
    } catch (err) {
        console.error("Erreur lors de l'ajout dans le presse-papiers : ", err);
    }
}

function getAge(date) {
    const diff = Date.now() - date.getTime();
    const age = new Date(diff);
    age.setDate(age.getDate() + 1)
    return Math.abs(age.getUTCFullYear() - 1970);
}

function updateStorage(dle){
    let histo = activitesEnregistreesDle(dle);
    let new_histo = [];
    histo.forEach(r => {
        new_histo.push(r.includes(".png") ? r : r+".png");
    })

    localStorage.setItem(dle+"dle", JSON.stringify(new_histo));
}