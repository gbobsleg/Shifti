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
                return 'bg-primary';
            case 'queued':
                return 'bg-warning';
            case 'completed':
            case 'finished':
                return 'bg-success';
            case 'failed':
            case 'error':
            case 'infeasible':
            case 'finished_with_errors':
                return 'bg-danger';
            case 'cancelled':
                return 'bg-secondary';
            default:
                return 'bg-secondary';
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
            '<a class="dropdown-item text-primary" href="' + esc(seeAllUrl || '#') + '">' +
                '<i class="bi bi-arrow-right-circle me-1"></i> Voir tout' +
            '</a>';
        return html;
    }

    function mergeBadgeItems(data) {
        var active = data && data.items ? data.items.slice() : [];
        var recent = data && data.recent ? data.recent : [];
        var seen = {};
        var out = [];
        function push(item) {
            if (!item) {
                return;
            }
            var key = String(item.type || '') + ':' + String(item.id || '');
            if (seen[key]) {
                return;
            }
            seen[key] = true;
            out.push(item);
        }
        active.forEach(push);
        recent.forEach(push);
        return out.slice(0, MAX_ITEMS);
    }

    function renderError(seeAllUrl, message) {
        return (
            '<span class="dropdown-item-text text-danger small">' + esc(message || 'Chargement impossible') + '</span>' +
            '<div class="dropdown-divider"></div>' +
            '<a class="dropdown-item text-primary" href="' + esc(seeAllUrl || '#') + '">' +
                '<i class="bi bi-arrow-right-circle me-1"></i> Voir tout' +
            '</a>'
        );
    }

    function indexPathFromUrl(indexUrl) {
        var path = String(indexUrl || '').split('?')[0];
        if (!path) {
            return '';
        }
        if (/^https?:\/\//i.test(path)) {
            try {
                path = new URL(path).pathname;
            } catch (e) {
                return '';
            }
        }
        return path.replace(/\/+$/, '') || '/';
    }

    function isBackgroundJobsPage(indexUrl) {
        var path = window.location.pathname || '';
        var indexPath = indexPathFromUrl(indexUrl);
        if (!indexPath || indexPath === '/') {
            return false;
        }
        // Sur la page Jobs, la page poll déjà : on hydrate le badge une fois, sans intervalle.
        return path === indexPath || path.indexOf(indexPath + '/') === 0;
    }

    function init(root) {
        if (!root) {
            return;
        }
        var url = root.getAttribute('data-url-status');
        var seeAllUrl = root.getAttribute('data-url-index') || '';
        if (!url) {
            return;
        }

        var elCount = qs(root, '[data-bj-count]');
        var elMenu = qs(root, '[data-bj-menu]');
        var timer = null;
        var lastPayload = null;
        var menuReady = false;
        var skipInterval = isBackgroundJobsPage(seeAllUrl);

        function updateCount(n) {
            var count = Number(n) || 0;
            if (elCount) {
                elCount.textContent = String(count);
                elCount.classList.toggle('bg-warning', count > 0);
                elCount.classList.toggle('bg-secondary', count === 0);
                elCount.classList.toggle('d-none', false);
            }
            root.classList.toggle('bj-badge-idle', count === 0);
            root.classList.toggle('bj-badge-active', count > 0);
        }

        function paintMenuFromPayload(data) {
            if (!elMenu) {
                return;
            }
            elMenu.innerHTML = renderList(mergeBadgeItems(data || {}), seeAllUrl);
            menuReady = true;
        }

        function apply(data) {
            if (!data || !data.success) {
                return;
            }
            lastPayload = data;
            updateCount(data.active_count);
            // Anti-flicker : ne pas réécrire si ouvert — SAUF tant que jamais hydraté
            if (elMenu && (!isDropdownOpen(elMenu) || !menuReady)) {
                paintMenuFromPayload(data);
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
                    if (elMenu && !menuReady) {
                        elMenu.innerHTML = renderError(seeAllUrl, 'Chargement impossible');
                        menuReady = true;
                    }
                });
        }

        function stopPoll() {
            if (timer) {
                clearInterval(timer);
                timer = null;
            }
        }

        function startPoll() {
            if (skipInterval) {
                return;
            }
            stopPoll();
            timer = setInterval(fetchStatus, POLL_MS);
        }

        // À la fermeture : peindre le dernier snapshot (évite "Chargement…" figé)
        if (typeof window.jQuery !== 'undefined') {
            window.jQuery(root).on('hidden.bs.dropdown', function () {
                if (lastPayload) {
                    paintMenuFromPayload(lastPayload);
                    updateCount(lastPayload.active_count);
                } else if (!menuReady) {
                    fetchStatus();
                }
            });
            window.jQuery(root).on('show.bs.dropdown', function () {
                if (!menuReady) {
                    fetchStatus();
                }
            });
        }

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                stopPoll();
                return;
            }
            fetchStatus().then(startPoll);
        });

        // Premier fetch immédiat (compteur + liste), puis intervalle 45s hors page Jobs
        if (!document.hidden) {
            fetchStatus().then(startPoll);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        init(document.getElementById('background-jobs-badge'));
    });
})();
