function rateRealisation(realisationId, star, token) {
    var wrapper = document.querySelector('.quick-stars-wrapper[data-realisation-id="' + realisationId + '"]');
    if (!wrapper) return;

    var activeBtns = wrapper.querySelectorAll('.quick-star-btn.active');
    var currentVal = activeBtns.length;
    var newVal = (currentVal === star) ? 0 : star;

    // Mise à jour visuelle optimiste immédiate
    wrapper.querySelectorAll('.quick-star-btn').forEach(function (btn) {
        var s = parseInt(btn.dataset.star, 10);
        btn.classList.toggle('active', s <= newVal);
    });

    var formData = new FormData();
    formData.append('_token', token);
    formData.append('note', newVal);

    fetch('/realisation/' + realisationId + '/rate', {
        method: 'POST',
        body: formData,
        headers: {
            'Accept': 'application/json'
        }
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
        if (data && data.success) {
            var card = wrapper.closest('.card-recette');
            if (card) {
                var countEl = card.querySelector('.rating-count');
                if (countEl) {
                    countEl.textContent = '(' + (data.recipeCount || 0) + ')';
                }

                var scoreEl = card.querySelector('.rating-avg-value');
                if (scoreEl) {
                    if (data.recipeAverage) {
                        scoreEl.textContent = data.recipeAverage;
                        scoreEl.style.display = '';
                    } else {
                        scoreEl.textContent = '';
                        scoreEl.style.display = 'none';
                    }
                }

                var starsContainer = card.querySelector('.stars-display');
                if (starsContainer) {
                    if (data.recipeAverage) {
                        starsContainer.innerHTML = '<span class="star star-filled">★</span>';
                    } else {
                        starsContainer.innerHTML = '<span class="star star-empty">☆</span>';
                    }
                }

                var group = card.querySelector('.card-recette-stars-group');
                if (group) {
                    var count = data.recipeCount || 0;
                    var titleText = data.recipeAverage
                        ? 'Note moyenne : ' + data.recipeAverage + ' / 5 (' + count + ' notation' + (count > 1 ? 's' : '') + ')'
                        : 'Aucune note pour l\'instant (0 notation)';
                    group.setAttribute('title', titleText);
                }
            }
        }
    })
    .catch(function (err) {
        console.error('Erreur lors de l\'enregistrement de la note :', err);
    });
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.quick-stars-wrapper').forEach(function (wrapper) {
        var buttons = wrapper.querySelectorAll('.quick-star-btn');
        buttons.forEach(function (btn) {
            btn.addEventListener('mouseenter', function () {
                var hoverStar = parseInt(btn.dataset.star, 10);
                buttons.forEach(function (b) {
                    var s = parseInt(b.dataset.star, 10);
                    b.classList.toggle('hover-active', s <= hoverStar);
                });
            });
        });
        wrapper.addEventListener('mouseleave', function () {
            buttons.forEach(function (b) {
                b.classList.remove('hover-active');
            });
        });
    });

    if (typeof window.initCardPhotoTouchSwipe === 'function') {
        window.initCardPhotoTouchSwipe();
    }
});

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

