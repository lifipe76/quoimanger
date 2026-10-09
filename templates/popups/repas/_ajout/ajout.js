/* ==========================================================================
   Popup "Ajouter un repas" - JavaScript spécifique
   (Les sections Date et Photo sont gérées par leurs composants respectifs)
   ========================================================================== */

function openRepasModal() {
    if (typeof removeRepasPhoto === "function") {
        removeRepasPhoto();
    }
    openModal("repas-modal");
    var wrapper = document.getElementById("recipe-filter-input-wrapper");
    if (wrapper) {
        wrapper.classList.remove("is-open");
        wrapper.classList.remove("has-value");
    }
    var input = document.getElementById("recipe-filter-input");
    if (input) {
        input.value = "";
        filterRecipesList("");
    }
}

function closeRepasModal() {
    closeModal("repas-modal");
}

function filterRecipesList(term) {
    var normalized = term.trim().toLowerCase();
    var forms = document.querySelectorAll("#modal-recipes-container .modal-recipe-form");
    forms.forEach(function (form) {
        var name = form.dataset.name || "";
        form.style.display = name.includes(normalized) ? "block" : "none";
    });
}

function toggleModalSortOrder(btnElement, containerId) {
    var container = document.getElementById(containerId || "modal-recipes-container");
    if (!container) return;

    var currentOrder = btnElement.getAttribute("data-order") || "desc";
    var nextOrder = currentOrder === "asc" ? "desc" : "asc";
    btnElement.setAttribute("data-order", nextOrder);

    var icon = btnElement.querySelector(".sort-order-icon") || btnElement.querySelector("i");
    if (icon) {
        icon.className = (nextOrder === "asc" ? "fa-solid fa-arrow-up" : "fa-solid fa-arrow-down") + " sort-order-icon";
    }
    btnElement.title = nextOrder === "asc" ? "Tri croissant (cliquer pour inverser)" : "Tri décroissant (cliquer pour inverser)";

    var topBar = btnElement.closest(".modal-top-bar");
    var activePill = topBar ? topBar.querySelector(".modal-sort-pills .sort-pill.active") : null;
    var currentSortKey = activePill ? (activePill.getAttribute("data-sort") || "date") : "date";

    sortModalRecipes(currentSortKey, activePill, containerId, nextOrder);
}
window.toggleModalSortOrder = toggleModalSortOrder;

function sortModalRecipes(sortKey, btnElement, containerId, forcedOrder) {
    var container = document.getElementById(containerId || "modal-recipes-container");
    if (!container) return;

    if (sortKey === "recent" && !forcedOrder) {
        sortKey = "date";
        forcedOrder = "desc";
    } else if (sortKey === "oldest" && !forcedOrder) {
        sortKey = "date";
        forcedOrder = "asc";
    }

    var topBar = btnElement ? btnElement.closest(".modal-top-bar") : null;
    if (!topBar) {
        var modal = container.closest(".modal") || container.closest(".app-modal") || container.parentElement;
        if (modal) topBar = modal.querySelector(".modal-top-bar");
    }

    var orderBtn = topBar ? topBar.querySelector(".modal-sort-order-btn") : null;

    var order = forcedOrder;
    if (!order) {
        if (btnElement && btnElement.classList.contains("active") && orderBtn) {
            order = (orderBtn.getAttribute("data-order") === "asc") ? "desc" : "asc";
        } else {
            order = (sortKey === "alpha" || sortKey === "rank") ? "asc" : "desc";
        }
    }

    if (orderBtn) {
        orderBtn.setAttribute("data-order", order);
        var icon = orderBtn.querySelector(".sort-order-icon") || orderBtn.querySelector("i");
        if (icon) {
            icon.className = (order === "asc" ? "fa-solid fa-arrow-up" : "fa-solid fa-arrow-down") + " sort-order-icon";
        }
        orderBtn.title = order === "asc" ? "Tri croissant (cliquer pour inverser)" : "Tri décroissant (cliquer pour inverser)";
    }

    if (btnElement && btnElement.parentNode) {
        btnElement.parentNode.querySelectorAll(".sort-pill").forEach(function (pill) {
            pill.classList.remove("active");
        });
        btnElement.classList.add("active");
    }

    var forms = Array.from(container.querySelectorAll(".modal-recipe-form"));
    if (forms.length <= 1) return;

    var isAsc = (order === "asc");

    forms.sort(function (a, b) {
        var dateA = parseInt(a.dataset.date || "0", 10);
        var dateB = parseInt(b.dataset.date || "0", 10);
        var idA = parseInt(a.dataset.id || "0", 10);
        var idB = parseInt(b.dataset.id || "0", 10);
        var noteA = parseFloat(a.dataset.note || "0");
        var noteB = parseFloat(b.dataset.note || "0");
        var countA = parseInt(a.dataset.count || "0", 10);
        var countB = parseInt(b.dataset.count || "0", 10);
        var rankA = parseInt(a.dataset.rank || "999999", 10);
        var rankB = parseInt(b.dataset.rank || "999999", 10);
        var nameA = (a.dataset.name || "").trim();
        var nameB = (b.dataset.name || "").trim();

        if (sortKey === "date" || sortKey === "recent" || sortKey === "oldest") {
            if (dateA === 0 && dateB !== 0) return 1;
            if (dateB === 0 && dateA !== 0) return -1;
            if (dateA === dateB) return isAsc ? (idA - idB) : (idB - idA);
            return isAsc ? (dateA - dateB) : (dateB - dateA);
        } else if (sortKey === "note") {
            if (noteA === noteB) {
                if (countA === countB) return isAsc ? (idA - idB) : (idB - idA);
                return isAsc ? (countA - countB) : (countB - countA);
            }
            return isAsc ? (noteA - noteB) : (noteB - noteA);
        } else if (sortKey === "rank") {
            if (rankA === 999999 && rankB !== 999999) return 1;
            if (rankB === 999999 && rankA !== 999999) return -1;
            if (rankA === rankB) {
                if (countA === countB) return isAsc ? (idA - idB) : (idB - idA);
                return isAsc ? (countA - countB) : (countB - countA);
            }
            return isAsc ? (rankA - rankB) : (rankB - rankA);
        } else if (sortKey === "alpha") {
            var cmp = nameA.localeCompare(nameB, "fr", { sensitivity: "base" });
            return isAsc ? cmp : -cmp;
        }
        return 0;
    });

    forms.forEach(function (form) {
        container.appendChild(form);
    });
}
window.sortModalRecipes = sortModalRecipes;

document.addEventListener("DOMContentLoaded", function () {
    var container = document.getElementById("modal-recipes-container");
    if (container) {
        container.addEventListener("submit", function (e) {
            var form = e.target;
            if (form && form.classList.contains("modal-recipe-form")) {
                var file = window.selectedRepasPhotoFile;
                var input = window.selectedRepasPhotoInput;
                if (file && input && input.files && input.files.length > 0) {
                    form.enctype = "multipart/form-data";
                    if (window.DataTransfer) {
                        try {
                            var dt = new DataTransfer();
                            dt.items.add(file);
                            var hiddenInput = document.createElement("input");
                            hiddenInput.type = "file";
                            hiddenInput.name = "photo";
                            hiddenInput.style.display = "none";
                            hiddenInput.files = dt.files;
                            form.appendChild(hiddenInput);
                            return;
                        } catch (err) {
                            // Fallback if DataTransfer fails
                        }
                    }
                    input.name = "photo";
                    form.appendChild(input);
                }
            }
        });
    }

    var sortPills = document.querySelector("#repas-modal .modal-sort-pills");
    if (sortPills) {
        sortPills.addEventListener("wheel", function (e) {
            if (e.deltaY !== 0) {
                e.preventDefault();
                sortPills.scrollLeft += e.deltaY;
            }
        }, { passive: false });
    }
});
