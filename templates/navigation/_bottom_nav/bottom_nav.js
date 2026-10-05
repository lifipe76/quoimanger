// Interactions et retours tactiles de la barre de navigation mobile
document.addEventListener('DOMContentLoaded', function () {
    var navItems = document.querySelectorAll('.bottom-nav-item, .bottom-nav-center-btn');
    navItems.forEach(function (item) {
        item.addEventListener('click', function () {
            if (window.navigator && window.navigator.vibrate) {
                try {
                    window.navigator.vibrate(12);
                } catch (e) {}
            }
        });
    });
});
