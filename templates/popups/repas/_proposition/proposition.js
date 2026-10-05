function openPropositionModal() {
    openModal("repas-proposition-modal");
    var input = document.getElementById("proposition-recipe-filter-input");
    if (input) {
        input.value = "";
        filterPropositionRecipesList("");
        setTimeout(function () {
            input.focus();
        }, 100);
    }
}

function closePropositionModal() {
    closeModal("repas-proposition-modal");
}

function selectPropositionMoment(moment) {
    document.querySelectorAll(".btn-prop-moment-toggle").forEach(function (btn) {
        btn.classList.toggle("active", btn.dataset.moment === moment);
    });
    document.querySelectorAll(".input-prop-form-moment").forEach(function (input) {
        input.value = moment;
    });
}

function selectPropositionDate(dateStr, btnElement) {
    var dateInput = document.getElementById("proposition-date-input");
    if (dateInput) {
        dateInput.value = dateStr;
    }
    document.querySelectorAll(".input-prop-form-date").forEach(function (input) {
        input.value = dateStr;
    });
    document.querySelectorAll(".btn-date-quick").forEach(function (btn) {
        btn.classList.remove("active");
    });
    if (btnElement) {
        btnElement.classList.add("active");
    }
}

function onPropositionDateInputChange(val) {
    document.querySelectorAll(".input-prop-form-date").forEach(function (input) {
        input.value = val;
    });
    document.querySelectorAll(".btn-date-quick").forEach(function (btn) {
        btn.classList.remove("active");
    });
}

function filterPropositionRecipesList(term) {
    var normalized = term.trim().toLowerCase();
    var forms = document.querySelectorAll(".modal-prop-recipe-form");
    forms.forEach(function (form) {
        var name = form.dataset.name || "";
        form.style.display = name.includes(normalized) ? "block" : "none";
    });
}
