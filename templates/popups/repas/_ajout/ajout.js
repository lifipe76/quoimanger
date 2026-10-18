function getStorageKeyForContainer(containerId) {
    if (!containerId) return "quoiManger_sort_repas";
    if (containerId.indexOf("proposition") !== -1) return "quoiManger_sort_proposition";
    if (containerId.indexOf("frigo") !== -1 && containerId.indexOf("ajout") === -1 && containerId.indexOf("prop") === -1) {
        return "quoiManger_sort_frigo";
    }
    return "quoiManger_sort_repas";
}
window.getStorageKeyForContainer = getStorageKeyForContainer;

function restoreModalSort(containerId) {
    var container = document.getElementById(containerId);
    if (!container) return;

    var storageKey = getStorageKeyForContainer(containerId);
    var saved = null;
    try {
        var raw = localStorage.getItem(storageKey);
        if (raw) saved = JSON.parse(raw);
    } catch (e) {}

    if (!saved || !saved.sort) return;

    var modal = container.closest(".modal-overlay") || container.closest(".modal") || container.parentElement;
    var topBar = modal ? modal.querySelector(".modal-top-bar") : null;
    if (!topBar) {
        topBar = document.getElementById("repas-modal-top-bar") || document.getElementById("proposition-modal-top-bar") || document.getElementById("frigo-modal-top-bar");
    }

    var pill = topBar ? topBar.querySelector(".modal-sort-pills .sort-pill[data-sort='" + saved.sort + "']") : null;
    if (pill) {
        window.sortModalRecipes(saved.sort, pill, containerId, saved.order || "desc");
    }
}
window.restoreModalSort = restoreModalSort;

function openRepasModal(defaultTab) {
    openModal("repas-modal");

    var activeTab = defaultTab;
    if (!activeTab) {
        try {
            activeTab = localStorage.getItem("quoiManger_repas_tab") || "toutes";
        } catch (e) {
            activeTab = "toutes";
        }
    }
    switchRepasTab(activeTab);

    // Restaurer le tri mémorisé sur les deux onglets
    restoreModalSort("modal-recipes-container");
    restoreModalSort("modal-ajout-frigo-container");

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

function switchRepasTab(tab) {
    try {
        localStorage.setItem("quoiManger_repas_tab", tab);
    } catch (e) {}

    var btnToutes = document.getElementById("tab-btn-ajout-toutes");
    var btnFrigo = document.getElementById("tab-btn-ajout-frigo");
    var contToutes = document.getElementById("modal-recipes-container");
    var contFrigo = document.getElementById("modal-ajout-frigo-container");
    var topBar = document.getElementById("repas-modal-top-bar");

    if (tab === "frigo") {
        if (btnToutes) btnToutes.classList.remove("active");
        if (btnFrigo) btnFrigo.classList.add("active");
        if (contToutes) {
            contToutes.style.display = "none";
            contToutes.classList.remove("active");
        }
        if (contFrigo) {
            contFrigo.style.display = "block";
            contFrigo.classList.add("active");
        }
    } else {
        if (btnFrigo) btnFrigo.classList.remove("active");
        if (btnToutes) btnToutes.classList.add("active");
        if (contFrigo) {
            contFrigo.style.display = "none";
            contFrigo.classList.remove("active");
        }
        if (contToutes) {
            contToutes.style.display = "block";
            contToutes.classList.add("active");
        }
    }

    // Ré-appliquer le tri actif sur l'onglet affiché
    var activePill = topBar ? topBar.querySelector(".modal-sort-pills .sort-pill.active") : null;
    var orderBtn = topBar ? topBar.querySelector(".modal-sort-order-btn") : null;
    if (activePill) {
        var sortKey = activePill.getAttribute("data-sort") || "probability";
        var order = orderBtn ? (orderBtn.getAttribute("data-order") || "desc") : "desc";
        var targetContainerId = (tab === "frigo") ? "modal-ajout-frigo-container" : "modal-recipes-container";
        sortModalRecipes(sortKey, activePill, targetContainerId, order);
    }

    var input = document.getElementById("recipe-filter-input");
    if (input && input.value) {
        filterRecipesList(input.value);
    }
}
window.switchRepasTab = switchRepasTab;

function filterRecipesList(term) {
    var normalized = term.trim().toLowerCase();
    var forms = document.querySelectorAll("#modal-recipes-container .modal-recipe-form, #modal-ajout-frigo-container .modal-recipe-form");
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
    var currentSortKey = activePill ? (activePill.getAttribute("data-sort") || "probability") : "probability";

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

    // Sauvegarder le tri sélectionné dans le localStorage
    try {
        var storageKey = getStorageKeyForContainer(containerId);
        localStorage.setItem(storageKey, JSON.stringify({ sort: sortKey, order: order }));
    } catch (e) {}

    var forms = Array.from(container.querySelectorAll(".modal-recipe-form"));
    if (forms.length <= 1) return;

    var isAsc = (order === "asc");

    forms.sort(function (a, b) {
        var probA = parseFloat(a.dataset.probability || "0");
        var probB = parseFloat(b.dataset.probability || "0");
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

        if (sortKey === "probability") {
            if (probA === probB) {
                var cmpP = nameA.localeCompare(nameB, "fr", { sensitivity: "base" });
                return isAsc ? cmpP : -cmpP;
            }
            return isAsc ? (probA - probB) : (probB - probA);
        } else if (sortKey === "date" || sortKey === "recent" || sortKey === "oldest") {
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
    var sortPills = document.querySelector("#repas-modal .modal-sort-pills");
    if (sortPills) {
        sortPills.addEventListener("wheel", function (e) {
            if (e.deltaY !== 0) {
                e.preventDefault();
                sortPills.scrollLeft += e.deltaY;
            }
        }, { passive: false });
    }

    // Restaurer le tri des modales à l'initialisation
    restoreModalSort("modal-recipes-container");
    restoreModalSort("modal-ajout-frigo-container");
});
