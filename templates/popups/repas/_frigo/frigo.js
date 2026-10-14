function openFrigoModal() {
    openModal("repas-frigo-modal");
    var wrapper = document.getElementById("frigo-recipe-filter-input-wrapper");
    if (wrapper) {
        wrapper.classList.remove("is-open");
        wrapper.classList.remove("has-value");
    }
    var input = document.getElementById("frigo-recipe-filter-input");
    if (input) {
        input.value = "";
        filterFrigoRecipesList("");
    }
}

function closeFrigoModal() {
    closeModal("repas-frigo-modal");
}

function filterFrigoRecipesList(term) {
    var normalized = term.trim().toLowerCase();
    var forms = document.querySelectorAll("#modal-frigo-recipes-container .modal-frigo-recipe-form");
    forms.forEach(function (form) {
        var name = form.dataset.name || "";
        form.style.display = name.includes(normalized) ? "block" : "none";
    });
}
