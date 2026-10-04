document.addEventListener("DOMContentLoaded", function () {
    const tabButtons = document.querySelectorAll(".account-tab-btn");
    const tabPanes = document.querySelectorAll(".account-tab-pane");

    function switchTab(tabName) {
        if (!tabName) return;

        let found = false;
        tabButtons.forEach(function (btn) {
            const isTarget = btn.getAttribute("data-tab") === tabName;
            btn.classList.toggle("active", isTarget);
            btn.setAttribute("aria-selected", isTarget ? "true" : "false");
            if (isTarget) found = true;
        });

        tabPanes.forEach(function (pane) {
            const isTarget = pane.id === "tab-" + tabName;
            pane.classList.toggle("active", isTarget);
        });

        if (found) {
            const currentUrl = new URL(window.location.href);
            if (currentUrl.searchParams.get("tab") !== tabName) {
                currentUrl.searchParams.set("tab", tabName);
                window.history.replaceState({}, "", currentUrl.toString());
            }
        }
    }

    tabButtons.forEach(function (btn) {
        btn.addEventListener("click", function () {
            const tabName = btn.getAttribute("data-tab");
            switchTab(tabName);
        });
    });

    // Initialisation depuis l'ancre URL (#famille) ou paramètre URL (?tab=famille)
    const urlParams = new URLSearchParams(window.location.search);
    const queryTab = urlParams.get("tab");
    const hash = window.location.hash.replace("#", "");

    if (hash === "famille" || hash === "profil") {
        switchTab(hash);
    } else if (queryTab === "famille" || queryTab === "profil") {
        switchTab(queryTab);
    }
});
