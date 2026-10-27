/* JavaScript Popup Modif Action Realisation */

var currentActionDeleteUrl = "";
var currentActionDeleteToken = "";

function openEditActionRealisationModal(updateUrl, dateStr, moment, actionName, csrfToken, deleteUrl, deleteToken, complement, participantIds, photoUrls) {
    currentActionDeleteUrl = deleteUrl || "";
    currentActionDeleteToken = deleteToken || "";

    var form = document.getElementById("edit-action-realisation-form");
    if (form) {
        form.action = updateUrl;
    }

    var tokenInp = document.getElementById("edit-action-token");
    if (tokenInp) {
        tokenInp.value = csrfToken || "";
    }

    var nameEl = document.getElementById("edit-action-name");
    if (nameEl) {
        nameEl.textContent = actionName || "";
    }

    var picker = document.getElementById("edit-action_date_picker");
    if (picker) {
        picker.value = dateStr || "";
    }

    var momentInp = document.getElementById("edit-action-moment-input");
    if (momentInp) {
        momentInp.value = moment || "matin";
    }

    // Activer la pilule du moment
    var momentWrap = document.getElementById("edit-action_moment_pills");
    if (momentWrap) {
        momentWrap.querySelectorAll(".moment-pill").forEach(function (pill) {
            var val = pill.getAttribute("data-moment");
            pill.classList.toggle("active", val === (moment || "matin"));
        });
    }

    // Complément
    var compInp = document.getElementById("edit-action-complement-input");
    if (compInp) {
        compInp.value = complement || "";
    }

    // Participants
    if (Array.isArray(participantIds)) {
        document.querySelectorAll("input[name='participants[]']").forEach(function (chk) {
            var val = parseInt(chk.value, 10);
            chk.checked = participantIds.includes(val);
            var parentBtn = chk.closest(".personne-badge-btn");
            if (parentBtn) {
                parentBtn.classList.toggle("active", chk.checked);
            }
        });
    }

    if (typeof openModal === "function") {
        openModal("edit-action-modal");
    } else {
        var m = document.getElementById("edit-action-modal");
        if (m) m.style.display = "flex";
    }
}
window.openEditActionRealisationModal = openEditActionRealisationModal;

function closeEditActionRealisationModal() {
    if (typeof closeModal === "function") {
        closeModal("edit-action-modal");
    } else {
        var m = document.getElementById("edit-action-modal");
        if (m) m.style.display = "none";
    }
}
window.closeEditActionRealisationModal = closeEditActionRealisationModal;

function selectEditActionDate(btn, dateVal) {
    var parent = btn.closest(".date-quick-pills");
    if (parent) {
        parent.querySelectorAll(".date-quick-btn").forEach(function (b) {
            b.classList.remove("active");
        });
        btn.classList.add("active");
    }

    var picker = document.getElementById("edit-action_date_picker");
    if (picker) {
        picker.value = dateVal;
    }
}
window.selectEditActionDate = selectEditActionDate;

function selectEditActionMoment(btn, momentVal) {
    var parent = btn.closest(".moment-pills");
    if (parent) {
        parent.querySelectorAll(".moment-pill").forEach(function (b) {
            b.classList.remove("active");
        });
        btn.classList.add("active");
    }

    var inp = document.getElementById("edit-action-moment-input");
    if (inp) {
        inp.value = momentVal;
    }
}
window.selectEditActionMoment = selectEditActionMoment;

function onEditActionDateInputChange(dateVal) {
    // Value already set in picker
}
window.onEditActionDateInputChange = onEditActionDateInputChange;

function deleteCurrentActionRealisation() {
    if (!currentActionDeleteUrl) return;

    if (confirm("Supprimer cette action de votre fil ?")) {
        var f = document.createElement("form");
        f.method = "POST";
        f.action = currentActionDeleteUrl;

        var t = document.createElement("input");
        t.type = "hidden";
        t.name = "_token";
        t.value = currentActionDeleteToken;
        f.appendChild(t);

        document.body.appendChild(f);
        f.submit();
    }
}
window.deleteCurrentActionRealisation = deleteCurrentActionRealisation;
