document.addEventListener("DOMContentLoaded", function () {
    const wrapper = document.getElementById("ingredients-wrapper");
    const addBtn = document.getElementById("add-ingredient-btn");
    const emptyMsg = document.getElementById("no-ingredients-msg");

    function checkEmptyState() {
        if (!emptyMsg) return;
        const rows = wrapper.querySelectorAll(".ingredient-row");
        emptyMsg.style.display = rows.length === 0 ? "block" : "none";
    }

    function bindRemoveButtons(container) {
        container.querySelectorAll(".remove-row-btn").forEach((btn) => {
            btn.onclick = function () {
                btn.closest(".ingredient-row").remove();
                checkEmptyState();
            };
        });
    }

    if (addBtn && wrapper) {
        addBtn.addEventListener("click", function () {
            const prototype = wrapper.dataset.prototype;
            let index = parseInt(wrapper.dataset.index, 10) || 0;
            const newHtml = prototype.replace(/__name__/g, index);
            wrapper.dataset.index = index + 1;

            const temp = document.createElement("div");
            temp.innerHTML = newHtml;
            const newRow = temp.firstElementChild;
            wrapper.appendChild(newRow);
            bindRemoveButtons(newRow);
            checkEmptyState();
        });
    }

    bindRemoveButtons(wrapper);
    checkEmptyState();
});
