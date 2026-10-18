var editMealCurrentRecipes = [];

function escapeHtmlModif(str) {
    if (!str) return "";
    return String(str)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");
}

function computeMergedMealTitle(recipeTitles) {
    if (!recipeTitles || recipeTitles.length === 0) return "";
    var clean = [];
    recipeTitles.forEach(function (t) {
        var str = (t || "").trim();
        if (str === "") return;
        var lower = str.toLowerCase();
        var found = clean.some(function (existing) {
            return existing.toLowerCase() === lower;
        });
        if (!found) {
            clean.push(str);
        }
    });

    if (clean.length === 0) return "";
    if (clean.length === 1) return clean[0];

    var filtered = [];
    clean.forEach(function (titleA, i) {
        var subsumed = false;
        var normA = titleA.toLowerCase();
        clean.forEach(function (titleB, j) {
            if (i === j) return;
            var normB = titleB.toLowerCase();
            if (normB.length > normA.length && normB.indexOf(normA) !== -1) {
                subsumed = true;
            }
        });
        if (!subsumed) {
            filtered.push(titleA);
        }
    });

    if (filtered.length === 0) {
        filtered = [clean[0]];
    }
    if (filtered.length === 1) {
        return filtered[0];
    }

    var result = filtered.map(function (t, idx) {
        if (idx === 0) {
            return t.charAt(0).toUpperCase() + t.slice(1);
        } else {
            return t.toLowerCase();
        }
    });

    return result.join(", ");
}

function renderEditMealRecipes() {
    var chipsContainer = document.getElementById("edit-meal-recipes-chips");
    var inputsContainer = document.getElementById("edit-meal-recipes-inputs");
    var countEl = document.getElementById("edit-meal-recipes-count");
    var recipeNameEl = document.getElementById("edit-meal-recipe-name");
    var selectEl = document.getElementById("edit-meal-recipe-select");

    if (!chipsContainer || !inputsContainer) return;

    var count = editMealCurrentRecipes.length;
    if (countEl) {
        countEl.textContent =
            count > 1 ? count + " recettes" : count + " recette";
    }

    var chipsHtml = "";
    editMealCurrentRecipes.forEach(function (r) {
        var rId = r.id ? String(r.id) : "";
        chipsHtml +=
            '<span class="edit-meal-recipe-chip" data-recipe-id="' + rId + '">';
        chipsHtml +=
            '<span class="chip-label">' +
            escapeHtmlModif(r.designation) +
            "</span>";
        if (count > 1) {
            chipsHtml +=
                '<button type="button" class="btn-remove-recipe-chip" onclick="removeRecipeFromEditMeal(\'' +
                rId +
                '\')" title="Retirer cette recette" aria-label="Retirer cette recette">✕</button>';
        }
        chipsHtml += "</span>";
    });
    chipsContainer.innerHTML = chipsHtml;

    var inputsHtml =
        '<input type="hidden" name="has_recettes_field" value="1">';
    editMealCurrentRecipes.forEach(function (r) {
        if (r.id) {
            inputsHtml +=
                '<input type="hidden" name="recette_ids[]" value="' +
                escapeHtmlModif(r.id) +
                '">';
        }
    });
    inputsContainer.innerHTML = inputsHtml;

    var titles = editMealCurrentRecipes.map(function (r) {
        return r.designation;
    });
    var mergedTitle = computeMergedMealTitle(titles);
    if (recipeNameEl && mergedTitle) {
        recipeNameEl.textContent = mergedTitle;
    }

    if (selectEl) {
        var selectedIds = new Set(
            editMealCurrentRecipes.map(function (r) {
                return String(r.id);
            }),
        );
        for (var i = 0; i < selectEl.options.length; i++) {
            var opt = selectEl.options[i];
            if (opt.value && selectedIds.has(String(opt.value))) {
                opt.disabled = true;
            } else {
                opt.disabled = false;
            }
        }
        selectEl.value = "";
    }
}

function addSelectedRecipeToEditMeal() {
    var selectEl = document.getElementById("edit-meal-recipe-select");
    if (!selectEl || !selectEl.value) return;

    var selectedId = selectEl.value;
    var selectedOption = selectEl.options[selectEl.selectedIndex];
    var designation = (
        selectedOption.getAttribute("data-designation") ||
        selectedOption.text ||
        ""
    ).trim();

    var alreadyExists = editMealCurrentRecipes.some(function (r) {
        return String(r.id) === String(selectedId);
    });

    if (!alreadyExists) {
        editMealCurrentRecipes.push({
            id: parseInt(selectedId, 10),
            designation: designation,
        });
        renderEditMealRecipes();
    }
}

function removeRecipeFromEditMeal(recipeId) {
    if (editMealCurrentRecipes.length <= 1) return;

    editMealCurrentRecipes = editMealCurrentRecipes.filter(function (r) {
        return String(r.id) !== String(recipeId);
    });
    renderEditMealRecipes();
}

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
    currentPhotoUrl,
    currentPhotoNames,
    currentRecipesData,
    boissonId,
    boissonDateAchat,
    boissonNote,
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

        // Initialisation de la liste des recettes de ce repas
        editMealCurrentRecipes = [];
        if (currentRecipesData) {
            if (typeof currentRecipesData === "string") {
                try {
                    editMealCurrentRecipes = JSON.parse(currentRecipesData);
                } catch (e) {
                    editMealCurrentRecipes = [];
                }
            } else if (Array.isArray(currentRecipesData)) {
                editMealCurrentRecipes = currentRecipesData.slice();
            }
        }

        if (editMealCurrentRecipes.length === 0 && recipeTitle) {
            var matchedId = null;
            var selectEl = document.getElementById("edit-meal-recipe-select");
            if (selectEl) {
                for (var sIdx = 0; sIdx < selectEl.options.length; sIdx++) {
                    if (
                        selectEl.options[sIdx].text.trim().toLowerCase() ===
                        recipeTitle.trim().toLowerCase()
                    ) {
                        matchedId = selectEl.options[sIdx].value;
                        break;
                    }
                }
            }
            editMealCurrentRecipes.push({
                id: matchedId ? parseInt(matchedId, 10) : null,
                designation: recipeTitle,
            });
        }

        renderEditMealRecipes();

        if (typeof selectEditMoment === "function") {
            selectEditMoment(currentMoment || "soir");
        }

        if (deleteForm && deleteUrl && deleteTokenInput) {
            deleteForm.action = deleteUrl;
            deleteTokenInput.value = deleteToken;
        }

        // Réinitialisation et peuplement de la section photo (multi-photos)
        if (typeof clearModalPhotos === "function") {
            clearModalPhotos("edit-meal");
        }
        if (
            typeof populateModalExistingPhotos === "function" &&
            (currentPhotoUrl || currentPhotoNames)
        ) {
            populateModalExistingPhotos(
                "edit-meal",
                currentPhotoUrl,
                currentPhotoNames,
            );
        }

        if (updateForm && !updateForm.dataset.multiPhotoListenerAttached) {
            updateForm.dataset.multiPhotoListenerAttached = "true";
            updateForm.addEventListener("submit", function () {
                if (typeof syncModalStagedFiles === "function") {
                    syncModalStagedFiles("edit-meal");
                }
            });
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

        var participantCheckboxes = document.querySelectorAll(
            "#edit-meal-participants-container .participant-checkbox",
        );
        participantCheckboxes.forEach(function (chk) {
            var val = parseInt(chk.value, 10);
            var isChecked = parsedParticipantIds.includes(val);
            chk.checked = isChecked;
            var label = chk.closest(".participant-toggle-label");
            if (label) {
                label.style.borderColor = isChecked
                    ? "var(--primary, #c4a587)"
                    : "#cbd5e1";
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

        document
            .querySelectorAll("#edit-meal-ratings-container .modal-star-rating")
            .forEach(function (container) {
                var userId = container.dataset.userId;
                var userNote = parsedNotes[userId] || 0;

                var input = document.getElementById("edit-meal-note-" + userId);
                if (input) {
                    input.value = userNote;
                }

                container
                    .querySelectorAll(".modal-star-btn")
                    .forEach(function (btn) {
                        var s = parseInt(btn.dataset.star, 10);
                        btn.style.color = s <= userNote ? "#f59e0b" : "#cbd5e1";
                    });

                container
                    .querySelectorAll(".modal-star-display")
                    .forEach(function (span) {
                        var s = parseInt(span.dataset.star, 10);
                        span.style.color =
                            s <= userNote ? "#f59e0b" : "#cbd5e1";
                    });
            });

        // Initialisation de la section boisson
        if (typeof populateModalBoisson === "function") {
            populateModalBoisson(
                "edit-meal",
                boissonId,
                boissonDateAchat,
                boissonNote,
            );
        }

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
            },
        });
    } else {
        form.submit();
    }
}
