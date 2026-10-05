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
    document.querySelectorAll(".btn-moment-toggle").forEach(function (btn) {
        btn.classList.toggle("active", btn.dataset.moment === moment);
    });
    document.querySelectorAll(".input-form-moment").forEach(function (input) {
        input.value = moment;
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
