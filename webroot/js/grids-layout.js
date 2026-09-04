(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', () => {
        const toggleSiteBtn = document.getElementById('toggle-site-column');
        const toggleSiteText = document.getElementById('toggle-site-text');
        const app = document.querySelector('.grids-app');
        if (toggleSiteBtn && app) {
            const savedPreference = localStorage.getItem('showSiteColumn');
            let isVisible = savedPreference === null ? true : savedPreference === 'true';

            function updateSiteColumnVisibility(visible) {
                app.classList.toggle('hide-site-column', !visible);
                if (toggleSiteText) {
                    toggleSiteText.textContent = visible ? 'Masquer site' : 'Afficher site';
                }
                localStorage.setItem('showSiteColumn', visible.toString());
            }

            updateSiteColumnVisibility(isVisible);
            toggleSiteBtn.addEventListener('click', () => {
                updateSiteColumnVisibility(app.classList.contains('hide-site-column'));
            });
        }

        const sortSelect = document.getElementById('sort-select');
        if (sortSelect) {
            sortSelect.addEventListener('change', function () {
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('sort_by', this.value);
                window.location.href = currentUrl.toString();
            });
        }

        if (app && !app.classList.contains('grids-app--embed')) {
            const chrome = app.querySelector('.grids-chrome');
            if (chrome) {
                const syncChromeHeight = () => {
                    app.style.setProperty('--grids-chrome-h', chrome.offsetHeight + 'px');
                };
                syncChromeHeight();
                if (typeof ResizeObserver !== 'undefined') {
                    new ResizeObserver(syncChromeHeight).observe(chrome);
                }
                window.addEventListener('resize', syncChromeHeight);
            }
        }

        const rail = document.querySelector('.grids-rail');
        const railInner = document.querySelector('.grids-rail-inner');
        if (rail && railInner) {
            rail.addEventListener('wheel', (event) => {
                const canScroll = railInner.scrollHeight > railInner.clientHeight + 1;
                if (!canScroll) {
                    event.preventDefault();
                    return;
                }
                const goingDown = event.deltaY > 0;
                const atTop = railInner.scrollTop <= 0;
                const atBottom = railInner.scrollTop + railInner.clientHeight >= railInner.scrollHeight - 1;
                if ((goingDown && atBottom) || (!goingDown && atTop)) {
                    event.preventDefault();
                }
            }, { passive: false });
        }

    });
}());
