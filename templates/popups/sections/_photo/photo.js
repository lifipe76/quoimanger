/* ==========================================================================
   Composant Section Photo pour Popups (Ajouter, Modifier)
   ========================================================================== */

window.selectedRepasPhotoFile = null;
window.selectedRepasPhotoInput = null;

/* --------------------------------------------------------------------------
   Gestion photo dans "Ajouter un repas"
   -------------------------------------------------------------------------- */

function triggerRepasPhotoInput() {
    var input = document.getElementById("repas-photo-input");
    if (input) {
        input.click();
    }
}

function triggerRepasGalleryInput() {
    var input = document.getElementById("repas-photo-gallery-input");
    if (input) {
        input.click();
    }
}

function onRepasPhotoSelected(input) {
    if (!input || !input.files || input.files.length === 0) {
        return;
    }
    var file = input.files[0];
    window.selectedRepasPhotoFile = file;
    window.selectedRepasPhotoInput = input;

    var previewBox = document.getElementById("repas-photo-preview-box");
    var previewImg = document.getElementById("repas-photo-preview-img");
    var controls = document.querySelector("#repas-modal .repas-photo-controls");

    if (previewImg && previewBox) {
        var reader = new FileReader();
        reader.onload = function (e) {
            previewImg.src = e.target.result;
            previewBox.style.display = "flex";
            if (controls) {
                controls.style.display = "none";
            }
        };
        reader.readAsDataURL(file);
    }
}

function removeRepasPhoto() {
    window.selectedRepasPhotoFile = null;
    window.selectedRepasPhotoInput = null;

    var cameraInput = document.getElementById("repas-photo-input");
    if (cameraInput) {
        cameraInput.value = "";
    }
    var galleryInput = document.getElementById("repas-photo-gallery-input");
    if (galleryInput) {
        galleryInput.value = "";
    }

    var previewBox = document.getElementById("repas-photo-preview-box");
    var previewImg = document.getElementById("repas-photo-preview-img");
    var controls = document.querySelector("#repas-modal .repas-photo-controls");

    if (previewImg) {
        previewImg.src = "";
    }
    if (previewBox) {
        previewBox.style.display = "none";
    }
    if (controls) {
        controls.style.display = "flex";
    }
}

/* --------------------------------------------------------------------------
   Gestion photo dans "Modifier le repas"
   -------------------------------------------------------------------------- */

function triggerEditMealPhotoInput() {
    var input = document.getElementById("edit-meal-photo-input");
    if (input) {
        input.click();
    }
}

function triggerEditMealGalleryInput() {
    var input = document.getElementById("edit-meal-photo-gallery-input");
    if (input) {
        input.click();
    }
}

function onEditMealPhotoSelected(input) {
    if (!input || !input.files || input.files.length === 0) {
        return;
    }
    var file = input.files[0];

    // Si sélection via input galerie, synchroniser sur l'input principal du formulaire
    var mainInput = document.getElementById("edit-meal-photo-input");
    if (input.id === "edit-meal-photo-gallery-input" && mainInput && window.DataTransfer) {
        try {
            var dt = new DataTransfer();
            dt.items.add(file);
            mainInput.files = dt.files;
        } catch (e) {}
    }

    var previewBox = document.getElementById("edit-meal-new-photo-preview");
    var previewImg = document.getElementById("edit-meal-new-photo-img");
    var curBox = document.getElementById("edit-meal-current-photo");
    var noBox = document.getElementById("edit-meal-no-photo");

    if (previewImg && previewBox) {
        var reader = new FileReader();
        reader.onload = function (e) {
            previewImg.src = e.target.result;
            previewBox.style.display = "flex";
            if (curBox) curBox.style.display = "none";
            if (noBox) noBox.style.display = "none";
        };
        reader.readAsDataURL(file);
    }
}

function cancelEditMealNewPhoto() {
    var mainInput = document.getElementById("edit-meal-photo-input");
    if (mainInput) mainInput.value = "";
    var galInput = document.getElementById("edit-meal-photo-gallery-input");
    if (galInput) galInput.value = "";

    var previewBox = document.getElementById("edit-meal-new-photo-preview");
    var previewImg = document.getElementById("edit-meal-new-photo-img");
    var curBox = document.getElementById("edit-meal-current-photo");
    var curImg = document.getElementById("edit-meal-current-photo-img");
    var noBox = document.getElementById("edit-meal-no-photo");
    var delInput = document.getElementById("edit-meal-delete-photo-input");

    if (previewImg) previewImg.src = "";
    if (previewBox) previewBox.style.display = "none";

    var isDeleted = delInput && delInput.value === "1";
    if (curImg && curImg.src && curImg.src.trim() !== "" && !curImg.src.endsWith("/") && !isDeleted) {
        if (curBox) curBox.style.display = "flex";
        if (noBox) noBox.style.display = "none";
    } else {
        if (curBox) curBox.style.display = "none";
        if (noBox) noBox.style.display = "flex";
    }
}

function removeEditMealExistingPhoto() {
    var delInput = document.getElementById("edit-meal-delete-photo-input");
    if (delInput) {
        delInput.value = "1";
    }
    var curBox = document.getElementById("edit-meal-current-photo");
    var noBox = document.getElementById("edit-meal-no-photo");
    if (curBox) curBox.style.display = "none";
    if (noBox) noBox.style.display = "flex";
}
