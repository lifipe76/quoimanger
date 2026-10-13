/* ==========================================================================
   Composant Reutilisable PhotoInput (Multi-photos avec Drag & Drop)
   ========================================================================== */

window.stagedPhotos = window.stagedPhotos || {};

function triggerPhotoInput(prefix) {
    var pfx = prefix || "photo";
    var input = document.getElementById(pfx + "-photo-input");
    if (input) {
        input.click();
    }
}

function triggerPhotoGalleryInput(prefix) {
    var pfx = prefix || "photo";
    var input = document.getElementById(pfx + "-photo-gallery-input");
    if (input) {
        input.click();
    }
}

function onPhotoSelected(input, prefix) {
    if (!input || !input.files || input.files.length === 0) {
        return;
    }
    var pfx = prefix || "photo";
    addFilesToStaged(pfx, input.files);
    input.value = "";
}

function onPhotoDragOver(e, prefix) {
    e.preventDefault();
    e.stopPropagation();
    var comp = document.getElementById((prefix || "photo") + "-photo-component");
    if (comp) {
        comp.classList.add("is-dragover");
    }
}

function onPhotoDragLeave(e, prefix) {
    e.preventDefault();
    e.stopPropagation();
    var comp = document.getElementById((prefix || "photo") + "-photo-component");
    if (comp) {
        comp.classList.remove("is-dragover");
    }
}

function onPhotoDrop(e, prefix) {
    e.preventDefault();
    e.stopPropagation();
    var pfx = prefix || "photo";
    var comp = document.getElementById(pfx + "-photo-component");
    if (comp) {
        comp.classList.remove("is-dragover");
    }

    if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
        addFilesToStaged(pfx, e.dataTransfer.files);
    }
}

function addFilesToStaged(prefix, fileList) {
    var pfx = prefix || "photo";
    if (!window.stagedPhotos[pfx]) {
        window.stagedPhotos[pfx] = [];
    }

    for (var i = 0; i < fileList.length; i++) {
        var file = fileList[i];
        if (file && (file.type ? file.type.startsWith("image/") : true)) {
            window.stagedPhotos[pfx].push(file);
        }
    }

    syncStagedFilesToInput(pfx);
    renderNewPhotosPreview(pfx);
}

function syncStagedFilesToInput(prefix) {
    var pfx = prefix || "photo";
    var mainInput = document.getElementById(pfx + "-photos-input");
    if (!mainInput) return;

    if (window.DataTransfer) {
        try {
            var dt = new DataTransfer();
            var files = window.stagedPhotos[pfx] || [];
            files.forEach(function (f) {
                dt.items.add(f);
            });
            mainInput.files = dt.files;
        } catch (e) {}
    }
}

function renderNewPhotosPreview(prefix) {
    var pfx = prefix || "photo";
    var container = document.getElementById(pfx + "-new-photos-grid");
    if (!container) return;

    container.innerHTML = "";
    var files = window.stagedPhotos[pfx] || [];

    files.forEach(function (file, index) {
        var card = document.createElement("div");
        card.className = "photo-thumb-card photo-thumb-new";

        var img = document.createElement("img");
        img.className = "photo-thumb-img";
        img.alt = file.name || "Nouvelle photo";

        var badge = document.createElement("span");
        badge.className = "badge-new-photo-pill";
        badge.textContent = "Nouvelle";

        var btnRemove = document.createElement("button");
        btnRemove.type = "button";
        btnRemove.className = "photo-thumb-btn-remove";
        btnRemove.title = "Retirer cette photo";
        btnRemove.textContent = "✕";
        btnRemove.onclick = function () {
            removeSpecificStagedPhoto(pfx, index);
        };

        var reader = new FileReader();
        reader.onload = function (e) {
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);

        card.appendChild(img);
        card.appendChild(badge);
        card.appendChild(btnRemove);
        container.appendChild(card);
    });
}

function removeSpecificStagedPhoto(prefix, index) {
    var pfx = prefix || "photo";
    if (window.stagedPhotos[pfx] && window.stagedPhotos[pfx].length > index) {
        window.stagedPhotos[pfx].splice(index, 1);
        syncStagedFilesToInput(pfx);
        renderNewPhotosPreview(pfx);
    }
}

function removeSpecificExistingPhoto(prefix, filename, elementId) {
    var pfx = prefix || "photo";
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

// Rétrocompatibilité
function cancelNewPhoto(prefix) {
    var pfx = prefix || "photo";
    window.stagedPhotos[pfx] = [];
    syncStagedFilesToInput(pfx);
    renderNewPhotosPreview(pfx);
}

function removeExistingPhoto(prefix) {
    var pfx = prefix || "photo";
    var delInput = document.getElementById(pfx + "-delete-photo-input");
    if (delInput) {
        delInput.value = "1";
    }
    var existingGrid = document.getElementById(pfx + "-existing-photos-grid");
    if (existingGrid) {
        existingGrid.innerHTML = "";
    }
}

function triggerRecettePhotoInput() { triggerPhotoInput("recette"); }
function triggerRecetteGalleryInput() { triggerPhotoGalleryInput("recette"); }
function onRecettePhotoSelected(input) { onPhotoSelected(input, "recette"); }
function cancelRecetteNewPhoto() { cancelNewPhoto("recette"); }
function removeRecetteExistingPhoto() { removeExistingPhoto("recette"); }
