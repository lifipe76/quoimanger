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
});
