// Scripts spécifiques à la page Home
document.addEventListener('DOMContentLoaded', function () {
    // 1. Ouverture de modale via paramètre URL
    var params = new URLSearchParams(window.location.search);
    if (params.get('open_modal') === '1') {
        if (typeof openRepasModal === 'function') {
            openRepasModal();
            var url = new URL(window.location);
            url.searchParams.delete('open_modal');
            window.history.replaceState({}, '', url);
        }
    }

    // 2. Gestion de la bascule de vue Timeline / Calendrier
    var btnTimeline = document.getElementById('btn-view-timeline');
    var btnCalendar = document.getElementById('btn-view-calendar');
    var panelTimeline = document.getElementById('home-view-timeline');
    var panelCalendar = document.getElementById('home-view-calendar');

    function setView(viewName, updateUrl) {
        if (viewName === 'calendar') {
            if (btnCalendar) {
                btnCalendar.classList.add('active');
                btnCalendar.setAttribute('aria-selected', 'true');
            }
            if (btnTimeline) {
                btnTimeline.classList.remove('active');
                btnTimeline.setAttribute('aria-selected', 'false');
            }
            if (panelCalendar) panelCalendar.style.display = 'block';
            if (panelTimeline) panelTimeline.style.display = 'none';

            try {
                localStorage.setItem('quoimanger_home_view', 'calendar');
            } catch (e) {}

            if (window.calendarApp && typeof window.calendarApp.render === 'function') {
                window.calendarApp.render();
            }
        } else {
            if (btnTimeline) {
                btnTimeline.classList.add('active');
                btnTimeline.setAttribute('aria-selected', 'true');
            }
            if (btnCalendar) {
                btnCalendar.classList.remove('active');
                btnCalendar.setAttribute('aria-selected', 'false');
            }
            if (panelTimeline) panelTimeline.style.display = 'block';
            if (panelCalendar) panelCalendar.style.display = 'none';

            try {
                localStorage.setItem('quoimanger_home_view', 'timeline');
            } catch (e) {}
        }

        if (updateUrl) {
            var currentUrl = new URL(window.location);
            currentUrl.searchParams.set('view', viewName);
            window.history.replaceState({}, '', currentUrl);
        }
    }

    if (btnTimeline) {
        btnTimeline.addEventListener('click', function () {
            setView('timeline', true);
        });
    }

    if (btnCalendar) {
        btnCalendar.addEventListener('click', function () {
            setView('calendar', true);
        });
    }

    // Déterminer la vue initiale : paramètre URL > localStorage > défaut ('timeline')
    var viewParam = params.get('view');
    var savedView = null;
    try {
        savedView = localStorage.getItem('quoimanger_home_view');
    } catch (e) {}

    var initialView = viewParam || savedView || 'timeline';
    if (initialView === 'calendar') {
        setView('calendar', false);
    } else {
        setView('timeline', false);
    }
});
