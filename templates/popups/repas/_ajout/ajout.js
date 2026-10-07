function openRepasModal() {
    openModal("repas-modal");
    var input = document.getElementById("recipe-filter-input");
    if (input) {
        input.value = "";
        filterRecipesList("");
        setTimeout(function () {
            input.focus();
        }, 100);
    }
}

function closeRepasModal() {
    closeModal("repas-modal");
}

function selectRepasMoment(moment) {
    document.querySelectorAll("#repas-modal .btn-moment-toggle").forEach(function (btn) {
        btn.classList.toggle("active", btn.dataset.moment === moment);
    });
    document.querySelectorAll("#repas-modal .input-form-moment").forEach(function (input) {
        input.value = moment;
    });
}

function selectRepasDate(dateStr, btnElement) {
    var dateInput = document.getElementById("repas-date-input");
    if (dateInput) {
        dateInput.value = dateStr;
    }
    document.querySelectorAll("#repas-modal .input-form-date").forEach(function (input) {
        input.value = dateStr;
    });
    document.querySelectorAll("#repas-modal .btn-date-quick").forEach(function (btn) {
        btn.classList.remove("active");
    });
    if (btnElement) {
        btnElement.classList.add("active");
    }
}

function onRepasDateInputChange(val) {
    document.querySelectorAll("#repas-modal .input-form-date").forEach(function (input) {
        input.value = val;
    });
    document.querySelectorAll("#repas-modal .btn-date-quick").forEach(function (btn) {
        btn.classList.remove("active");
    });
}

function filterRecipesList(term) {
    var normalized = term.trim().toLowerCase();
    var forms = document.querySelectorAll("#modal-recipes-container .modal-recipe-form");
    forms.forEach(function (form) {
        var name = form.dataset.name || "";
        form.style.display = name.includes(normalized) ? "block" : "none";
    });
}

function sortModalRecipes(sortKey, btnElement, containerId) {
    var container = document.getElementById(containerId || "modal-recipes-container");
    if (!container) return;

    if (btnElement && btnElement.parentNode) {
        btnElement.parentNode.querySelectorAll(".sort-pill").forEach(function (pill) {
            pill.classList.remove("active");
        });
        btnElement.classList.add("active");
    }

    var forms = Array.from(container.querySelectorAll(".modal-recipe-form"));
    if (forms.length <= 1) return;

    forms.sort(function (a, b) {
        var dateA = parseInt(a.dataset.date || "0", 10);
        var dateB = parseInt(b.dataset.date || "0", 10);
        var idA = parseInt(a.dataset.id || "0", 10);
        var idB = parseInt(b.dataset.id || "0", 10);
        var noteA = parseFloat(a.dataset.note || "0");
        var noteB = parseFloat(b.dataset.note || "0");
        var countA = parseInt(a.dataset.count || "0", 10);
        var countB = parseInt(b.dataset.count || "0", 10);
        var nameA = (a.dataset.name || "").trim();
        var nameB = (b.dataset.name || "").trim();

        if (sortKey === "recent") {
            if (dateA === 0 && dateB !== 0) return 1;
            if (dateB === 0 && dateA !== 0) return -1;
            if (dateA === dateB) return idB - idA;
            return dateB - dateA;
        } else if (sortKey === "oldest") {
            if (dateA === 0 && dateB !== 0) return 1;
            if (dateB === 0 && dateA !== 0) return -1;
            if (dateA === dateB) return idA - idB;
            return dateA - dateB;
        } else if (sortKey === "note") {
            if (noteA === noteB) {
                if (countA === countB) return idB - idA;
                return countB - countA;
            }
            return noteB - noteA;
        } else if (sortKey === "alpha") {
            return nameA.localeCompare(nameB, "fr", { sensitivity: "base" });
        }
        return 0;
    });

    forms.forEach(function (form) {
        container.appendChild(form);
    });
}
