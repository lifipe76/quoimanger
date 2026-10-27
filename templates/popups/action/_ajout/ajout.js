/* JavaScript Popup Ajout Action */

function openActionModal() {
    if (typeof openModal === "function") {
        openModal("action-modal");
    } else {
        var m = document.getElementById("action-modal");
        if (m) m.style.display = "flex";
    }

    var input = document.getElementById("action-filter-input");
    if (input) {
        input.value = "";
        filterActionsList("");
    }
}
window.openActionModal = openActionModal;

function closeActionModal() {
    if (typeof closeModal === "function") {
        closeModal("action-modal");
    } else {
        var m = document.getElementById("action-modal");
        if (m) m.style.display = "none";
    }
}
window.closeActionModal = closeActionModal;

function selectActionDate(btn, dateVal) {
    var parent = btn.closest(".date-quick-pills");
    if (parent) {
        parent.querySelectorAll(".date-quick-btn").forEach(function (b) {
            b.classList.remove("active");
        });
        btn.classList.add("active");
    }

    var picker = document.getElementById("action_date_picker");
    if (picker) {
        picker.value = dateVal;
    }

    document.querySelectorAll(".input-action-form-date").forEach(function (inp) {
        inp.value = dateVal;
    });
}
window.selectActionDate = selectActionDate;

function selectActionMoment(btn, momentVal) {
    var parent = btn.closest(".moment-pills");
    if (parent) {
        parent.querySelectorAll(".moment-pill").forEach(function (b) {
            b.classList.remove("active");
        });
        btn.classList.add("active");
    }

    document.querySelectorAll(".input-action-form-moment").forEach(function (inp) {
        inp.value = momentVal;
    });
}
window.selectActionMoment = selectActionMoment;

function onActionDateInputChange(dateVal) {
    document.querySelectorAll(".input-action-form-date").forEach(function (inp) {
        inp.value = dateVal;
    });
}
window.onActionDateInputChange = onActionDateInputChange;

function filterActionsList(term) {
    var normalized = (term || "").trim().toLowerCase();
    var forms = document.querySelectorAll("#modal-actions-container .modal-action-form");
    forms.forEach(function (form) {
        var name = form.dataset.name || "";
        form.style.display = name.includes(normalized) ? "block" : "none";
    });
}
window.filterActionsList = filterActionsList;

function toggleModalActionSortOrder(btnElement, containerId) {
    var container = document.getElementById(containerId || "modal-actions-container");
    if (!container) return;

    var currentOrder = btnElement.getAttribute("data-order") || "desc";
    var nextOrder = currentOrder === "asc" ? "desc" : "asc";
    btnElement.setAttribute("data-order", nextOrder);

    var icon = btnElement.querySelector(".sort-order-icon") || btnElement.querySelector("i");
    if (icon) {
        icon.className = (nextOrder === "asc" ? "fa-solid fa-arrow-up" : "fa-solid fa-arrow-down") + " sort-order-icon";
    }

    var topBar = btnElement.closest(".modal-top-bar");
    var activePill = topBar ? topBar.querySelector(".modal-sort-pills .sort-pill.active") : null;
    var currentSortKey = activePill ? (activePill.getAttribute("data-sort") || "date") : "date";

    sortModalActions(currentSortKey, activePill, containerId, nextOrder);
}
window.toggleModalActionSortOrder = toggleModalActionSortOrder;

function sortModalActions(sortKey, btnElement, containerId, forcedOrder) {
    var container = document.getElementById(containerId || "modal-actions-container");
    if (!container) return;

    var topBar = btnElement ? btnElement.closest(".modal-top-bar") : null;
    var orderBtn = topBar ? topBar.querySelector(".modal-sort-order-btn") : null;

    var order = forcedOrder;
    if (!order) {
        if (btnElement && btnElement.classList.contains("active") && orderBtn) {
            order = (orderBtn.getAttribute("data-order") === "asc") ? "desc" : "asc";
        } else {
            order = (sortKey === "alpha") ? "asc" : "desc";
        }
    }

    if (orderBtn) {
        orderBtn.setAttribute("data-order", order);
        var icon = orderBtn.querySelector(".sort-order-icon") || orderBtn.querySelector("i");
        if (icon) {
            icon.className = (order === "asc" ? "fa-solid fa-arrow-up" : "fa-solid fa-arrow-down") + " sort-order-icon";
        }
    }

    if (btnElement && btnElement.parentNode) {
        btnElement.parentNode.querySelectorAll(".sort-pill").forEach(function (pill) {
            pill.classList.remove("active");
        });
        btnElement.classList.add("active");
    }

    var forms = Array.from(container.querySelectorAll(".modal-action-form"));
    if (forms.length <= 1) return;

    var isAsc = (order === "asc");

    forms.sort(function (a, b) {
        var dateA = parseInt(a.dataset.date || "0", 10);
        var dateB = parseInt(b.dataset.date || "0", 10);
        var countA = parseInt(a.dataset.count || "0", 10);
        var countB = parseInt(b.dataset.count || "0", 10);
        var nameA = (a.dataset.name || "").trim();
        var nameB = (b.dataset.name || "").trim();

        if (sortKey === "date") {
            if (dateA === 0 && dateB !== 0) return 1;
            if (dateB === 0 && dateA !== 0) return -1;
            if (dateA === dateB) return nameA.localeCompare(nameB, "fr");
            return isAsc ? (dateA - dateB) : (dateB - dateA);
        } else if (sortKey === "echeance") {
            var echA = parseInt(a.dataset.echeance || "99999", 10);
            var echB = parseInt(b.dataset.echeance || "99999", 10);
            if (echA === echB) return nameA.localeCompare(nameB, "fr");
            return isAsc ? (echA - echB) : (echB - echA);
        } else if (sortKey === "count") {
            if (countA === countB) return nameA.localeCompare(nameB, "fr");
            return isAsc ? (countA - countB) : (countB - countA);
        } else if (sortKey === "alpha") {
            var cmp = nameA.localeCompare(nameB, "fr");
            return isAsc ? cmp : -cmp;
        }
        return 0;
    });

    forms.forEach(function (form) {
        container.appendChild(form);
    });
}
window.sortModalActions = sortModalActions;
