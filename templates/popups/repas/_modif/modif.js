function openEditMealModal(
    updateUrl,
    currentIsoDate,
    currentMoment,
    recipeTitle,
    updateToken,
    deleteUrl,
    deleteToken,
    currentComplement,
    participantIds,
    notesMap
) {
    var modal = document.getElementById("edit-meal-modal");
    var updateForm = document.getElementById("edit-meal-form");
    var dateInput = document.getElementById("edit-meal-date-input");
    var complementInput = document.getElementById("edit-meal-complement-input");
    var updateTokenInput = document.getElementById("edit-meal-token");
    var recipeNameEl = document.getElementById("edit-meal-recipe-name");
    var deleteForm = document.getElementById("delete-meal-form");
    var deleteTokenInput = document.getElementById("delete-meal-token");

    if (modal && updateForm && dateInput) {
        updateForm.action = updateUrl;
        dateInput.value = currentIsoDate ? currentIsoDate.split("T")[0] : "";
        if (complementInput) {
            complementInput.value = currentComplement || "";
        }
        updateTokenInput.value = updateToken;
        if (recipeNameEl && recipeTitle) {
            recipeNameEl.textContent = recipeTitle;
        }

        selectEditMoment(currentMoment || "soir");

        if (deleteForm && deleteUrl && deleteTokenInput) {
            deleteForm.action = deleteUrl;
            deleteTokenInput.value = deleteToken;
        }

        // Initialisation des cases à cocher des participants
        var parsedParticipantIds = [];
        if (participantIds) {
            if (typeof participantIds === "string") {
                try {
                    parsedParticipantIds = JSON.parse(participantIds);
                } catch (e) {
                    parsedParticipantIds = [];
                }
            } else if (Array.isArray(participantIds)) {
                parsedParticipantIds = participantIds;
            }
        }

        document.querySelectorAll("#edit-meal-participants-container .participant-checkbox").forEach(function (cb) {
            cb.checked = parsedParticipantIds.includes(parseInt(cb.value, 10));
        });

        // Initialisation des notes des membres de la famille
        var parsedNotes = {};
        if (notesMap) {
            if (typeof notesMap === "string") {
                try {
                    parsedNotes = JSON.parse(notesMap);
                } catch (e) {
                    parsedNotes = {};
                }
            } else if (typeof notesMap === "object") {
                parsedNotes = notesMap;
            }
        }

        document.querySelectorAll(".modal-star-rating").forEach(function (container) {
            var uid = container.dataset.userId;
            var noteVal = parsedNotes[uid] ? parseInt(parsedNotes[uid], 10) : 0;
            var input = document.getElementById("edit-meal-note-" + uid);
            if (input) {
                input.value = noteVal;
            }
            container.querySelectorAll(".modal-star-btn, .modal-star-display").forEach(function (el) {
                var s = parseInt(el.dataset.star, 10);
                el.style.color = (s <= noteVal) ? "#f59e0b" : "#cbd5e1";
            });
        });

        openModal("edit-meal-modal");
        setTimeout(function () {
            dateInput.focus();
        }, 100);
    }
}

function closeEditMealModal() {
    closeModal("edit-meal-modal");
}

function handleEditMealBackdropClick(event) {
    handleModalBackdropClick(event, "edit-meal-modal");
}

function selectEditMoment(moment) {
    var input = document.getElementById("edit-meal-moment-input");
    if (input) {
        input.value = moment;
    }
    document.querySelectorAll(".btn-edit-moment-toggle").forEach(function (btn) {
        btn.classList.toggle("active", btn.dataset.moment === moment);
    });
}

function submitDeleteMeal() {
    var form = document.getElementById("delete-meal-form");
    if (!form) return;

    if (typeof window.openConfirmPopup === "function") {
        window.openConfirmPopup({
            title: "Supprimer le repas",
            message: "Retirer définitivement ce repas de la timeline ?",
            confirmText: "Supprimer",
            confirmClass: "btn-danger",
            onConfirm: function () {
                form.submit();
            }
        });
    } else {
        form.submit();
    }
}

function setModalMemberStar(userId, star) {
    var input = document.getElementById("edit-meal-note-" + userId);
    if (!input) return;

    var currentVal = parseInt(input.value, 10) || 0;
    var newVal = (currentVal === star) ? 0 : star;
    input.value = newVal;

    var container = document.querySelector('.modal-star-rating[data-user-id="' + userId + '"]');
    if (container) {
        container.querySelectorAll(".modal-star-btn").forEach(function (btn) {
            var s = parseInt(btn.dataset.star, 10);
            btn.style.color = (s <= newVal) ? "#f59e0b" : "#cbd5e1";
        });
    }
}
