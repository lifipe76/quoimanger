/* ==========================================================================
   Composant Barre de Recherche pour Popups / Modales & Pages - JavaScript
   ========================================================================== */

/**
 * Normalise une chaîne pour comparaison insensible aux accents et à la casse.
 *
 * @param {string} str
 * @returns {string}
 */
function normalizeSearchText(str) {
    return (str || "")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLowerCase()
        .trim();
}

/**
 * Filtre les éléments d'une liste ou grille en fonction d'un terme et d'un sélecteur cible.
 *
 * @param {string} term Le terme recherché
 * @param {string} targetSelector Sélecteur CSS des éléments à filtrer (ex: '.card-recette', '.card-ingredient')
 */
function filterPopupList(term, targetSelector) {
    if (!targetSelector) return;
    var normalized = normalizeSearchText(term);
    var items = document.querySelectorAll(targetSelector);
    var visibleCount = 0;

    items.forEach(function (item) {
        var name = normalizeSearchText(item.dataset.name || item.textContent || "");
        var match = normalized === "" || name.includes(normalized);
        item.style.display = match ? "" : "none";
        if (match) visibleCount++;
    });

    var container = items.length > 0 ? items[0].parentElement : null;
    if (container) {
        var cleanId = targetSelector.replace(/[^a-zA-Z0-9_-]/g, "");
        var emptyMsg = container.querySelector(".empty-search-message-" + cleanId);
        if (!emptyMsg && items.length > 0) {
            emptyMsg = document.createElement("div");
            emptyMsg.className = "empty-search-message empty-search-message-" + cleanId + " card";
            emptyMsg.style.cssText = "grid-column: 1 / -1; width: 100%; text-align: center; padding: 2rem 1rem; color: #64748b; font-weight: 500; font-size: 0.95rem; border-radius: 16px; margin: 1rem 0; box-sizing: border-box; display: none;";
            emptyMsg.innerHTML = "Aucun résultat pour cette recherche.";
            container.appendChild(emptyMsg);
        }
        if (emptyMsg) {
            emptyMsg.style.display = (normalized !== "" && visibleCount === 0) ? "block" : "none";
        }
    }
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

/* Fermer la recherche avec la touche Échap */
document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
        document.querySelectorAll(".modal-search-expandable.is-open").forEach(function (wrapper) {
            var input = wrapper.querySelector(".modal-search-input");
            var clearBtn = wrapper.querySelector(".btn-search-clear");
            if (clearBtn && wrapper.classList.contains("has-value")) {
                clearBtn.click();
            } else if (input) {
                input.value = "";
                wrapper.classList.remove("is-open", "has-value");
            }
        });
    }
});
