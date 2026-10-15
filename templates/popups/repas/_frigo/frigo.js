function openFrigoModal() {
    openModal("repas-frigo-modal");

    if (typeof restoreModalSort === "function") {
        restoreModalSort("modal-frigo-recipes-container");
    }

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

document.addEventListener("DOMContentLoaded", function () {
    var sortPills = document.querySelector("#repas-frigo-modal .modal-sort-pills");
    if (sortPills) {
        sortPills.addEventListener("wheel", function (e) {
            if (e.deltaY !== 0) {
                e.preventDefault();
                sortPills.scrollLeft += e.deltaY;
            }
        }, { passive: false });
    }

    if (typeof restoreModalSort === "function") {
        restoreModalSort("modal-frigo-recipes-container");
    }
});
