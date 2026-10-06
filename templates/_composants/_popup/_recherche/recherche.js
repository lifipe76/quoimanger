/* ==========================================================================
   Composant Barre de Recherche pour Popups / Modales - JavaScript
   ========================================================================== */

/**
 * Filtre les éléments d'une liste de popup en fonction d'un terme et d'un sélecteur cible.
 *
 * @param {string} term Le terme recherché
 * @param {string} targetSelector Sélecteur CSS des éléments à filtrer (ex: '.modal-recipe-form')
 */
function filterPopupList(term, targetSelector) {
    if (!targetSelector) return;
    var normalized = (term || "").trim().toLowerCase();
    var items = document.querySelectorAll(targetSelector);
    items.forEach(function (item) {
        var name = (item.dataset.name || item.textContent || "").toLowerCase();
        item.style.display = name.includes(normalized) ? "" : "none";
    });
}
