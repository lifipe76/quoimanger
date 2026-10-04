function openComplementModal(
    updateUrl,
    currentComplement,
    recipeTitle,
    updateToken,
) {
    var modal = document.getElementById("complement-modal");
    var form = document.getElementById("complement-form");
    var input = document.getElementById("complement-input");
    var tokenInput = document.getElementById("complement-token");
    var titleEl = document.getElementById("complement-modal-recipe-name");

    if (modal && form && input) {
        form.action = updateUrl;
        input.value = currentComplement || "";
        if (tokenInput) {
            tokenInput.value = updateToken;
        }
        if (titleEl) {
            titleEl.textContent = recipeTitle || "";
        }
        openModal("complement-modal");
        setTimeout(function () {
            input.focus();
            input.select();
        }, 100);
    }
}

function closeComplementModal() {
    closeModal("complement-modal");
}

function handleComplementBackdropClick(event) {
    handleModalBackdropClick(event, "complement-modal");
}

function clearComplementInput() {
    var input = document.getElementById("complement-input");
    if (input) {
        input.value = "";
        input.focus();
    }
}
