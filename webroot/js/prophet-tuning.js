/**
 * Polling + actions AJAX pour le tuning Optuna (fiche Offre).
 */
(function () {
    'use strict';

    var POLL_MS = 3000;

    function qs(root, sel) {
        return root.querySelector(sel);
    }

    function setText(el, text) {
        if (el) {
            el.textContent = text == null ? '' : String(text);
        }
    }

    function show(el, visible) {
        if (!el) {
            return;
        }
        el.style.display = visible ? '' : 'none';
    }

    function pct(done, total) {
        if (!total || total <= 0) {
            return 0;
        }
        return Math.min(100, Math.round((done / total) * 100));
    }

    function formatScores(scores) {
        if (!scores) {
            return '—';
        }
        var mae = scores.mae_volume != null ? Number(scores.mae_volume).toFixed(2) : '—';
        var mape = scores.mape_volume != null ? Number(scores.mape_volume).toFixed(2) : '—';
        return 'MAE ' + mae + ' · MAPE ' + mape + '%';
    }

    function init(root) {
        var urls = {
            status: root.getAttribute('data-url-status'),
            start: root.getAttribute('data-url-start'),
            cancel: root.getAttribute('data-url-cancel'),
            apply: root.getAttribute('data-url-apply'),
            reject: root.getAttribute('data-url-reject'),
            rollback: root.getAttribute('data-url-rollback')
        };
        var csrf = root.getAttribute('data-csrf-token') || '';

        var elStatus = qs(root, '[data-pt-status]');
        var elProgressWrap = qs(root, '[data-pt-progress-wrap]');
        var elProgressBar = qs(root, '[data-pt-progress-bar]');
        var elProgressLabel = qs(root, '[data-pt-progress-label]');
        var elError = qs(root, '[data-pt-error]');
        var elBaseline = qs(root, '[data-pt-baseline]');
        var elProposed = qs(root, '[data-pt-proposed]');
        var elImprovement = qs(root, '[data-pt-improvement]');
        var elDraftActions = qs(root, '[data-pt-draft-actions]');
        var elRollback = qs(root, '[data-pt-rollback-wrap]');
        var elMessage = qs(root, '[data-pt-message]');
        var elSeasonAdapt = qs(root, '[data-pt-seasonality-adapt]');
        var elSeasonAdaptText = qs(root, '[data-pt-seasonality-adapt-text]');

        var btnStart = qs(root, '[data-pt-start]');
        var btnCancel = qs(root, '[data-pt-cancel]');
        var btnApply = qs(root, '[data-pt-apply]');
        var btnReject = qs(root, '[data-pt-reject]');
        var btnRollback = qs(root, '[data-pt-rollback]');

        var pollTimer = null;
        var busy = false;

        function parseJsonResponse(res) {
            return res.text().then(function (text) {
                var body = null;
                try {
                    body = text ? JSON.parse(text) : null;
                } catch (e) {
                    var snippet = (text || '').replace(/\s+/g, ' ').slice(0, 180);
                    throw new Error(
                        'Réponse non-JSON (HTTP ' + res.status + ')' +
                        (snippet ? ' : ' + snippet : '')
                    );
                }
                return { ok: res.ok, status: res.status, body: body };
            });
        }

        function post(url) {
            return fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrf,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            }).then(parseJsonResponse);
        }

        function fetchStatus() {
            return fetch(urls.status, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            }).then(function (res) {
                return parseJsonResponse(res).then(function (r) {
                    if (!r.body) {
                        throw new Error('Status vide (HTTP ' + r.status + ')');
                    }
                    return r.body;
                });
            });
        }

        function stopPoll() {
            if (pollTimer) {
                clearInterval(pollTimer);
                pollTimer = null;
            }
        }

        function startPoll() {
            if (pollTimer) {
                return;
            }
            pollTimer = setInterval(function () {
                refresh();
            }, POLL_MS);
        }

        function render(data) {
            if (!data || !data.success) {
                return;
            }
            var job = data.job;
            var offer = data.offer || {};

            var status = job ? job.status : 'none';
            setText(elStatus, status === 'none' ? 'Aucun job' : status);

            var running = status === 'queued' || status === 'running';
            show(elProgressWrap, running || status === 'completed' || status === 'failed' || status === 'cancelled');

            if (job) {
                var done = job.progress_trials_done || 0;
                var total = job.progress_trials_total || 0;
                var p = pct(done, total);
                if (elProgressBar) {
                    elProgressBar.style.width = p + '%';
                    elProgressBar.setAttribute('aria-valuenow', String(p));
                }
                setText(
                    elProgressLabel,
                    done + ' / ' + total + ' essais' +
                    (job.best_mae_so_far != null ? ' · meilleur MAE ' + Number(job.best_mae_so_far).toFixed(2) : '')
                );
            }

            if (job && job.error_message) {
                show(elError, true);
                setText(elError, job.error_message);
            } else {
                show(elError, false);
                setText(elError, '');
            }

            var draftScores = offer.draft_scores || null;
            var baseline = (draftScores && draftScores.baseline)
                || (job && job.baseline_scores)
                || null;
            var proposed = (draftScores && draftScores.proposed)
                || (job && job.best_scores)
                || null;

            setText(elBaseline, formatScores(baseline));
            setText(elProposed, formatScores(proposed));

            // Pendant le run : afficher le best MAE du job dans "Proposé" si pas encore de brouillon
            if (!proposed && job && job.best_mae_so_far != null && (status === 'running' || status === 'queued')) {
                setText(elProposed, 'meilleur MAE ' + Number(job.best_mae_so_far).toFixed(2) + ' (en cours)');
            }

            var improv = draftScores && draftScores.mae_improvement_pct != null
                ? draftScores.mae_improvement_pct
                : null;
            if (improv != null) {
                setText(elImprovement, (improv >= 0 ? '−' : '+') + Math.abs(Number(improv)).toFixed(1) + ' % MAE');
            } else {
                setText(elImprovement, '—');
            }

            var adapt = draftScores && draftScores.seasonality_adaptation
                ? draftScores.seasonality_adaptation
                : null;
            if (adapt && adapt.notes && adapt.notes.length) {
                setText(elSeasonAdaptText, adapt.notes.join(' · '));
                show(elSeasonAdapt, true);
            } else {
                setText(elSeasonAdaptText, '');
                show(elSeasonAdapt, false);
            }

            var hasDraft = !!(offer.draft_params);
            show(elDraftActions, hasDraft && !running);
            show(elRollback, !!offer.has_previous && !running);

            if (btnStart) {
                btnStart.disabled = running || busy;
            }
            show(btnCancel, running);
            if (btnCancel) {
                btnCancel.disabled = busy;
            }

            if (running) {
                startPoll();
            } else {
                stopPoll();
            }

            if (job && job.auto_applied && status === 'completed') {
                setText(elMessage, 'Profil appliqué automatiquement (auto-apply).');
            }
        }

        function refresh() {
            return fetchStatus()
                .then(render)
                .catch(function (err) {
                    console.error('[prophet-tuning] status', err);
                });
        }

        function flash(msg, isError) {
            setText(elMessage, msg || '');
            if (elMessage) {
                elMessage.classList.toggle('text-danger', !!isError);
                elMessage.classList.toggle('text-success', !isError);
            }
        }

        if (btnStart) {
            btnStart.addEventListener('click', function () {
                if (busy) {
                    return;
                }
                busy = true;
                btnStart.disabled = true;
                flash('Mise en file…', false);
                post(urls.start)
                    .then(function (r) {
                        flash((r.body && r.body.message) || (r.ok ? 'OK' : 'Erreur'), !r.ok);
                        return refresh();
                    })
                    .catch(function (err) {
                        flash(String(err), true);
                    })
                    .finally(function () {
                        busy = false;
                    });
            });
        }

        function bindPost(btn, url, confirmMsg) {
            if (!btn) {
                return;
            }
            btn.addEventListener('click', function () {
                if (busy) {
                    return;
                }
                if (confirmMsg && !window.confirm(confirmMsg)) {
                    return;
                }
                busy = true;
                post(url)
                    .then(function (r) {
                        flash((r.body && r.body.message) || (r.ok ? 'OK' : 'Erreur'), !r.ok);
                        return refresh();
                    })
                    .catch(function (err) {
                        flash(String(err), true);
                    })
                    .finally(function () {
                        busy = false;
                    });
            });
        }

        bindPost(btnApply, urls.apply, 'Appliquer le brouillon Optuna comme profil Prophet officiel ?');
        bindPost(btnReject, urls.reject, 'Rejeter le brouillon Optuna ?');
        bindPost(btnRollback, urls.rollback, 'Restaurer le profil Prophet précédent ?');
        bindPost(
            btnCancel,
            urls.cancel,
            'Annuler ce job Optuna ? S’il est en cours, l’arrêt prend effet après le trial en cours.'
        );

        refresh();
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.getElementById('prophet-tuning-root');
        if (root) {
            init(root);
        }
    });
})();
