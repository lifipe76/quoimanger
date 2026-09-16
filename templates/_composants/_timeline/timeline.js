// =====================================================================
// TIMELINE.JS - Animation 3D d'apparition au défilement (Scroll Flip)
// =====================================================================
(function () {
    if (!("IntersectionObserver" in window)) return;

    let revealTimeout = null;
    let revealIndex = 0;

    const observer = new IntersectionObserver(
        (entries, obs) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    const card = entry.target;
                    obs.unobserve(card);

                    // Délai progressif par lot de cartes entrant simultanément dans l'écran
                    const delay = revealIndex * 0.05;
                    card.style.animationDelay = `${delay}s`;
                    card.classList.add("card-flip-visible");
                    revealIndex++;

                    clearTimeout(revealTimeout);
                    revealTimeout = setTimeout(() => {
                        revealIndex = 0;
                    }, 100);

                    // Une fois l'animation terminée, stabiliser le style pour ne pas perturber les hovers
                    card.addEventListener(
                        "animationend",
                        () => {
                            card.style.opacity = "1";
                            card.style.transform = "";
                            card.style.animation = "none";
                            card.classList.add("card-flip-done");
                        },
                        { once: true },
                    );
                }
            });
        },
        {
            threshold: 0.05,
            rootMargin: "0px 0px -30px 0px",
        },
    );

    function observeCards(root = document) {
        const cards = root.querySelectorAll(
            ".timeline-card-wrapper .card:not(.card-flip-visible):not(.card-flip-done)",
        );
        cards.forEach((card) => {
            observer.observe(card);
        });
    }

    window.observeTimelineCards = observeCards;

    function init() {
        document.body.classList.add("timeline-scroll-active");
        observeCards();

        // Observer les ajouts dynamiques (ex: bouton Charger plus ou AJAX)
        const timelineContainers = document.querySelectorAll(
            ".timeline-container, #timelineZonesContainer, .home-timeline-section",
        );
        if (timelineContainers.length > 0 && "MutationObserver" in window) {
            const mutObserver = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    mutation.addedNodes.forEach((node) => {
                        if (node.nodeType === Node.ELEMENT_NODE) {
                            observeCards(node);
                        }
                    });
                });
            });

            timelineContainers.forEach((tc) => {
                mutObserver.observe(tc, { childList: true, subtree: true });
            });
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
