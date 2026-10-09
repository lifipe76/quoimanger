/* ==========================================================================
   Composant Section Note pour Popups
   ========================================================================== */

/**
 * Attribue ou retire une note (étoiles) pour un membre dans le popup.
 *
 * @param {number|string} userId
 * @param {number} star
 * @param {string} [prefix]
 */
function setModalMemberStar(userId, star, prefix) {
    var pfx = prefix || "edit-meal";
    var input = document.getElementById(pfx + "-note-" + userId);
    if (!input) return;

    var currentVal = parseInt(input.value, 10) || 0;
    var newVal = (currentVal === star) ? 0 : star;
    input.value = newVal;

    var container = document.querySelector("#" + pfx + "-ratings-container .modal-star-rating[data-user-id='" + userId + "']");
    if (container) {
        container.querySelectorAll(".modal-star-btn").forEach(function (btn) {
            var s = parseInt(btn.dataset.star, 10);
            btn.style.color = (s <= newVal) ? "#f59e0b" : "#cbd5e1";
        });
    }
}
