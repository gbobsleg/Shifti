/**
 * Garde-fou : quitter la grille avec des cellules .is-modified.
 */
(function () {
    'use strict';

    var LEAVE_MESSAGE = 'Des modifications de planning ne sont pas enregistrées. Si vous continuez, elles seront perdues.';
    var leaveArmed = false;
    var pendingResolve = null;
    var modalEl = null;
    var bsModal = null;

    window.gridsHasUnsavedChanges = function () {
        return document.querySelectorAll('.td_quarter.is-modified').length > 0;
    };

    window.gridsConfirmLeave = function () {
        if (!window.gridsHasUnsavedChanges()) {
            return Promise.resolve(true);
        }
        return showLeaveModal();
    };

    window.gridsWhenLeaveAllowed = function (proceed) {
        window.gridsConfirmLeave().then(function (ok) {
            if (ok && typeof proceed === 'function') {
                proceed();
            }
        });
    };

    function blockEvent(event) {
        event.preventDefault();
        event.stopImmediatePropagation();
    }

    function finishLeave(ok) {
        if (ok) {
            leaveArmed = true;
        }
        var resolve = pendingResolve;
        pendingResolve = null;
        if (bsModal) {
            bsModal.hide();
        }
        if (typeof resolve === 'function') {
            resolve(!!ok);
        }
    }

    function ensureModal() {
        if (modalEl && document.body.contains(modalEl)) {
            return modalEl;
        }
        modalEl = document.getElementById('gridsLeaveModal');
        if (!modalEl) {
            modalEl = document.createElement('div');
            modalEl.id = 'gridsLeaveModal';
            modalEl.className = 'modal fade';
            modalEl.tabIndex = -1;
            modalEl.setAttribute('aria-labelledby', 'gridsLeaveModalTitle');
            modalEl.setAttribute('aria-hidden', 'true');
            modalEl.innerHTML =
                '<div class="modal-dialog modal-dialog-centered">' +
                    '<div class="modal-content">' +
                        '<div class="modal-header">' +
                            '<h5 class="modal-title" id="gridsLeaveModalTitle">Modifications non enregistrées</h5>' +
                            '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>' +
                        '</div>' +
                        '<div class="modal-body">' +
                            '<p class="mb-0">' + LEAVE_MESSAGE + '</p>' +
                        '</div>' +
                        '<div class="modal-footer">' +
                            '<button type="button" class="btn btn-outline-danger" id="gridsLeaveContinue">Continuer sans enregistrer</button>' +
                            '<button type="button" class="btn btn-primary" id="gridsLeaveStay" data-bs-dismiss="modal">Rester sur la page</button>' +
                        '</div>' +
                    '</div>' +
                '</div>';
            document.body.appendChild(modalEl);
        }
        modalEl.querySelector('#gridsLeaveContinue').addEventListener('click', function () {
            finishLeave(true);
        });
        modalEl.addEventListener('hidden.bs.modal', function () {
            if (pendingResolve) {
                finishLeave(false);
            }
        });
        return modalEl;
    }

    function showLeaveModal() {
        if (pendingResolve) {
            return new Promise(function (resolve) {
                var previous = pendingResolve;
                pendingResolve = function (ok) {
                    previous(ok);
                    resolve(ok);
                };
            });
        }
        return new Promise(function (resolve) {
            pendingResolve = resolve;
            var el = ensureModal();
            if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                var ok = window.confirm(LEAVE_MESSAGE);
                finishLeave(ok);
                return;
            }
            bsModal = bootstrap.Modal.getOrCreateInstance(el, { backdrop: true, keyboard: true });
            bsModal.show();
            window.setTimeout(function () {
                var stay = el.querySelector('#gridsLeaveStay');
                if (stay) {
                    stay.focus();
                }
            }, 150);
        });
    }

    function isHashOrScriptHref(raw) {
        var href = String(raw || '').trim();
        if (href === '' || href === '#') {
            return true;
        }
        if (href.charAt(0) === '#') {
            return true;
        }
        return href.toLowerCase().indexOf('javascript:') === 0;
    }

    function isSameDocumentLink(anchor) {
        try {
            var url = new URL(anchor.href, window.location.href);
            return url.origin === window.location.origin
                && url.pathname === window.location.pathname
                && url.search === window.location.search;
        } catch (e) {
            return false;
        }
    }

    function isLeaveAnchor(anchor) {
        if (!anchor) {
            return false;
        }
        if (isHashOrScriptHref(anchor.getAttribute('href'))) {
            return false;
        }
        if (anchor.target === '_blank') {
            return false;
        }
        if (anchor.hasAttribute('download')) {
            return false;
        }
        if (anchor.closest('#rangesForm')) {
            return false;
        }
        if (isSameDocumentLink(anchor)) {
            return false;
        }
        return true;
    }

    function isSaveForm(form) {
        return !!(form && (form.id === 'rangesForm' || form.closest('#rangesForm')));
    }

    function openAlertAddModal() {
        var alertModal = document.getElementById('alertAddModal');
        if (alertModal && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(alertModal).show();
        }
    }

    document.addEventListener('click', function (event) {
        var target = event.target;
        if (!target || typeof target.closest !== 'function') {
            return;
        }

        var alertAddBtn = target.closest('[data-bs-target="#alertAddModal"]');
        if (alertAddBtn) {
            if (leaveArmed || !window.gridsHasUnsavedChanges()) {
                return;
            }
            blockEvent(event);
            window.gridsWhenLeaveAllowed(openAlertAddModal);
            return;
        }

        var anchor = target.closest('a[href]');
        if (!isLeaveAnchor(anchor)) {
            return;
        }
        if (!window.gridsHasUnsavedChanges()) {
            return;
        }
        blockEvent(event);
        window.gridsWhenLeaveAllowed(function () {
            window.location.assign(anchor.href);
        });
    }, true);

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement) || isSaveForm(form)) {
            return;
        }
        if (!window.gridsHasUnsavedChanges()) {
            return;
        }
        blockEvent(event);
        window.gridsWhenLeaveAllowed(function () {
            HTMLFormElement.prototype.submit.call(form);
        });
    }, true);

    window.addEventListener('beforeunload', function (event) {
        if (leaveArmed || !window.gridsHasUnsavedChanges()) {
            return;
        }
        event.preventDefault();
        event.returnValue = '';
    });
}());
