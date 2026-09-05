(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', () => {
        const app = document.querySelector('.grids-app');
        const displayMenu = document.querySelector('.grids-display-menu');
        const siteCheck = document.getElementById('grids-display-site');
        const hideLeaveCheck = document.getElementById('grids-display-hide-leave');
        const hideEmptyCheck = document.getElementById('grids-display-hide-empty');

        function classifyRow(tr) {
            if (!tr) {
                return;
            }
            const cells = tr.querySelectorAll('td.td_quarter');
            let hasAbsence = false;
            let hasOther = false;
            let hasEmptyAvailable = false;
            let hasAnyOffer = false;
            cells.forEach((td) => {
                if (td.classList.contains('td_unavailable')) {
                    return;
                }
                const type = td.getAttribute('data-offer-type') || '';
                const id = td.getAttribute('data-offer-id') || '0';
                if (id === '0' || id === '') {
                    hasEmptyAvailable = true;
                    return;
                }
                hasAnyOffer = true;
                if (type === 'absence' || type === 'meeting') {
                    hasAbsence = true;
                } else if (type !== 'pause' && type !== 'lunch') {
                    hasOther = true;
                }
            });
            tr.classList.toggle('is-full-leave', hasAbsence && !hasOther && !hasEmptyAvailable);
            tr.classList.toggle('is-empty-day', !hasAnyOffer);
        }

        function classifyAllRows() {
            document.querySelectorAll('.grids-app tr.tr_quarter').forEach(classifyRow);
        }

        if (app) {
            if (siteCheck) {
                const showSite = localStorage.getItem('showSiteColumn');
                siteCheck.checked = showSite === null ? true : showSite === 'true';
                app.classList.toggle('hide-site-column', !siteCheck.checked);
                siteCheck.addEventListener('change', () => {
                    app.classList.toggle('hide-site-column', !siteCheck.checked);
                    localStorage.setItem('showSiteColumn', siteCheck.checked ? 'true' : 'false');
                });
            }
            if (hideLeaveCheck) {
                hideLeaveCheck.checked = localStorage.getItem('gridsHideFullLeave') === 'true';
                app.classList.toggle('hide-full-leave', hideLeaveCheck.checked);
                hideLeaveCheck.addEventListener('change', () => {
                    app.classList.toggle('hide-full-leave', hideLeaveCheck.checked);
                    localStorage.setItem('gridsHideFullLeave', hideLeaveCheck.checked ? 'true' : 'false');
                });
            }
            if (hideEmptyCheck) {
                hideEmptyCheck.checked = localStorage.getItem('gridsHideEmptyDay') === 'true';
                app.classList.toggle('hide-empty-day', hideEmptyCheck.checked);
                hideEmptyCheck.addEventListener('change', () => {
                    app.classList.toggle('hide-empty-day', hideEmptyCheck.checked);
                    localStorage.setItem('gridsHideEmptyDay', hideEmptyCheck.checked ? 'true' : 'false');
                });
            }
            classifyAllRows();
            const prevPaint = window.gridsOnPaintedRow;
            window.gridsOnPaintedRow = function (tr) {
                if (typeof prevPaint === 'function') {
                    prevPaint(tr);
                }
                classifyRow(tr);
            };
        }

        if (displayMenu) {
            displayMenu.addEventListener('click', (event) => {
                event.stopPropagation();
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

        if (app && !app.classList.contains('grids-app--embed')) {
            const chrome = app.querySelector('.grids-chrome');
            if (chrome) {
                const syncChromeHeight = () => {
                    app.style.setProperty('--grids-chrome-h', chrome.offsetHeight + 'px');
                };
                if (typeof ResizeObserver !== 'undefined') {
                    new ResizeObserver(syncChromeHeight).observe(chrome);
                }
                window.addEventListener('resize', syncChromeHeight);
            }
        }

    });
}());
