/**
 * Standalone Vanilla JS Controller toggle-password (compatible assets/controllers)
 * Permet d'afficher/masquer le mot de passe avec les icônes SVG
 */
(function () {
    const visibleIcon = `<svg xmlns="http://www.w3.org/2000/svg" class="toggle-password-icon" viewBox="0 0 20 20" fill="currentColor">
<path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
<path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
</svg>`;

    const hiddenIcon = `<svg xmlns="http://www.w3.org/2000/svg" class="toggle-password-icon" viewBox="0 0 20 20" fill="currentColor">
<path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" clip-rule="evenodd" />
<path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z" />
</svg>`;

    function initTogglePassword(input) {
        if (!input || input.dataset.togglePasswordInitialized) return;
        input.dataset.togglePasswordInitialized = "true";

        if (input.parentElement) {
            input.parentElement.classList.add("toggle-password-container");
        }

        const button = document.createElement("button");
        button.type = "button";
        button.className = "toggle-password-button toggle-password-btn";
        button.setAttribute("tabindex", "-1");
        button.setAttribute("aria-label", "Afficher ou masquer le mot de passe");
        button.setAttribute("title", "Afficher ou masquer le mot de passe");
        button.innerHTML = visibleIcon;

        let isDisplayed = false;
        button.addEventListener("click", function (event) {
            event.preventDefault();
            isDisplayed = !isDisplayed;
            input.type = isDisplayed ? "text" : "password";
            input.setAttribute("type", isDisplayed ? "text" : "password");
            button.innerHTML = isDisplayed ? hiddenIcon : visibleIcon;
            const label = isDisplayed ? "Masquer le mot de passe" : "Afficher le mot de passe";
            button.setAttribute("title", label);
            button.setAttribute("aria-label", label);
        });

        input.insertAdjacentElement("afterend", button);
    }

    function scanInputs() {
        document.querySelectorAll('[data-controller="toggle-password"]').forEach(initTogglePassword);
    }

    // Compatibilité globale avec onclick="togglePasswordVisibility(id, this)"
    window.togglePasswordVisibility = function (inputId, btn) {
        const input = typeof inputId === "string" ? document.getElementById(inputId) : inputId;
        if (!input) return;

        const isPassword = input.type === "password";
        input.type = isPassword ? "text" : "password";
        input.setAttribute("type", isPassword ? "text" : "password");

        const icon = btn ? (btn.querySelector("i") || (btn.tagName === "I" ? btn : null)) : null;
        if (icon) {
            if (isPassword) {
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            } else {
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            }
        }

        const svg = btn ? btn.querySelector("svg") : null;
        if (svg) {
            btn.innerHTML = isPassword ? hiddenIcon : visibleIcon;
        }

        if (btn) {
            const label = isPassword ? "Masquer le mot de passe" : "Afficher le mot de passe";
            btn.setAttribute("title", label);
            btn.setAttribute("aria-label", label);
        }
    };

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", scanInputs);
    } else {
        scanInputs();
    }

    // Observer pour le cas où le DOM est modifié dynamiquement
    if (window.MutationObserver) {
        const observer = new MutationObserver(function () {
            scanInputs();
        });
        observer.observe(document.documentElement || document.body, {
            childList: true,
            subtree: true
        });
    }
})();
