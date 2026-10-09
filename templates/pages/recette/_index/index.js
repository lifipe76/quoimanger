document.addEventListener("DOMContentLoaded", function () {
    var sortBtn = document.querySelector(".sort-order-btn");
    if (!sortBtn) return;
    var icon = sortBtn.querySelector("i.sort-order-icon");
    if (icon && (!icon.offsetWidth || window.getComputedStyle(icon, ":before").content === "none")) {
        var isAsc = icon.classList.contains("fa-arrow-up");
        icon.innerHTML = isAsc
            ? '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:block;"><line x1="12" y1="19" x2="12" y2="5"></line><polyline points="5 12 12 5 19 12"></polyline></svg>'
            : '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:block;"><line x1="12" y1="5" x2="12" y2="19"></line><polyline points="19 12 12 19 5 12"></polyline></svg>';
    }
});
