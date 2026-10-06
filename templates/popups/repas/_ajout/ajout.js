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
    var forms = document.querySelectorAll(".modal-recipe-form");
    forms.forEach(function (form) {
        var name = form.dataset.name || "";
        form.style.display = name.includes(normalized) ? "block" : "none";
    });
}
