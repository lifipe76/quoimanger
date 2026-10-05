(function () {
    var pendingConfirmCallback = null;

    window.openConfirmPopup = function (options) {
        options = options || {};
        var title = options.title || "Confirmation requise";
        var message = options.message || "Êtes-vous sûr de vouloir effectuer cette action ?";
        var confirmText = options.confirmText || "Confirmer";
        var confirmClass = options.confirmClass || "btn-danger";

        var titleEl = document.getElementById("app-confirm-title");
        var msgEl = document.getElementById("app-confirm-message");
        var btnEl = document.getElementById("app-confirm-btn");

        if (titleEl) titleEl.textContent = title;
        if (msgEl) msgEl.textContent = message;
        if (btnEl) {
            btnEl.textContent = confirmText;
            btnEl.className = "btn button " + confirmClass;
        }

        pendingConfirmCallback = options.onConfirm || null;
        if (typeof openModal === "function") {
            openModal("app-confirm-modal");
        }
    };

    document.addEventListener("DOMContentLoaded", function () {
        var confirmBtn = document.getElementById("app-confirm-btn");
        if (confirmBtn) {
            confirmBtn.addEventListener("click", function () {
                var cb = pendingConfirmCallback;
                pendingConfirmCallback = null;
                if (typeof closeModal === "function") {
                    closeModal("app-confirm-modal");
                }
                if (typeof cb === "function") {
                    cb();
                }
            });
        }

        // Intercepter automatiquement les formulaires avec data-confirm
        document.addEventListener("submit", function (e) {
            var form = e.target;
            if (form && form.dataset && form.dataset.confirm && !form.dataset.confirmed) {
                e.preventDefault();
                window.openConfirmPopup({
                    title: form.dataset.confirmTitle || "Confirmation de suppression",
                    message: form.dataset.confirm,
                    confirmText: form.dataset.confirmBtn || "Supprimer",
                    confirmClass: form.dataset.confirmClass || "btn-danger",
                    onConfirm: function () {
                        form.dataset.confirmed = "true";
                        form.submit();
                    }
                });
            }
        });
    });
})();
