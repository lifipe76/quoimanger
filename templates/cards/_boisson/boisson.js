// =========================================================================
// Navigation photos multiples sur les cards boisson
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

// Override or define updateCardPhotoSliderIndex to support boisson cards
var prevUpdateCardPhotoSliderIndex = window.updateCardPhotoSliderIndex;
window.updateCardPhotoSliderIndex = function (slider, photos, next) {
    slider.setAttribute('data-current-index', next);

    var nextUrl = photos[next];
    if (!nextUrl) return;

    var bgEl = slider.querySelector('.card-recette-bg-photo');
    if (bgEl) {
        bgEl.style.backgroundImage = 'url("' + nextUrl + '")';
    }

    var imgEl = slider.querySelector('.card-ingredient-bg-photo, .card-boisson-bg-photo');
    if (imgEl) {
        imgEl.src = nextUrl;
    }

    var idxEl = slider.querySelector('.card-photo-current-idx');
    if (idxEl) {
        idxEl.textContent = (next + 1);
    }

    var dots = slider.querySelectorAll('.card-photo-dot');
    dots.forEach(function (dot, i) {
        dot.classList.toggle('active', i === next);
    });
};
