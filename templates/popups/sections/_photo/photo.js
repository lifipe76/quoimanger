/* ==========================================================================
   Composant Section Photo pour Popups (Ajouter, Modifier)
   Support Multi-photos et Drag & Drop
   ========================================================================== */

window.stagedModalPhotos = window.stagedModalPhotos || {};
window.selectedRepasPhotoFiles = window.selectedRepasPhotoFiles || [];
window.selectedRepasPhotoFile = null;

function triggerRepasModalCamera(prefix) {
    var pfx = prefix || "repas";
    var input = document.getElementById(pfx + "-photo-input");
    if (input) {
        input.click();
    }
}

function triggerRepasModalGallery(prefix) {
    var pfx = prefix || "repas";
    var input = document.getElementById(pfx + "-photo-gallery-input");
    if (input) {
        input.click();
    }
}

function onRepasModalPhotoInputSelected(input, prefix) {
    if (!input || !input.files || input.files.length === 0) {
        return;
    }
    var pfx = prefix || "repas";
    addFilesToModalStaged(pfx, input.files);
    input.value = "";
}

function onRepasModalPhotoDragOver(e, prefix) {
    e.preventDefault();
    e.stopPropagation();
    var pfx = prefix || "repas";
    var row = document.getElementById(pfx + "-photo-section");
    if (row) {
        row.classList.add("is-dragover");
    }
}

function onRepasModalPhotoDragLeave(e, prefix) {
    e.preventDefault();
    e.stopPropagation();
    var pfx = prefix || "repas";
    var row = document.getElementById(pfx + "-photo-section");
    if (row) {
        row.classList.remove("is-dragover");
    }
}

function onRepasModalPhotoDrop(e, prefix) {
    e.preventDefault();
    e.stopPropagation();
    var pfx = prefix || "repas";
    var row = document.getElementById(pfx + "-photo-section");
    if (row) {
        row.classList.remove("is-dragover");
    }

    if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
        addFilesToModalStaged(pfx, e.dataTransfer.files);
    }
}

function addFilesToModalStaged(prefix, fileList) {
    var pfx = prefix || "repas";
    if (!window.stagedModalPhotos[pfx]) {
        window.stagedModalPhotos[pfx] = [];
    }

    for (var i = 0; i < fileList.length; i++) {
        var file = fileList[i];
        if (file && (file.type ? file.type.startsWith("image/") : true)) {
            window.stagedModalPhotos[pfx].push(file);
        }
    }

    syncModalStagedFiles(pfx);
    renderModalNewPhotos(pfx);
}

function syncModalStagedFiles(prefix) {
    var pfx = prefix || "repas";
    var files = window.stagedModalPhotos[pfx] || [];

    if (pfx === "repas") {
        window.selectedRepasPhotoFiles = files;
        window.selectedRepasPhotoFile = files[0] || null;
    }

    var mainInput = document.getElementById(pfx + "-photos-input");
    if (mainInput && window.DataTransfer) {
        try {
            var dt = new DataTransfer();
            files.forEach(function (f) {
                dt.items.add(f);
            });
            mainInput.files = dt.files;
        } catch (e) {}
    }
}

function renderModalNewPhotos(prefix) {
    var pfx = prefix || "repas";
    var container = document.getElementById(pfx + "-new-photos-grid");
    if (!container) return;

    container.innerHTML = "";
    var files = window.stagedModalPhotos[pfx] || [];

    files.forEach(function (file, index) {
        var item = document.createElement("div");
        item.className = "modal-photo-item modal-photo-item-new";

        var img = document.createElement("img");
        img.className = "modal-photo-thumb";
        img.alt = file.name || "Nouvelle photo";

        var removeBtn = document.createElement("button");
        removeBtn.type = "button";
        removeBtn.className = "modal-photo-btn-remove";
        removeBtn.title = "Retirer cette photo";
        removeBtn.textContent = "✕";
        removeBtn.onclick = function () {
            removeModalStagedPhoto(pfx, index);
        };

        var reader = new FileReader();
        reader.onload = function (e) {
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);

        item.appendChild(img);
        item.appendChild(removeBtn);
        container.appendChild(item);
    });
}

function removeModalStagedPhoto(prefix, index) {
    var pfx = prefix || "repas";
    if (window.stagedModalPhotos[pfx] && window.stagedModalPhotos[pfx].length > index) {
        window.stagedModalPhotos[pfx].splice(index, 1);
        syncModalStagedFiles(pfx);
        renderModalNewPhotos(pfx);
    }
}

function removeModalExistingPhoto(prefix, filename, elementId) {
    var pfx = prefix || "edit-meal";
    var container = document.getElementById(pfx + "-delete-inputs-container");
    if (container && filename) {
        var hidden = document.createElement("input");
        hidden.type = "hidden";
        hidden.name = "delete_photos[]";
        hidden.value = filename;
        container.appendChild(hidden);
    }

    var el = document.getElementById(elementId);
    if (el) {
        el.remove();
    }
}

function populateModalExistingPhotos(prefix, photoUrls, photoNames) {
    var pfx = prefix || "edit-meal";
    var container = document.getElementById(pfx + "-existing-photos-grid");
    if (!container) return;

    container.innerHTML = "";
    var delContainer = document.getElementById(pfx + "-delete-inputs-container");
    if (delContainer) {
        delContainer.innerHTML = "";
        var delInput = document.createElement("input");
        delInput.type = "hidden";
        delInput.name = "delete_photo";
        delInput.id = pfx + "-delete-photo-input";
        delInput.value = "0";
        delContainer.appendChild(delInput);
    }

    var urls = [];
    if (Array.isArray(photoUrls)) {
        urls = photoUrls;
    } else if (typeof photoUrls === "string" && photoUrls.trim() !== "") {
        try {
            var parsed = JSON.parse(photoUrls);
            urls = Array.isArray(parsed) ? parsed : [photoUrls];
        } catch (e) {
            urls = [photoUrls];
        }
    }

    var names = [];
    if (Array.isArray(photoNames)) {
        names = photoNames;
    } else if (typeof photoNames === "string" && photoNames.trim() !== "") {
        try {
            var parsedN = JSON.parse(photoNames);
            names = Array.isArray(parsedN) ? parsedN : [photoNames];
        } catch (e) {
            names = [photoNames];
        }
    }

    urls.forEach(function (url, index) {
        if (!url || url.trim() === "" || url.endsWith("/")) return;
        var filename = names[index] || url.split("/").pop();

        var item = document.createElement("div");
        item.className = "modal-photo-item modal-photo-item-existing";
        var itemId = pfx + "-existing-photo-" + index;
        item.id = itemId;

        var img = document.createElement("img");
        img.className = "modal-photo-thumb";
        img.src = url;
        img.alt = "Photo actuelle";

        var removeBtn = document.createElement("button");
        removeBtn.type = "button";
        removeBtn.className = "modal-photo-btn-remove";
        removeBtn.title = "Supprimer cette photo";
        removeBtn.textContent = "✕";
        removeBtn.onclick = function () {
            removeModalExistingPhoto(pfx, filename, itemId);
        };

        item.appendChild(img);
        item.appendChild(removeBtn);
        container.appendChild(item);
    });
}

function clearModalPhotos(prefix) {
    var pfx = prefix || "repas";
    window.stagedModalPhotos[pfx] = [];
    syncModalStagedFiles(pfx);
    renderModalNewPhotos(pfx);

    var existing = document.getElementById(pfx + "-existing-photos-grid");
    if (existing) existing.innerHTML = "";

    var delContainer = document.getElementById(pfx + "-delete-inputs-container");
    if (delContainer) delContainer.innerHTML = "";
}

/* ==========================================================================
   Rétrocompatibilité avec les scripts existants
   ========================================================================== */

function triggerRepasPhotoInput() { triggerRepasModalCamera("repas"); }
function triggerRepasGalleryInput() { triggerRepasModalGallery("repas"); }
function onRepasPhotoSelected(input) { onRepasModalPhotoInputSelected(input, "repas"); }
function removeRepasPhoto() { clearModalPhotos("repas"); }

function triggerEditMealPhotoInput() { triggerRepasModalCamera("edit-meal"); }
function triggerEditMealGalleryInput() { triggerRepasModalGallery("edit-meal"); }
function onEditMealPhotoSelected(input) { onRepasModalPhotoInputSelected(input, "edit-meal"); }
function cancelEditMealNewPhoto() { clearModalPhotos("edit-meal"); }
function removeEditMealExistingPhoto() {
    var delInput = document.getElementById("edit-meal-delete-photo-input");
    if (delInput) delInput.value = "1";
    var existing = document.getElementById("edit-meal-existing-photos-grid");
    if (existing) existing.innerHTML = "";
}
