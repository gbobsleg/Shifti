/**
 * Badge navbar Jobs — polling 45s, anti-flicker dropdown .show, pause si onglet caché.
 */
(function () {
    'use strict';

    var POLL_MS = 45000;
    var MAX_ITEMS = 8;
    var TYPE_LABELS = {
        optuna: 'Optuna',
        forecast: 'Prévision',
        planning: 'Planning'
    };

    function qs(root, sel) {
        return root ? root.querySelector(sel) : null;
    }

    function esc(text) {
        var d = document.createElement('div');
        d.textContent = text == null ? '' : String(text);
        return d.innerHTML;
    }

    function statusBadgeClass(status) {
        switch (String(status || '')) {
            case 'running':
                return 'badge-primary';
            case 'queued':
                return 'badge-warning';
            case 'completed':
            case 'finished':
                return 'badge-success';
            case 'failed':
            case 'error':
            case 'infeasible':
            case 'finished_with_errors':
                return 'badge-danger';
            default:
                return 'badge-secondary';
        }
    }

    function isDropdownOpen(menu) {
        return !!(menu && menu.classList.contains('show'));
    }

    function renderList(items, seeAllUrl) {
        var list = (items || []).slice(0, MAX_ITEMS);
        var html = '';
        if (!list.length) {
            html += '<span class="dropdown-item-text text-muted small">Aucun job actif / récent</span>';
        } else {
            list.forEach(function (item) {
                var type = TYPE_LABELS[item.type] || item.type || '';
                var label = item.label || '—';
                var status = item.status || '—';
                var progress = item.progress || '';
                var url = item.url || seeAllUrl || '#';
                html +=
                    '<a class="dropdown-item py-2" href="' + esc(url) + '">' +
                        '<div class="d-flex justify-content-between align-items-center">' +
                            '<span class="small font-weight-bold">' + esc(type) + '</span>' +
                            '<span class="badge ' + statusBadgeClass(status) + '">' + esc(status) + '</span>' +
                        '</div>' +
                        '<div class="small text-truncate" style="max-width: 260px;">' + esc(label) + '</div>' +
                        (progress
                            ? '<div class="small text-muted">' + esc(progress) + '</div>'
                            : '') +
                    '</a>';
            });
        }
        html += '<div class="dropdown-divider"></div>';
        html +=
            '<a class="dropdown-item text-primary" href="' + esc(seeAllUrl || '/background-jobs') + '">' +
                '<i class="bi bi-arrow-right-circle mr-1"></i> Voir tout' +
            '</a>';
        return html;
    }

    function isBackgroundJobsPage() {
        var path = window.location.pathname || '';
        // Évite le double polling avec background-jobs-page.js
        return path === '/background-jobs' || path.indexOf('/background-jobs/') === 0;
    }

    function init(root) {
        if (!root) {
            return;
        }
        if (isBackgroundJobsPage()) {
            return;
        }
        var url = root.getAttribute('data-url-status');
        var seeAllUrl = root.getAttribute('data-url-index') || '/background-jobs';
        if (!url) {
            return;
        }

        var elCount = qs(root, '[data-bj-count]');
        var elMenu = qs(root, '[data-bj-menu]');
        var timer = null;

        function updateCount(n) {
            var count = Number(n) || 0;
            if (elCount) {
                elCount.textContent = String(count);
                elCount.classList.toggle('badge-warning', count > 0);
                elCount.classList.toggle('badge-secondary', count === 0);
                elCount.classList.toggle('d-none', false);
            }
            root.classList.toggle('bj-badge-idle', count === 0);
            root.classList.toggle('bj-badge-active', count > 0);
        }

        function apply(data) {
            if (!data || !data.success) {
                return;
            }
            updateCount(data.active_count);
            // Anti-flicker : ne pas réécrire la liste si le dropdown est ouvert
            if (elMenu && !isDropdownOpen(elMenu)) {
                elMenu.innerHTML = renderList(data.items || [], seeAllUrl);
            }
        }

        function fetchStatus() {
            if (document.hidden) {
                return Promise.resolve();
            }
            return fetch(url, {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
                .then(function (res) {
                    if (!res.ok) {
                        throw new Error('HTTP ' + res.status);
                    }
                    return res.json();
                })
                .then(apply)
                .catch(function (err) {
                    console.error('[background-jobs-badge]', err);
                });
        }

        function stopPoll() {
            if (timer) {
                clearInterval(timer);
                timer = null;
            }
        }

        function startPoll() {
            stopPoll();
            timer = setInterval(fetchStatus, POLL_MS);
        }

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                stopPoll();
                return;
            }
            fetchStatus().then(startPoll);
        });

        // Premier fetch immédiat (compteur + liste), puis intervalle 45s
        if (!document.hidden) {
            fetchStatus().then(startPoll);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        init(document.getElementById('background-jobs-badge'));
    });
})();
