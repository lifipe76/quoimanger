function togglePasswordVisibility(inputId, btn) {
    var input = document.getElementById(inputId);
    if (!input) return;

    var isPassword = input.type === "password";
    input.type = isPassword ? "text" : "password";

    var icon = btn
        ? btn.querySelector("i") || (btn.tagName === "I" ? btn : null)
        : null;
    if (icon) {
        if (isPassword) {
            icon.classList.remove("fa-eye");
            icon.classList.add("fa-eye-slash");
        } else {
            icon.classList.remove("fa-eye-slash");
            icon.classList.add("fa-eye");
        }
    }
    if (btn) {
        var label = isPassword
            ? "Masquer le mot de passe"
            : "Afficher le mot de passe";
        btn.setAttribute("title", label);
        btn.setAttribute("aria-label", label);
    }
}
