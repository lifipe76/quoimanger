// =========================================================================
// Navigation photos multiples sur les cards (recette & ingrédient)
// =========================================================================
if (typeof window.changeCardPhoto !== 'function') {
    window.changeCardPhoto = function (event, trigger, delta) {
        if (event) {
            if (typeof event.stopPropagation === 'function') event.stopPropagation();
            if (typeof event.preventDefault === 'function') event.preventDefault();
        }
        var slider = trigger && trigger.closest ? trigger.closest('.card-photo-slider') : null;
        if (!slider) return;

        var photos = [];
        try {
            photos = JSON.parse(slider.getAttribute('data-photos') || '[]');
        } catch (e) {
            photos = [];
        }
        if (!photos || photos.length <= 1) return;

        var cur = parseInt(slider.getAttribute('data-current-index') || '0', 10);
        if (isNaN(cur)) cur = 0;
        var next = (cur + delta + photos.length) % photos.length;

        window.updateCardPhotoSliderIndex(slider, photos, next);
    };
}

if (typeof window.selectCardPhoto !== 'function') {
    window.selectCardPhoto = function (event, trigger, targetIndex) {
        if (event) {
            if (typeof event.stopPropagation === 'function') event.stopPropagation();
            if (typeof event.preventDefault === 'function') event.preventDefault();
        }
        var slider = trigger && trigger.closest ? trigger.closest('.card-photo-slider') : null;
        if (!slider) return;

        var photos = [];
        try {
            photos = JSON.parse(slider.getAttribute('data-photos') || '[]');
        } catch (e) {
            photos = [];
        }
        if (!photos || photos.length <= 1) return;

        var next = (targetIndex + photos.length) % photos.length;
        window.updateCardPhotoSliderIndex(slider, photos, next);
    };
}

if (typeof window.updateCardPhotoSliderIndex !== 'function') {
    window.updateCardPhotoSliderIndex = function (slider, photos, next) {
        slider.setAttribute('data-current-index', next);

        var nextUrl = photos[next];
        if (!nextUrl) return;

        // Photo de fond (card recette)
        var bgEl = slider.querySelector('.card-recette-bg-photo');
        if (bgEl) {
            bgEl.style.backgroundImage = 'url("' + nextUrl + '")';
        }

        // Image tag (card ingrédient)
        var imgEl = slider.querySelector('.card-ingredient-bg-photo');
        if (imgEl) {
            imgEl.src = nextUrl;
        }

        // Numéro de l'index courant dans le badge (ex: 1/3)
        var idxEl = slider.querySelector('.card-photo-current-idx');
        if (idxEl) {
            idxEl.textContent = (next + 1);
        }

        // Mise à jour de la classe active sur les points
        var dots = slider.querySelectorAll('.card-photo-dot');
        dots.forEach(function (dot, i) {
            dot.classList.toggle('active', i === next);
        });
    };
}

if (typeof window.initCardPhotoTouchSwipe !== 'function') {
    window.initCardPhotoTouchSwipe = function (root) {
        var scope = root || document;
        scope.querySelectorAll('.card-photo-slider').forEach(function (slider) {
            if (slider._photoSwipeBound) return;
            slider._photoSwipeBound = true;

            var startX = 0;
            var startY = 0;
            slider.addEventListener('touchstart', function (e) {
                if (e.touches && e.touches.length === 1) {
                    startX = e.touches[0].clientX;
                    startY = e.touches[0].clientY;
                }
            }, { passive: true });

            slider.addEventListener('touchend', function (e) {
                if (e.changedTouches && e.changedTouches.length === 1) {
                    var diffX = e.changedTouches[0].clientX - startX;
                    var diffY = e.changedTouches[0].clientY - startY;
                    if (Math.abs(diffX) > 40 && Math.abs(diffX) > Math.abs(diffY)) {
                        if (diffX < 0) {
                            window.changeCardPhoto(e, slider, 1);
                        } else {
                            window.changeCardPhoto(e, slider, -1);
                        }
                    }
                }
            }, { passive: true });
        });
    };
}

document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.initCardPhotoTouchSwipe === 'function') {
        window.initCardPhotoTouchSwipe();
    }
});
