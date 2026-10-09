/* ==========================================================================
   Composant Barre de Recherche pour Popups / Modales - JavaScript
   ========================================================================== */

/**
 * Filtre les éléments d'une liste de popup en fonction d'un terme et d'un sélecteur cible.
 *
 * @param {string} term Le terme recherché
 * @param {string} targetSelector Sélecteur CSS des éléments à filtrer (ex: '.modal-recipe-form')
 */
function filterPopupList(term, targetSelector) {
    if (!targetSelector) return;
    var normalized = (term || "").trim().toLowerCase();
    var items = document.querySelectorAll(targetSelector);
    items.forEach(function (item) {
        var name = (item.dataset.name || item.textContent || "").toLowerCase();
        item.style.display = name.includes(normalized) ? "" : "none";
    });
}

/**
 * Bascule l'état ouvert/fermé de la recherche extensible.
 *
 * @param {string} wrapperId ID de l'élément conteneur
 * @param {string} inputId ID du champ input
 */
function toggleExpandableSearch(wrapperId, inputId) {
    var wrapper = document.getElementById(wrapperId);
    var input = document.getElementById(inputId);
    if (!wrapper || !input) return;

    var isOpen = wrapper.classList.contains("is-open");
    if (!isOpen) {
        wrapper.classList.add("is-open");
        setTimeout(function () {
            input.focus();
        }, 150);
    } else {
        if (!input.value || input.value.trim() === "") {
            wrapper.classList.remove("is-open");
            wrapper.classList.remove("has-value");
        } else {
            input.focus();
        }
    }
}

/**
 * Gère le changement de contenu dans l'input (pour afficher ou cacher la croix).
 *
 * @param {HTMLInputElement} input
 * @param {string} wrapperId
 */
function handleSearchInputChange(input, wrapperId) {
    var wrapper = document.getElementById(wrapperId);
    if (!wrapper) return;
    if (input.value && input.value.length > 0) {
        wrapper.classList.add("has-value");
    } else {
        wrapper.classList.remove("has-value");
    }
}

/**
 * Efface le texte recherché et referme l'input extensible.
 *
 * @param {string} wrapperId
 * @param {string} inputId
 * @param {Function} [onClearCallback]
 * @param {string} [targetSelector]
 */
function clearExpandableSearch(wrapperId, inputId, onClearCallback, targetSelector) {
    var wrapper = document.getElementById(wrapperId);
    var input = document.getElementById(inputId);
    if (input) {
        input.value = "";
    }
    if (wrapper) {
        wrapper.classList.remove("has-value");
        wrapper.classList.remove("is-open");
    }
    if (typeof onClearCallback === "function") {
        onClearCallback();
    }
    if (targetSelector && typeof filterPopupList === "function") {
        filterPopupList("", targetSelector);
    }
}

/* Fermer la recherche au clic extérieur si le champ est vide */
document.addEventListener("click", function (e) {
    document.querySelectorAll(".modal-search-expandable.is-open").forEach(function (wrapper) {
        var input = wrapper.querySelector(".modal-search-input");
        if (input && (!input.value || input.value.trim() === "")) {
            if (!wrapper.contains(e.target)) {
                wrapper.classList.remove("is-open");
                wrapper.classList.remove("has-value");
            }
        }
    });
});
