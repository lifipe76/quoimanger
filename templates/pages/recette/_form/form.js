document.addEventListener("DOMContentLoaded", function () {
    const wrapper = document.getElementById("ingredients-wrapper");
    const addBtn = document.getElementById("add-ingredient-btn");
    const emptyMsg = document.getElementById("no-ingredients-msg");

    function checkEmptyState() {
        if (!emptyMsg) return;
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

    if (addBtn && wrapper) {
        addBtn.addEventListener("click", function () {
            const prototype = wrapper.dataset.prototype;
            let index = parseInt(wrapper.dataset.index, 10) || 0;
            const newHtml = prototype.replace(/__name__/g, index);
            wrapper.dataset.index = index + 1;

            const temp = document.createElement("div");
            temp.innerHTML = newHtml;
            const newRow = temp.firstElementChild;
            wrapper.appendChild(newRow);
            bindRemoveButtons(newRow);
            checkEmptyState();
        });
    }

    bindRemoveButtons(wrapper);
    checkEmptyState();
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
