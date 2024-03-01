// Fonction pour supprimer les données à minuit
function nettoyerLocalStorage() {
    // Code pour nettoyer le localStorage
    localStorage.clear();
    console.log("LocalStorage nettoyé à", new Date().toLocaleString());
}

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
      console.log()

      if(n_maintenant > time){
          nettoyerLocalStorage();
          localStorage.setItem("resetKCdle", n_maintenant.getTime().toString());
      }else{
        if(!localStorage.getItem('lecdle') && verifierFinJeuLEC()){
          enleverHistoricJeuLEC();
          enleverFinJeuLEC();
        }
        
        if(!localStorage.getItem('lfldle') && verifierFinJeuLFL()){
          enleverHistoricJeuLFL();
          enleverFinJeuLFL();
        }
        
        if(!localStorage.getItem('kcdle') && verifierFinJeuLFL()){
          enleverHistoricJeuKC();
          enleverFinJeuKC();
        }
      }
}


function enregistrerActivite(key, activite)
{
    let activites = JSON.parse(localStorage.getItem(key)) || [];
    activites.push(activite);
    localStorage.setItem(key, JSON.stringify(activites));
}

function activitesEnregistrees(key){
    return localStorage.getItem(key) ? JSON.parse(localStorage.getItem(key)) : [];
}


//LFL

// Fonction pour enregistrer une activité LFL
function enregistrerActiviteLFL(activite)
{
    if(!!activite.Nationalite && !!activite.Date_Naissance && !!activite.Role && !!activite.Equipe && !!activite.Nom) {
        enregistrerActivite('lfldle', activite);
    }else{
        console.log("Manque une info dans l'enregistrement de l'historique")
    }

}

// Récupération des activités enregistrées LFL
function activitesEnregistreesLFL(){
    return activitesEnregistrees('lfldle');
}

function enregistrerFinJeuLFL(){
  if(!localStorage.getItem('finlfl')){
    ajouteTrouveLFL();
  }
    enregistrerActivite('finlfl', true);
}

async function ajouteTrouveLFL() {
    let t = '../src/Modele/Service/ajouteTrouveLfl';
    const req = await fetch(t)
}

function verifierFinJeuLFL(){
    return activitesEnregistrees('finlfl')[0] === true;
}

function enleverFinJeuLFL(){
    localStorage.removeItem('finlfl')
}

function enleverHistoricJeuLFL(){
    localStorage.removeItem('lfldle')
}

//LEC

function enregistrerActiviteLEC(activite)
{
    if(!!activite.Nationalite && !!activite.Date_Naissance && !!activite.Role && !!activite.Equipe && !!activite.Nom) {
        enregistrerActivite('lecdle', activite);
    }else{
        console.log("Manque une info dans l'enregistrement de l'historique")
    }

}

// Récupération des activités enregistrées LEC
function activitesEnregistreesLEC(){
    return activitesEnregistrees('lecdle');
}

function enregistrerFinJeuLEC(){
  if(!localStorage.getItem('finlec')){
    ajouteTrouveLEC();
  }
    enregistrerActivite('finlec', true);
}

async function ajouteTrouveLEC() {
    let t = '../src/Modele/Service/ajouteTrouveLec';
    const req = await fetch(t)
}

function verifierFinJeuLEC(){
    return activitesEnregistrees('finlec')[0] === true;
}

function enleverFinJeuLEC(){
    localStorage.removeItem('finlec')
}

function enleverHistoricJeuLEC(){
    localStorage.removeItem('lecdle')
}

//KC

function enregistrerActiviteKC(activite)
{
    if(!!activite.Nationalite && !!activite.Date_naissance && !!activite.Role && !!activite.TeamMaintenant && !!activite.TeamAvant && !!activite.Image && !!activite.Jeu && !!activite.Annee && !!activite.Titres >= 0 && !!activite.Pseudo) {
        enregistrerActivite('kcdle', activite);
    }else{
        console.log("Manque une info dans l'enregistrement de l'historique")
    }

}

// Récupération des activités enregistrées KC
function activitesEnregistreesKC(){
    return activitesEnregistrees('kcdle');
}

function enregistrerFinJeuKC(){
  if(!localStorage.getItem('finkc')){
    ajouteTrouveKC();
  }
    enregistrerActivite('finkc', true);
}

async function ajouteTrouveKC() {
    let t = '../src/Modele/Service/ajouteTrouveKc';
    const req = await fetch(t)
}

function verifierFinJeuKC(){
    return activitesEnregistrees('finkc')[0] === true;
}

function enleverFinJeuKC(){
    localStorage.removeItem('finkc')
}

function enleverHistoricJeuKC(){
    localStorage.removeItem('kcdle')
}