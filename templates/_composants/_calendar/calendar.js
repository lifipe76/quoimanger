/* ==========================================================================
   Composant Calendrier des Repas - JavaScript
   ========================================================================== */

(function () {
    'use strict';

    var MONTH_NAMES = [
        'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
        'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'
    ];

    var DAY_NAMES = [
        'Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'
    ];

    var MOMENT_ORDER = {
        'matin': 1,
        'midi': 2,
        'gouter': 3,
        'soir': 4,
        'autre': 5
    };

    var state = {
        currentYear: new Date().getFullYear(),
        currentMonth: new Date().getMonth(), // 0-11
        selectedDate: formatDateIso(new Date()),
        meals: [],
        mealsByDate: {},
        mealsById: {}
    };

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function formatDateIso(dateObj) {
        var y = dateObj.getFullYear();
        var m = String(dateObj.getMonth() + 1).padStart(2, '0');
        var d = String(dateObj.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + d;
    }

    function parseDateIso(isoStr) {
        var parts = (isoStr || '').split('-');
        if (parts.length === 3) {
            return new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
        }
        return new Date();
    }

    function formatFrenchDate(isoStr) {
        var d = parseDateIso(isoStr);
        var dayName = DAY_NAMES[d.getDay()];
        var dayNum = d.getDate();
        var monthName = MONTH_NAMES[d.getMonth()].toLowerCase();
        var year = d.getFullYear();
        return dayName + ' ' + dayNum + ' ' + monthName + ' ' + year;
    }

    function isSameDay(d1, d2) {
        return d1.getFullYear() === d2.getFullYear() &&
               d1.getMonth() === d2.getMonth() &&
               d1.getDate() === d2.getDate();
    }

    function initMealsData() {
        var scriptTag = document.getElementById('calendar-meals-data');
        if (!scriptTag) return;

        try {
            var raw = JSON.parse(scriptTag.textContent || '[]');
            state.meals = Array.isArray(raw) ? raw : [];
            state.mealsByDate = {};
            state.mealsById = {};

            state.meals.forEach(function (meal) {
                state.mealsById[meal.id] = meal;
                if (!meal.date) return;
                var dateKey = meal.date.split('T')[0];
                if (!state.mealsByDate[dateKey]) {
                    state.mealsByDate[dateKey] = [];
                }
                state.mealsByDate[dateKey].push(meal);
            });

            // Tri des repas de chaque date selon le moment
            Object.keys(state.mealsByDate).forEach(function (dateKey) {
                state.mealsByDate[dateKey].sort(function (a, b) {
                    var orderA = MOMENT_ORDER[a.moment] || 99;
                    var orderB = MOMENT_ORDER[b.moment] || 99;
                    return orderA - orderB;
                });
            });
        } catch (e) {
            console.error('Erreur lecture données calendrier:', e);
            state.meals = [];
            state.mealsByDate = {};
            state.mealsById = {};
        }
    }

    function renderCalendar() {
        var grid = document.getElementById('calendar-days-grid');
        var monthLabel = document.getElementById('calendar-current-month-label');
        var counterLabel = document.getElementById('calendar-meals-count');
        if (!grid || !monthLabel) return;

        monthLabel.textContent = MONTH_NAMES[state.currentMonth] + ' ' + state.currentYear;

        var firstDayIndex = new Date(state.currentYear, state.currentMonth, 1).getDay();
        var firstDayOffset = firstDayIndex === 0 ? 6 : firstDayIndex - 1; // 0 = Lundi, 6 = Dimanche

        var daysInCurrentMonth = new Date(state.currentYear, state.currentMonth + 1, 0).getDate();
        var daysInPrevMonth = new Date(state.currentYear, state.currentMonth, 0).getDate();

        var today = new Date();
        var todayIso = formatDateIso(today);

        var monthMealsCount = 0;
        var html = '';

        // Jours du mois précédent (faded)
        for (var p = firstDayOffset - 1; p >= 0; p--) {
            var prevDayNum = daysInPrevMonth - p;
            var prevDateObj = new Date(state.currentYear, state.currentMonth - 1, prevDayNum);
            var prevDateIso = formatDateIso(prevDateObj);
            var prevMeals = state.mealsByDate[prevDateIso] || [];
            html += generateDayCellHtml(prevDateIso, prevDayNum, prevMeals, true, todayIso);
        }

        // Jours du mois courant
        for (var d = 1; d <= daysInCurrentMonth; d++) {
            var currDateObj = new Date(state.currentYear, state.currentMonth, d);
            var currDateIso = formatDateIso(currDateObj);
            var currMeals = state.mealsByDate[currDateIso] || [];
            monthMealsCount += currMeals.length;
            html += generateDayCellHtml(currDateIso, d, currMeals, false, todayIso);
        }

        // Jours du mois suivant pour compléter la grille (35 ou 42 cases)
        var totalCells = firstDayOffset + daysInCurrentMonth;
        var nextDaysNeeded = totalCells <= 35 ? (35 - totalCells) : (42 - totalCells);
        if (nextDaysNeeded > 0) {
            for (var n = 1; n <= nextDaysNeeded; n++) {
                var nextDateObj = new Date(state.currentYear, state.currentMonth + 1, n);
                var nextDateIso = formatDateIso(nextDateObj);
                var nextMeals = state.mealsByDate[nextDateIso] || [];
                html += generateDayCellHtml(nextDateIso, n, nextMeals, true, todayIso);
            }
        }

        grid.innerHTML = html;
        if (counterLabel) {
            counterLabel.textContent = monthMealsCount;
        }

        attachGridListeners();
        renderSelectedDayDetails();
    }

    function generateDayCellHtml(dateIso, dayNum, meals, isOtherMonth, todayIso) {
        var isToday = (dateIso === todayIso);
        var isSelected = (dateIso === state.selectedDate);

        var classes = ['calendar-day-cell'];
        if (isOtherMonth) classes.push('is-other-month');
        if (isToday) classes.push('is-today');
        if (isSelected) classes.push('is-selected');
        if (meals.length > 0) classes.push('has-meals');

        var maxDesktopMeals = 2;
        var desktopMeals = meals.slice(0, maxDesktopMeals);
        var extraCount = meals.length - maxDesktopMeals;

        var desktopMealsHtml = '';
        if (meals.length > 0) {
            desktopMealsHtml += '<div class="cell-meals-desktop">';
            desktopMeals.forEach(function (meal) {
                desktopMealsHtml += '<div class="cell-meal-item moment-' + escapeHtml(meal.moment) + '" data-meal-id="' + meal.id + '" title="' + escapeHtml(meal.title) + ' (' + escapeHtml(meal.momentLabel) + ')">';
                if (meal.photoUrl) {
                    desktopMealsHtml += '<div class="cell-meal-photo-wrap"><img src="' + escapeHtml(meal.photoUrl) + '" alt="' + escapeHtml(meal.title) + '" class="cell-meal-img" loading="lazy"></div>';
                } else {
                    desktopMealsHtml += '<div class="cell-meal-photo-placeholder"><i class="fa-solid fa-utensils"></i></div>';
                }
                desktopMealsHtml += '<div class="cell-meal-text">';
                desktopMealsHtml += '<span class="cell-meal-moment-badge moment-' + escapeHtml(meal.moment) + '">' + escapeHtml(meal.momentLabel) + '</span>';
                desktopMealsHtml += '<span class="cell-meal-name">' + escapeHtml(meal.title) + '</span>';
                desktopMealsHtml += '</div>';
                desktopMealsHtml += '<button type="button" class="cell-meal-edit-icon" title="Modifier" data-meal-id="' + meal.id + '"><i class="fa-solid fa-pencil"></i></button>';
                desktopMealsHtml += '</div>';
            });
            if (extraCount > 0) {
                desktopMealsHtml += '<div class="cell-more-meals">+' + extraCount + ' autre' + (extraCount > 1 ? 's' : '') + '</div>';
            }
            desktopMealsHtml += '</div>';
        }

        // Indicateurs mobiles
        var mobileIndicatorsHtml = '';
        if (meals.length > 0) {
            mobileIndicatorsHtml += '<div class="cell-meals-mobile">';
            var maxMobileDots = 2;
            meals.slice(0, maxMobileDots).forEach(function (meal) {
                if (meal.photoUrl) {
                    mobileIndicatorsHtml += '<span class="mobile-photo-dot" title="' + escapeHtml(meal.title) + '"><img src="' + escapeHtml(meal.photoUrl) + '" alt="" class="mobile-dot-img" loading="lazy"></span>';
                } else {
                    mobileIndicatorsHtml += '<span class="mobile-moment-dot moment-' + escapeHtml(meal.moment) + '" title="' + escapeHtml(meal.title) + '"></span>';
                }
            });
            if (meals.length > maxMobileDots) {
                mobileIndicatorsHtml += '<span class="mobile-extra-count">+' + (meals.length - maxMobileDots) + '</span>';
            }
            mobileIndicatorsHtml += '</div>';
        }

        return '<div class="' + classes.join(' ') + '" data-date="' + dateIso + '" role="gridcell" tabindex="0">' +
            '<div class="cell-header">' +
                '<span class="cell-day-num">' + dayNum + '</span>' +
                '<button type="button" class="cell-add-btn" title="Ajouter un repas le ' + dateIso + '" data-date="' + dateIso + '">' +
                    '<i class="fa-solid fa-plus"></i>' +
                '</button>' +
            '</div>' +
            desktopMealsHtml +
            mobileIndicatorsHtml +
        '</div>';
    }

    function attachGridListeners() {
        var grid = document.getElementById('calendar-days-grid');
        if (!grid) return;

        grid.querySelectorAll('.calendar-day-cell').forEach(function (cell) {
            cell.addEventListener('click', function (e) {
                var editBtn = e.target.closest('.cell-meal-edit-icon');
                var mealItem = e.target.closest('.cell-meal-item');
                var addBtn = e.target.closest('.cell-add-btn');

                if (editBtn || mealItem) {
                    var mealId = (editBtn || mealItem).getAttribute('data-meal-id');
                    if (mealId) {
                        e.stopPropagation();
                        openEditMealFromCalendar(parseInt(mealId, 10));
                        return;
                    }
                }

                if (addBtn) {
                    var addDate = addBtn.getAttribute('data-date');
                    if (addDate) {
                        e.stopPropagation();
                        openRepasModalForDate(addDate);
                        return;
                    }
                }

                var date = cell.getAttribute('data-date');
                if (date) {
                    selectDate(date);
                }
            });
        });
    }

    function selectDate(dateIso) {
        state.selectedDate = dateIso;

        var targetDate = parseDateIso(dateIso);
        if (targetDate.getMonth() !== state.currentMonth || targetDate.getFullYear() !== state.currentYear) {
            state.currentMonth = targetDate.getMonth();
            state.currentYear = targetDate.getFullYear();
            renderCalendar();
            return;
        }

        var grid = document.getElementById('calendar-days-grid');
        if (grid) {
            grid.querySelectorAll('.calendar-day-cell').forEach(function (c) {
                c.classList.toggle('is-selected', c.getAttribute('data-date') === dateIso);
            });
        }

        renderSelectedDayDetails();
    }

    function renderSelectedDayDetails() {
        var dateLabel = document.getElementById('day-details-date-label');
        var relativeTag = document.getElementById('day-details-relative-tag');
        var mealsList = document.getElementById('day-details-meals-list');
        var addBtn = document.getElementById('day-details-add-btn');
        if (!dateLabel || !mealsList) return;

        var selectedDateObj = parseDateIso(state.selectedDate);
        var today = new Date();
        var yest = new Date();
        yest.setDate(yest.getDate() - 1);

        dateLabel.textContent = formatFrenchDate(state.selectedDate);

        if (relativeTag) {
            if (isSameDay(selectedDateObj, today)) {
                relativeTag.textContent = "Aujourd'hui";
                relativeTag.style.display = 'inline-block';
            } else if (isSameDay(selectedDateObj, yest)) {
                relativeTag.textContent = 'Hier';
                relativeTag.style.display = 'inline-block';
            } else {
                relativeTag.style.display = 'none';
            }
        }

        if (addBtn) {
            addBtn.onclick = function () {
                openRepasModalForDate(state.selectedDate);
            };
        }

        var meals = state.mealsByDate[state.selectedDate] || [];

        if (meals.length === 0) {
            mealsList.innerHTML = '<div class="day-detail-empty">' +
                '<div class="day-detail-empty-icon"><i class="fa-solid fa-bowl-food"></i></div>' +
                '<p class="day-detail-empty-text">Aucun repas enregistré pour cette journée.</p>' +
                '<button type="button" class="day-detail-empty-btn" onclick="openRepasModalForDate(\'' + state.selectedDate + '\')">' +
                    '<i class="fa-solid fa-plus"></i>' +
                    '<span>Enregistrer un repas ce jour</span>' +
                '</button>' +
            '</div>';
            return;
        }

        var html = '';
        meals.forEach(function (meal) {
            html += '<div class="day-detail-meal-card moment-' + escapeHtml(meal.moment) + '">';

            // Colonne photo
            if (meal.photoUrl) {
                html += '<div class="meal-card-photo-col">' +
                    '<div class="meal-card-photo" style="background-image: url(\'' + escapeHtml(meal.photoUrl) + '\');" role="img" aria-label="' + escapeHtml(meal.title) + '"></div>' +
                '</div>';
            } else {
                html += '<div class="meal-card-photo-col meal-card-photo-empty">' +
                    '<i class="fa-solid fa-utensils"></i>' +
                '</div>';
            }

            // Colonne contenu
            html += '<div class="meal-card-content-col">';
            html += '<div class="meal-card-top-row">';
            html += '<div class="meal-card-badges">';
            html += '<span class="meal-card-moment-badge moment-' + escapeHtml(meal.moment) + '">' + escapeHtml(meal.momentLabel) + '</span>';
            if (meal.intervalLabel) {
                html += '<span class="meal-card-interval-badge">' + escapeHtml(meal.intervalLabel) + '</span>';
            }
            if (meal.participantsLabel) {
                html += '<span class="meal-card-participants-badge" title="Participants : ' + escapeHtml(meal.participantsLabel) + '">' + escapeHtml(meal.participantsLabel) + '</span>';
            }
            html += '</div>';

            html += '<button type="button" class="meal-card-edit-btn" onclick="openEditMealFromCalendar(' + meal.id + ')" title="Modifier ce repas">';
            html += '<i class="fa-solid fa-pencil" aria-hidden="true"></i>';
            html += '<span>Modifier</span>';
            html += '</button>';
            html += '</div>';

            html += '<h4 class="meal-card-title">';
            html += '<a href="' + escapeHtml(meal.recetteEditUrl) + '" class="meal-card-title-link">' + escapeHtml(meal.title) + '</a>';
            if (meal.complement) {
                html += '<span class="meal-card-complement">(' + escapeHtml(meal.complement) + ')</span>';
            }
            html += '</h4>';

            html += '<div class="meal-card-footer">';
            if (meal.averageNote) {
                html += '<div class="meal-card-rating">';
                html += '<span class="star-icon">★</span>';
                html += '<span class="rating-val">' + meal.averageNote + '</span>';
                html += '<span class="rating-cnt">(' + meal.notesCount + ')</span>';
                html += '</div>';
            } else {
                html += '<span class="meal-card-no-rating">Non noté</span>';
            }
            html += '</div>';

            html += '</div>'; // fin meal-card-content-col
            html += '</div>'; // fin day-detail-meal-card
        });

        mealsList.innerHTML = html;
    }

    function prevMonth() {
        if (state.currentMonth === 0) {
            state.currentMonth = 11;
            state.currentYear--;
        } else {
            state.currentMonth--;
        }
        adjustSelectedDateToMonth();
        renderCalendar();
    }

    function nextMonth() {
        if (state.currentMonth === 11) {
            state.currentMonth = 0;
            state.currentYear++;
        } else {
            state.currentMonth++;
        }
        adjustSelectedDateToMonth();
        renderCalendar();
    }

    function goToToday() {
        var today = new Date();
        state.currentYear = today.getFullYear();
        state.currentMonth = today.getMonth();
        state.selectedDate = formatDateIso(today);
        renderCalendar();
    }

    function adjustSelectedDateToMonth() {
        var selDate = parseDateIso(state.selectedDate);
        if (selDate.getMonth() !== state.currentMonth || selDate.getFullYear() !== state.currentYear) {
            // Chercher d'abord le dernier jour avec un repas dans ce mois
            var foundDate = null;
            Object.keys(state.mealsByDate).forEach(function (k) {
                var d = parseDateIso(k);
                if (d.getMonth() === state.currentMonth && d.getFullYear() === state.currentYear) {
                    if (!foundDate || k > foundDate) {
                        foundDate = k;
                    }
                }
            });
            if (foundDate) {
                state.selectedDate = foundDate;
            } else {
                state.selectedDate = formatDateIso(new Date(state.currentYear, state.currentMonth, 1));
            }
        }
    }

    // Fonctions globales exposées
    function openRepasModalForDate(dateStr, moment) {
        if (typeof openRepasModal === 'function') {
            openRepasModal();
        }
        if (typeof selectRepasDate === 'function') {
            selectRepasDate(dateStr, null);
        }
        if (moment && typeof selectRepasMoment === 'function') {
            selectRepasMoment(moment);
        }
    }
    window.openRepasModalForDate = openRepasModalForDate;

    function openEditMealFromCalendar(mealId) {
        var meal = state.mealsById[mealId];
        if (!meal) return;

        if (typeof openEditMealModal === 'function') {
            openEditMealModal(
                meal.updateDateUrl,
                meal.date,
                meal.moment,
                meal.title,
                meal.updateToken,
                meal.deleteUrl,
                meal.deleteToken,
                meal.complement,
                meal.participantIds || [],
                meal.notesMap || {},
                meal.photoUrl || ''
            );
        }
    }
    window.openEditMealFromCalendar = openEditMealFromCalendar;

    function initSwipeGestures() {
        var gridCard = document.querySelector('.calendar-grid-card');
        if (!gridCard) return;

        var touchStartX = 0;
        var touchStartY = 0;

        gridCard.addEventListener('touchstart', function (e) {
            if (e.touches && e.touches[0]) {
                touchStartX = e.touches[0].clientX;
                touchStartY = e.touches[0].clientY;
            }
        }, { passive: true });

        gridCard.addEventListener('touchend', function (e) {
            if (e.changedTouches && e.changedTouches[0]) {
                var deltaX = e.changedTouches[0].clientX - touchStartX;
                var deltaY = e.changedTouches[0].clientY - touchStartY;
                // Si mouvement horizontal prédominant et supérieur à 50px
                if (Math.abs(deltaX) > 50 && Math.abs(deltaX) > Math.abs(deltaY) * 1.5) {
                    if (deltaX < 0) {
                        nextMonth();
                    } else {
                        prevMonth();
                    }
                }
            }
        }, { passive: true });
    }

    function initCalendar() {
        initMealsData();

        var prevBtn = document.getElementById('calendar-prev-btn');
        var nextBtn = document.getElementById('calendar-next-btn');
        var todayBtn = document.getElementById('calendar-today-btn');

        if (prevBtn) prevBtn.addEventListener('click', prevMonth);
        if (nextBtn) nextBtn.addEventListener('click', nextMonth);
        if (todayBtn) todayBtn.addEventListener('click', goToToday);

        initSwipeGestures();
        renderCalendar();
    }

    window.calendarApp = {
        init: initCalendar,
        render: renderCalendar,
        goToToday: goToToday,
        selectDate: selectDate
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCalendar);
    } else {
        initCalendar();
    }
})();
