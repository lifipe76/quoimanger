// Interactions et retours tactiles de la barre de navigation mobile
document.addEventListener('DOMContentLoaded', function () {
    var navItems = document.querySelectorAll('.bottom-nav-item, .bottom-nav-center-btn, .bottom-brand-popup-item');
    navItems.forEach(function (item) {
        item.addEventListener('click', function () {
            if (window.navigator && window.navigator.vibrate) {
                try {
                    window.navigator.vibrate(12);
                } catch (e) {}
            }
        });
    });

    // Mobile Brand Switcher (QuoiManger 🍽️ <-> QuoiFaire 🏠)
    var centerAction = document.getElementById('bottomNavCenterAction');
    var centerBtn = document.getElementById('bottomNavCenterBtn');
    var brandPopup = document.getElementById('bottomBrandPopup');

    if (centerAction && centerBtn && brandPopup) {
        var isTouch = false;
        centerAction.addEventListener('touchstart', function () {
            isTouch = true;
        }, { passive: true });

        centerBtn.addEventListener('click', function (e) {
            if (isTouch && !brandPopup.classList.contains('is-open')) {
                e.preventDefault();
                brandPopup.classList.add('is-open');
            }
        });

        document.addEventListener('click', function (e) {
            if (!centerAction.contains(e.target)) {
                brandPopup.classList.remove('is-open');
            }
        });
    }
});
