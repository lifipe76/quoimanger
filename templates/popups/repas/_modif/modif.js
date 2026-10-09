/* ==========================================================================
   Popup "Modifier le repas" - JavaScript spécifique
   (Les sections Date, Photo, Personnes et Note sont gérées par leurs composants)
   ========================================================================== */

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
    notesMap,
    currentPhotoUrl
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
        var cleanDate = currentIsoDate ? currentIsoDate.split("T")[0] : "";
        dateInput.value = cleanDate;

        // Synchronisation des boutons rapides Aujourd'hui / Hier
        if (typeof syncEditMealQuickDateButtons === "function") {
            syncEditMealQuickDateButtons(cleanDate);
        }

        if (complementInput) {
            complementInput.value = currentComplement || "";
        }
        updateTokenInput.value = updateToken;
        if (recipeNameEl && recipeTitle) {
            recipeNameEl.textContent = recipeTitle;
        }

        if (typeof selectEditMoment === "function") {
            selectEditMoment(currentMoment || "soir");
        }

        if (deleteForm && deleteUrl && deleteTokenInput) {
            deleteForm.action = deleteUrl;
            deleteTokenInput.value = deleteToken;
        }

        // Réinitialisation de la section photo
        var photoInput = document.getElementById("edit-meal-photo-input");
        if (photoInput) {
            photoInput.value = "";
        }
        var photoGalleryInput = document.getElementById("edit-meal-photo-gallery-input");
        if (photoGalleryInput) {
            photoGalleryInput.value = "";
        }
        var deletePhotoInput = document.getElementById("edit-meal-delete-photo-input");
        if (deletePhotoInput) {
            deletePhotoInput.value = "0";
        }

        var currentPhotoBox = document.getElementById("edit-meal-current-photo");
        var currentPhotoImg = document.getElementById("edit-meal-current-photo-img");
        var newPhotoPreviewBox = document.getElementById("edit-meal-new-photo-preview");
        var newPhotoImg = document.getElementById("edit-meal-new-photo-img");
        var noPhotoBox = document.getElementById("edit-meal-no-photo");

        if (newPhotoImg) {
            newPhotoImg.src = "";
        }
        if (newPhotoPreviewBox) {
            newPhotoPreviewBox.style.display = "none";
        }

        if (currentPhotoUrl && currentPhotoUrl.trim() !== "") {
            if (currentPhotoImg) {
                currentPhotoImg.src = currentPhotoUrl;
            }
            if (currentPhotoBox) {
                currentPhotoBox.style.display = "flex";
            }
            if (noPhotoBox) {
                noPhotoBox.style.display = "none";
            }
        } else {
            if (currentPhotoImg) {
                currentPhotoImg.src = "";
            }
            if (currentPhotoBox) {
                currentPhotoBox.style.display = "none";
            }
            if (noPhotoBox) {
                noPhotoBox.style.display = "flex";
            }
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

        var participantCheckboxes = document.querySelectorAll("#edit-meal-participants-container .participant-checkbox");
        participantCheckboxes.forEach(function (chk) {
            var val = parseInt(chk.value, 10);
            var isChecked = parsedParticipantIds.includes(val);
            chk.checked = isChecked;
            var label = chk.closest(".participant-toggle-label");
            if (label) {
                label.style.borderColor = isChecked ? "var(--primary, #c4a587)" : "#cbd5e1";
                label.style.background = isChecked ? "#fdf8f4" : "#f8fafc";
            }
        });

        // Initialisation des notes
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

        document.querySelectorAll("#edit-meal-ratings-container .modal-star-rating").forEach(function (container) {
            var userId = container.dataset.userId;
            var userNote = parsedNotes[userId] || 0;

            var input = document.getElementById("edit-meal-note-" + userId);
            if (input) {
                input.value = userNote;
            }

            container.querySelectorAll(".modal-star-btn").forEach(function (btn) {
                var s = parseInt(btn.dataset.star, 10);
                btn.style.color = (s <= userNote) ? "#f59e0b" : "#cbd5e1";
            });

            container.querySelectorAll(".modal-star-display").forEach(function (span) {
                var s = parseInt(span.dataset.star, 10);
                span.style.color = (s <= userNote) ? "#f59e0b" : "#cbd5e1";
            });
        });

        openModal("edit-meal-modal");
    }
}

function closeEditMealModal() {
    closeModal("edit-meal-modal");
}

function handleEditMealBackdropClick(event) {
    handleModalBackdropClick(event, "edit-meal-modal");
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
