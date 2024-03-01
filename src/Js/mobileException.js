// Vérifie si l'écran est de taille mobile
function estMobile() {
    return window.innerWidth <= 1280;
}

// Si l'écran est de taille mobile, attachez les écouteurs d'événements
if (estMobile()) {
    let container = document.getElementById("infoNom");
    let startX;
    let startY;

    container.addEventListener("touchstart", function(event) {
        startX = event.touches[0].clientX;
        startY = event.touches[0].clientY;
    });

    container.addEventListener("touchmove", function(event) {
        let currentX = event.touches[0].clientX;
        let currentY = event.touches[0].clientY;
        let deltaX = currentX - startX;
        let deltaY = currentY - startY;

        if (Math.abs(deltaX) > Math.abs(deltaY)) {
            event.preventDefault();
            container.scrollLeft -= deltaX;
        }
    });
}
