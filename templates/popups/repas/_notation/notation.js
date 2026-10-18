/* ==========================================================================
   Popup Notation de repas (compte connecté)
   ========================================================================== */

var currentRateMealSelectedNote = 0;

/**
 * Ouvre le popup de notation pour le repas spécifié.
 *
 * @param {number|string} realisationId
 * @param {string} recipeTitle
 * @param {number} currentNote
 * @param {string} csrfToken
 * @param {string} actionUrl
 */
function openRateMealModal(realisationId, recipeTitle, currentNote, csrfToken, actionUrl) {
    var modal = document.getElementById("rate-meal-modal");
    var form = document.getElementById("rate-meal-form");
    var tokenInput = document.getElementById("rate-meal-token");
    var noteInput = document.getElementById("rate-meal-note-input");
    var recipeNameEl = document.getElementById("rate-meal-recipe-name");

    if (modal && form) {
        form.action = actionUrl || ("/realisation/" + realisationId + "/rate");
        if (tokenInput) {
            tokenInput.value = csrfToken || "";
        }
        if (recipeNameEl) {
            recipeNameEl.textContent = recipeTitle || "";
        }

        currentRateMealSelectedNote = parseInt(currentNote, 10) || 0;
        if (noteInput) {
            noteInput.value = currentRateMealSelectedNote;
        }

        updateRateMealStarsDisplay(currentRateMealSelectedNote);
        openModal("rate-meal-modal");
    }
}

function closeRateMealModal() {
    closeModal("rate-meal-modal");
}

function handleRateMealBackdropClick(event) {
    handleModalBackdropClick(event, "rate-meal-modal");
}

function selectRateMealStar(star) {
    var noteInput = document.getElementById("rate-meal-note-input");
    if (currentRateMealSelectedNote === star) {
        currentRateMealSelectedNote = 0;
    } else {
        currentRateMealSelectedNote = star;
    }

    if (noteInput) {
        noteInput.value = currentRateMealSelectedNote;
    }
    updateRateMealStarsDisplay(currentRateMealSelectedNote);
}

function hoverRateMealStar(star) {
    updateRateMealStarsVisual(star);
    updateRateMealFeedbackText(star, true);
}

function resetRateMealStarsHover() {
    updateRateMealStarsVisual(currentRateMealSelectedNote);
    updateRateMealFeedbackText(currentRateMealSelectedNote, false);
}

function clearRateMealStar() {
    currentRateMealSelectedNote = 0;
    var noteInput = document.getElementById("rate-meal-note-input");
    if (noteInput) {
        noteInput.value = 0;
    }
    updateRateMealStarsDisplay(0);
}

function updateRateMealStarsDisplay(note) {
    updateRateMealStarsVisual(note);
    updateRateMealFeedbackText(note, false);

    var clearBtn = document.getElementById("rate-meal-btn-clear");
    if (clearBtn) {
        clearBtn.style.display = note > 0 ? "inline-block" : "none";
    }
}

function updateRateMealStarsVisual(note) {
    var picker = document.getElementById("rate-meal-stars-picker");
    if (!picker) return;

    var btns = picker.querySelectorAll(".rate-meal-star-btn");
    btns.forEach(function (btn) {
        var s = parseInt(btn.dataset.star, 10);
        if (s <= note) {
            btn.classList.add("is-active");
        } else {
            btn.classList.remove("is-active");
        }
    });
}

function updateRateMealFeedbackText(note, isHover) {
    var feedbackEl = document.getElementById("rate-meal-feedback-text");
    if (!feedbackEl) return;

    if (note <= 0) {
        feedbackEl.textContent = "Aucune note";
        feedbackEl.className = "rate-meal-feedback-text rate-meal-feedback-empty";
    } else {
        var labels = {
            1: "1 / 5 (Pas bon)",
            2: "2 / 5 (Moyen)",
            3: "3 / 5 (Bon)",
            4: "4 / 5 (Très bon)",
            5: "5 / 5 (Excellent !)"
        };
        feedbackEl.textContent = labels[note] || (note + " / 5");
        feedbackEl.className = "rate-meal-feedback-text rate-meal-feedback-rated";
    }
}
