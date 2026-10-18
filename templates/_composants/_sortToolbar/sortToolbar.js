/* ==========================================================================
   Composant Sort Toolbar - JavaScript
   - Persistance du tri via localStorage si data-storage-key est défini
   - Fallback SVG pour l'icône de tri
   ========================================================================== */

document.addEventListener("DOMContentLoaded", function () {
    // 1. Persistance automatique dans localStorage
    var toolbar = document.querySelector(".sort-toolbar[data-storage-key]");
    if (toolbar) {
        var key = toolbar.dataset.storageKey;
        var defaultSort = toolbar.dataset.defaultSort || "";
        var defaultOrder = toolbar.dataset.defaultOrder || "asc";

        try {
            var params = new URLSearchParams(window.location.search);
            var currentSort = params.get("sort");
            var currentOrder = params.get("order");

            if (currentSort) {
                localStorage.setItem(key + "_sort", currentSort);
                if (currentOrder) {
                    localStorage.setItem(key + "_order", currentOrder);
                }
            } else if (!window.location.search) {
                var savedSort = localStorage.getItem(key + "_sort");
                var savedOrder = localStorage.getItem(key + "_order");
                if (savedSort && (savedSort !== defaultSort || (savedOrder && savedOrder !== defaultOrder))) {
                    var url = new URL(window.location.href);
                    url.searchParams.set("sort", savedSort);
                    if (savedOrder) {
                        url.searchParams.set("order", savedOrder);
                    }
                    window.location.replace(url.toString());
                    return;
                }
            }
        } catch (e) {
            console.warn("Storage access failed in sortToolbar:", e);
        }
    }

    // 2. Fallback SVG si FontAwesome n'affiche pas les pseudo-éléments de l'icône
    document.querySelectorAll(".sort-order-btn").forEach(function (sortBtn) {
        var icon = sortBtn.querySelector("i.sort-order-icon");
        if (icon && (!icon.offsetWidth || window.getComputedStyle(icon, ":before").content === "none")) {
            var isAsc = icon.classList.contains("fa-arrow-up");
            icon.innerHTML = isAsc
                ? '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:block;"><line x1="12" y1="19" x2="12" y2="5"></line><polyline points="5 12 12 5 19 12"></polyline></svg>'
                : '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:block;"><line x1="12" y1="5" x2="12" y2="19"></line><polyline points="19 12 12 19 5 12"></polyline></svg>';
        }
    });
});
