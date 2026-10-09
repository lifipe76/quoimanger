/* ==========================================================================
   Composant Section Date pour Popups (Ajout, Modif, etc.)
   ========================================================================== */

/**
 * Sélectionne un moment (matin, midi, soir) pour une modale donnée.
 *
 * @param {string} moment
 * @param {string} [prefix]
 */
function selectModalMoment(moment, prefix) {
    var pfx = prefix || "repas";
    var modalId = pfx === "repas" ? "repas-modal" : (pfx === "edit-meal" ? "edit-meal-modal" : null);
    var scope = modalId ? document.getElementById(modalId) : document;
    if (!scope) return;

    scope.querySelectorAll(".btn-moment-toggle").forEach(function (btn) {
        btn.classList.toggle("active", btn.dataset.moment === moment);
    });

    var input = scope.querySelector(".input-form-moment, #edit-meal-moment-input");
    if (input) {
        input.value = moment;
    }
}

/**
 * Sélectionne une date rapide pour une modale donnée.
 *
 * @param {string} dateStr
 * @param {HTMLElement} btnElement
 * @param {string} [prefix]
 */
function selectModalDate(dateStr, btnElement, prefix) {
    var pfx = prefix || "repas";
    var dateInput = document.getElementById(pfx + "-date-input");
    if (dateInput) {
        dateInput.value = dateStr;
    }

    var modalId = pfx === "repas" ? "repas-modal" : (pfx === "edit-meal" ? "edit-meal-modal" : null);
    var scope = modalId ? document.getElementById(modalId) : document;
    if (scope) {
        scope.querySelectorAll(".input-form-date").forEach(function (input) {
            input.value = dateStr;
        });
        scope.querySelectorAll(".btn-date-quick").forEach(function (btn) {
            btn.classList.remove("active");
        });
    }
    if (btnElement) {
        btnElement.classList.add("active");
    }
}

/* Fonctions compatibles pour "Ajouter un repas" */
function selectRepasMoment(moment) {
    selectModalMoment(moment, "repas");
}

function selectRepasDate(dateStr, btnElement) {
    selectModalDate(dateStr, btnElement, "repas");
}

function onRepasDateInputChange(val) {
    document.querySelectorAll("#repas-modal .input-form-date").forEach(function (input) {
        input.value = val;
    });
    document.querySelectorAll("#repas-modal .btn-date-quick").forEach(function (btn) {
        btn.classList.remove("active");
    });
}

/* Fonctions compatibles pour "Modifier le repas" */
function selectEditMoment(moment) {
    selectModalMoment(moment, "edit-meal");
}

function selectEditMealDate(dateStr, btnElement) {
    selectModalDate(dateStr, btnElement, "edit-meal");
}

function syncEditMealQuickDateButtons(val) {
    var now = new Date();
    var todayIso = now.toISOString().split("T")[0];
    var yest = new Date();
    yest.setDate(yest.getDate() - 1);
    var yestIso = yest.toISOString().split("T")[0];

    var btns = document.querySelectorAll("#edit-meal-modal .btn-date-quick");
    btns.forEach(function (b) { b.classList.remove("active"); });
    if (val === todayIso && btns[0]) {
        btns[0].classList.add("active");
    } else if (val === yestIso && btns[1]) {
        btns[1].classList.add("active");
    }
}

function onEditMealDateInputChange(val) {
    syncEditMealQuickDateButtons(val);
}
