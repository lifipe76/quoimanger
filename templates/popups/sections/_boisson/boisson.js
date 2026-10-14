/* ==========================================================================
   Composant Section Boisson pour Popups
   ========================================================================== */

/**
 * Gère le changement de boisson sélectionnée dans le popup.
 */
function onModalBoissonChanged(selectEl, prefix) {
    var pfx = prefix || "edit-meal";
    var details = document.getElementById(pfx + "-boisson-details");
    if (!details) return;

    if (selectEl && selectEl.value !== "") {
        details.style.display = "flex";
        // Si aucune date d'achat n'est saisie, mettre la date du jour par défaut
        var dateInput = document.getElementById(pfx + "-boisson-date-achat");
        if (dateInput && !dateInput.value) {
            dateInput.value = new Date().toISOString().split("T")[0];
        }
    } else {
        details.style.display = "none";
        // Réinitialiser la note et la date si aucune boisson
        var noteInput = document.getElementById(pfx + "-boisson-note");
        if (noteInput) noteInput.value = "0";
        var dateInput = document.getElementById(pfx + "-boisson-date-achat");
        if (dateInput) dateInput.value = "";
        updateModalBoissonStarDisplay(0, pfx);
    }
}

/**
 * Attribue ou retire une note (étoiles) pour la boisson dans le popup.
 */
function setModalBoissonStar(star, prefix) {
    var pfx = prefix || "edit-meal";
    var input = document.getElementById(pfx + "-boisson-note");
    if (!input) return;

    var currentVal = parseInt(input.value, 10) || 0;
    var newVal = (currentVal === star) ? 0 : star;
    input.value = newVal;

    updateModalBoissonStarDisplay(newVal, pfx);
}

/**
 * Met à jour l'affichage visuel des étoiles de la boisson.
 */
function updateModalBoissonStarDisplay(val, prefix) {
    var pfx = prefix || "edit-meal";
    var container = document.getElementById(pfx + "-boisson-stars-container");
    if (!container) return;

    container.querySelectorAll(".modal-boisson-star-btn").forEach(function (btn) {
        var s = parseInt(btn.dataset.star, 10);
        btn.style.color = (s <= val) ? "#f59e0b" : "#cbd5e1";
    });

    var noteText = document.getElementById(pfx + "-boisson-note-text");
    if (noteText) {
        noteText.textContent = (val > 0) ? (val + " / 5") : "";
    }
}

/**
 * Règle la date d'achat à aujourd'hui.
 */
function setModalBoissonToday(prefix) {
    var pfx = prefix || "edit-meal";
    var dateInput = document.getElementById(pfx + "-boisson-date-achat");
    if (dateInput) {
        dateInput.value = new Date().toISOString().split("T")[0];
    }
}

/**
 * Remplit les champs boisson lors de l'ouverture du popup d'édition.
 */
function populateModalBoisson(prefix, boissonId, dateAchat, note) {
    var pfx = prefix || "edit-meal";
    var select = document.getElementById(pfx + "-boisson-id");
    var details = document.getElementById(pfx + "-boisson-details");
    var dateInput = document.getElementById(pfx + "-boisson-date-achat");
    var noteInput = document.getElementById(pfx + "-boisson-note");

    var bId = boissonId ? String(boissonId) : "";
    if (select) {
        select.value = bId;
    }

    if (bId !== "") {
        if (details) details.style.display = "flex";
        if (dateInput) {
            dateInput.value = dateAchat ? String(dateAchat).split("T")[0] : "";
        }
        var n = parseInt(note, 10) || 0;
        if (noteInput) {
            noteInput.value = n;
        }
        updateModalBoissonStarDisplay(n, pfx);
    } else {
        if (details) details.style.display = "none";
        if (dateInput) dateInput.value = "";
        if (noteInput) noteInput.value = "0";
        updateModalBoissonStarDisplay(0, pfx);
    }
}
