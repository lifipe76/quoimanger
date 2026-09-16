document.addEventListener("DOMContentLoaded", function () {
    const container = document.getElementById("userMenuContainer");
    const popup = document.getElementById("userPopupMenu");
    const avatarImg = document.getElementById("avatarMenuImg");

    if (!container || !popup) {
        return;
    }

    // Toggle menu au clic (notamment sur support tactile / mobile)
    let isTouch = false;
    container.addEventListener(
        "touchstart",
        function () {
            isTouch = true;
        },
        { passive: true },
    );

    if (avatarImg) {
        avatarImg.addEventListener("click", function (e) {
            // Sur mobile / tactile, un premier clic ouvre le popup au lieu de déconnecter immédiatement
            if (isTouch && !popup.classList.contains("is-open")) {
                e.preventDefault();
                popup.classList.add("is-open");
            }
        });
    }

    // Fermeture si on clique en dehors
    document.addEventListener("click", function (event) {
        if (!container.contains(event.target)) {
            popup.classList.remove("is-open");
        }
    });

    // Fermeture avec la touche Échap
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape" && popup.classList.contains("is-open")) {
            popup.classList.remove("is-open");
        }
    });

    // Fallback SVG si FontAwesome n'est pas chargé sur la page
    const checkAndInjectIcons = function () {
        const logoutIcons = document.querySelectorAll("i.fa-sign-out-alt");
        logoutIcons.forEach((icon) => {
            if (
                !icon.innerHTML.trim() &&
                window.getComputedStyle(icon, ":before").content === "none"
            ) {
                icon.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle;"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>`;
            }
        });

        const userIcons = document.querySelectorAll("i.fa-user-gear");
        userIcons.forEach((icon) => {
            if (
                !icon.innerHTML.trim() &&
                window.getComputedStyle(icon, ":before").content === "none"
            ) {
                icon.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle;"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>`;
            }
        });
    };

    setTimeout(checkAndInjectIcons, 100);
});
