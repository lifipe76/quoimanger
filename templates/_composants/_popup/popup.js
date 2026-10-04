(function () {
    window.openModal = function (modalId) {
        var modal = typeof modalId === "string" ? document.getElementById(modalId) : modalId;
        if (modal) {
            modal.style.display = "flex";
            document.body.style.overflow = "hidden";
            var autoFocusEl = modal.querySelector("[autofocus], input:not([type=hidden]), textarea, select");
            if (autoFocusEl) {
                setTimeout(function () {
                    try { autoFocusEl.focus(); } catch (e) {}
                }, 100);
            }
        }
    };

    window.closeModal = function (modalId) {
        if (modalId) {
            var modal = typeof modalId === "string" ? document.getElementById(modalId) : modalId;
            if (modal) {
                modal.style.display = "none";
            }
        } else {
            document.querySelectorAll(".modal-overlay").forEach(function (m) {
                m.style.display = "none";
            });
        }

        // Restaurer le scroll s'il n'y a plus aucun modal ouvert
        var openModals = Array.from(document.querySelectorAll(".modal-overlay")).filter(function (m) {
            return m.style.display !== "none" && window.getComputedStyle(m).display !== "none";
        });
        if (openModals.length === 0) {
            document.body.style.overflow = "";
        }
    };

    window.handleModalBackdropClick = function (event, modalId) {
        if (event.target === event.currentTarget) {
            closeModal(modalId || event.currentTarget.id);
        }
    };

    // Gestion de la touche Échap pour fermer les modales
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") {
            closeModal();
        }
    });

    // Fonctions de compatibilité avec le code existant
    window.closeRepasModal = function () {
        closeModal("repas-modal");
    };

    window.closeEditMealModal = function () {
        closeModal("edit-meal-modal");
    };

    window.closeComplementModal = function () {
        closeModal("complement-modal");
    };

    window.handleEditMealBackdropClick = function (event) {
        handleModalBackdropClick(event, "edit-meal-modal");
    };

    window.handleComplementBackdropClick = function (event) {
        handleModalBackdropClick(event, "complement-modal");
    };
})();
