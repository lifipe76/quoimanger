/* ==========================================================================
   Composant Section Personnes / Participants pour Popups
   ========================================================================== */

document.addEventListener("change", function (e) {
    if (e.target && e.target.classList.contains("participant-checkbox")) {
        var label = e.target.closest(".participant-toggle-label");
        if (label) {
            label.style.borderColor = e.target.checked ? "var(--primary, #c4a587)" : "#cbd5e1";
            label.style.background = e.target.checked ? "#fdf8f4" : "#f8fafc";
        }
    }
});
