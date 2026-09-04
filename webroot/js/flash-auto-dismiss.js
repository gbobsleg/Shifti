/**
 * Auto-dismiss et fermeture des toasts flash (.flash-toast).
 */
(function () {
    'use strict';

    function closeToast(el) {
        if (!el || el.classList.contains('is-closing')) {
            return;
        }
        el.classList.add('is-closing');
        var removed = false;
        function finish() {
            if (removed) {
                return;
            }
            removed = true;
            el.removeEventListener('transitionend', onEnd);
            if (el.parentNode) {
                el.parentNode.removeChild(el);
            }
        }
        function onEnd(event) {
            if (event.target !== el || event.propertyName !== 'max-height') {
                return;
            }
            finish();
        }
        el.addEventListener('transitionend', onEnd);
        window.setTimeout(finish, 600);
    }

    function initFlashAutoDismiss(root) {
        var scope = root;
        if (scope && !scope.querySelectorAll && scope.jquery && scope[0]) {
            scope = scope[0];
        }
        if (!scope || !scope.querySelectorAll) {
            scope = document;
        }
        var nodes = scope.querySelectorAll('.flash-toast[data-auto-dismiss]:not([data-flash-timer])');
        nodes.forEach(function (el) {
            var delay = parseInt(el.getAttribute('data-auto-dismiss'), 10);
            el.setAttribute('data-flash-timer', '1');
            if (delay > 0 && !isNaN(delay)) {
                window.setTimeout(function () {
                    closeToast(el);
                }, delay);
            }
        });
    }

    document.addEventListener('click', function (event) {
        var target = event.target;
        if (target && target.nodeType !== 1) {
            target = target.parentElement;
        }
        if (!target || typeof target.closest !== 'function') {
            return;
        }
        var btn = target.closest('[data-flash-dismiss]');
        if (!btn) {
            return;
        }
        var toast = btn.closest('.flash-toast');
        if (!toast) {
            return;
        }
        event.preventDefault();
        closeToast(toast);
    });

    function boot() {
        initFlashAutoDismiss(document);
        var container = document.querySelector('.flash-container');
        if (container && typeof MutationObserver !== 'undefined') {
            new MutationObserver(function () {
                initFlashAutoDismiss(container);
            }).observe(container, { childList: true, subtree: true });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    window.initFlashAutoDismiss = initFlashAutoDismiss;
}());
