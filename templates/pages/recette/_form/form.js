document.addEventListener("DOMContentLoaded", function () {
    const wrapper = document.getElementById("ingredients-wrapper");
    const addBtn = document.getElementById("add-ingredient-btn");
    const createBtn = document.getElementById("create-ingredient-btn");
    const emptyMsg = document.getElementById("no-ingredients-msg");
    const form = document.getElementById("recette-form");
    const designationInput = form ? form.querySelector('[name$="[designation]"]') : null;
    const draftKey = "recette_draft_" + window.location.pathname;

    function checkEmptyState() {
        if (!emptyMsg || !wrapper) return;
        const rows = wrapper.querySelectorAll(".ingredient-row");
        emptyMsg.style.display = rows.length === 0 ? "block" : "none";
    }

    function bindRemoveButtons(container) {
        container.querySelectorAll(".remove-row-btn").forEach((btn) => {
            btn.onclick = function () {
                btn.closest(".ingredient-row").remove();
                checkEmptyState();
            };
        });
    }

    function addIngredientRow(ingredientId, quantite) {
        if (!wrapper) return null;
        const prototype = wrapper.dataset.prototype;
        let index = parseInt(wrapper.dataset.index, 10) || 0;
        const newHtml = prototype.replace(/__name__/g, index);
        wrapper.dataset.index = index + 1;

        const temp = document.createElement("div");
        temp.innerHTML = newHtml;
        const newRow = temp.firstElementChild;
        wrapper.appendChild(newRow);
        bindRemoveButtons(newRow);

        if (ingredientId !== undefined && ingredientId !== null && ingredientId !== "") {
            const select = newRow.querySelector(".select-ingredient");
            if (select) {
                select.value = String(ingredientId);
            }
        }
        if (quantite !== undefined && quantite !== null && quantite !== "") {
            const quantiteInput = newRow.querySelector(".input-quantite");
            if (quantiteInput) {
                quantiteInput.value = quantite;
            }
        }

        checkEmptyState();
        return newRow;
    }

    function saveDraft() {
        try {
            const draft = {
                designation: designationInput ? designationInput.value : "",
                ingredients: []
            };
            if (wrapper) {
                wrapper.querySelectorAll(".ingredient-row").forEach((row) => {
                    const sel = row.querySelector(".select-ingredient");
                    const qte = row.querySelector(".input-quantite");
                    draft.ingredients.push({
                        ingredient: sel ? sel.value : "",
                        quantite: qte ? qte.value : ""
                    });
                });
            }
            sessionStorage.setItem(draftKey, JSON.stringify(draft));
        } catch (e) {}
    }

    if (addBtn) {
        addBtn.addEventListener("click", function () {
            addIngredientRow();
        });
    }

    if (createBtn) {
        createBtn.addEventListener("click", function () {
            saveDraft();
        });
    }

    bindRemoveButtons(wrapper);
    checkEmptyState();

    // Restauration du brouillon si l'utilisateur revient de la création d'ingrédient
    const urlParams = new URLSearchParams(window.location.search);
    const newIngredientId = urlParams.get("new_ingredient_id");
    const savedDraftStr = sessionStorage.getItem(draftKey);

    if (savedDraftStr) {
        try {
            const draft = JSON.parse(savedDraftStr);
            if (designationInput && (!designationInput.value || designationInput.value.trim() === "")) {
                if (draft.designation) {
                    designationInput.value = draft.designation;
                }
            }
            const currentRows = wrapper ? wrapper.querySelectorAll(".ingredient-row") : [];
            if (currentRows.length === 0 && Array.isArray(draft.ingredients) && draft.ingredients.length > 0) {
                draft.ingredients.forEach((item) => {
                    addIngredientRow(item.ingredient, item.quantite);
                });
            }
        } catch (e) {}
    }

    // Si un nouvel ingrédient vient d'être créé, le pré-sélectionner
    if (newIngredientId) {
        let targetRow = null;
        if (wrapper) {
            const rows = wrapper.querySelectorAll(".ingredient-row");
            for (let i = 0; i < rows.length; i++) {
                const sel = rows[i].querySelector(".select-ingredient");
                if (sel && (!sel.value || sel.value === "")) {
                    targetRow = rows[i];
                    sel.value = String(newIngredientId);
                    break;
                }
            }
        }
        if (!targetRow) {
            targetRow = addIngredientRow(newIngredientId, "");
        }
        if (targetRow) {
            const sel = targetRow.querySelector(".select-ingredient");
            if (sel) {
                sel.focus();
            }
        }
    }

    if (form) {
        form.addEventListener("submit", function () {
            try {
                sessionStorage.removeItem(draftKey);
            } catch (e) {}
        });
    }
});

function triggerRecettePhotoInput() {
    var input = document.getElementById("recette-photo-input");
    if (input) {
        input.click();
    }
}

function triggerRecetteGalleryInput() {
    var input = document.getElementById("recette-photo-gallery-input");
    if (input) {
        input.click();
    }
}

function onRecettePhotoSelected(input) {
    if (!input || !input.files || input.files.length === 0) {
        return;
    }
    var file = input.files[0];

    // Si sélection via input galerie, synchroniser sur l'input principal du formulaire
    var mainInput = document.getElementById("recette-photo-input");
    if (input.id === "recette-photo-gallery-input" && mainInput && window.DataTransfer) {
        try {
            var dt = new DataTransfer();
            dt.items.add(file);
            mainInput.files = dt.files;
        } catch (e) {}
    }

    var delInput = document.getElementById("recette-delete-photo-input");
    if (delInput) {
        delInput.value = "0";
    }

    var previewBox = document.getElementById("recette-new-photo-preview");
    var previewImg = document.getElementById("recette-new-photo-img");
    var curBox = document.getElementById("recette-current-photo");
    var noBox = document.getElementById("recette-no-photo");

    if (previewImg && previewBox) {
        var reader = new FileReader();
        reader.onload = function (e) {
            previewImg.src = e.target.result;
            previewBox.style.display = "block";
            if (curBox) curBox.style.display = "none";
            if (noBox) noBox.style.display = "none";
        };
        reader.readAsDataURL(file);
    }
}

function cancelRecetteNewPhoto() {
    var mainInput = document.getElementById("recette-photo-input");
    if (mainInput) mainInput.value = "";
    var galInput = document.getElementById("recette-photo-gallery-input");
    if (galInput) galInput.value = "";

    var previewBox = document.getElementById("recette-new-photo-preview");
    var previewImg = document.getElementById("recette-new-photo-img");
    var curBox = document.getElementById("recette-current-photo");
    var curImg = document.getElementById("recette-current-photo-img");
    var noBox = document.getElementById("recette-no-photo");
    var delInput = document.getElementById("recette-delete-photo-input");

    if (previewImg) previewImg.src = "";
    if (previewBox) previewBox.style.display = "none";

    var isDeleted = delInput && delInput.value === "1";
    if (curImg && curImg.src && curImg.src.trim() !== "" && !curImg.src.endsWith("/") && !isDeleted) {
        if (curBox) curBox.style.display = "block";
        if (noBox) noBox.style.display = "none";
    } else {
        if (curBox) curBox.style.display = "none";
        if (noBox) noBox.style.display = "block";
    }
}

function removeRecetteExistingPhoto() {
    var delInput = document.getElementById("recette-delete-photo-input");
    if (delInput) {
        delInput.value = "1";
    }
    var mainInput = document.getElementById("recette-photo-input");
    if (mainInput) mainInput.value = "";
    var galInput = document.getElementById("recette-photo-gallery-input");
    if (galInput) galInput.value = "";

    var curBox = document.getElementById("recette-current-photo");
    var noBox = document.getElementById("recette-no-photo");
    if (curBox) curBox.style.display = "none";
    if (noBox) noBox.style.display = "block";
}
