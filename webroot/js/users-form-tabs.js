/**
 * Onglets fiche utilisateur : hash, erreurs, contrats.
 */
document.addEventListener('DOMContentLoaded', function () {
    var hash = window.location.hash;
    if (hash && typeof bootstrap !== 'undefined' && bootstrap.Tab) {
        var hashBtn = document.querySelector('[data-bs-target="' + hash + '"]');
        if (hashBtn) {
            bootstrap.Tab.getOrCreateInstance(hashBtn).show();
        }
    }

    var userForm = document.querySelector('.users.form form');
    if (userForm) {
        userForm.addEventListener('submit', function () {
            var active = document.querySelector('.tab-pane.active');
            if (!active || !active.id) {
                return;
            }
            var input = userForm.querySelector('input[name="_active_tab"]');
            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = '_active_tab';
                userForm.appendChild(input);
            }
            input.value = active.id;
        });
    }

    function localToday() {
        var now = new Date();
        return now.getFullYear()
            + '-' + String(now.getMonth() + 1).padStart(2, '0')
            + '-' + String(now.getDate()).padStart(2, '0');
    }

    function rowEndInput(row) {
        return row ? row.querySelector('input[type="date"][name*="[end_date]"]') : null;
    }

    function hasOpenContract() {
        var rows = document.querySelectorAll('#contracts-table tbody tr');
        return Array.prototype.some.call(rows, function (row) {
            var end = rowEndInput(row);
            var start = row.querySelector('input[type="date"][name*="[start_date]"]');
            return start && start.value && end && !end.value;
        });
    }

    document.querySelectorAll('.js-delete-contract').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var message = btn.getAttribute('data-confirm') || 'Supprimer ce contrat ?';
            if (!window.confirm(message)) {
                return;
            }
            var form = document.getElementById(btn.getAttribute('data-form'));
            if (form) {
                form.requestSubmit();
            }
        });
    });

    var addBtn = document.getElementById('add-contract-btn');
    if (addBtn) {
        addBtn.addEventListener('click', function () {
            if (hasOpenContract()) {
                window.alert('Indiquez une date de fin au contrat en cours avant d’en ajouter un autre.');
                return;
            }
            var tbody = document.querySelector('#contracts-table tbody');
            if (!tbody) {
                return;
            }
            var index = tbody.querySelectorAll('tr').length;
            var today = localToday();
            var row = document.createElement('tr');
            row.innerHTML =
                '<td>'
                + '<input type="hidden" name="contracts[' + index + '][id]" value="">'
                + '<input type="date" name="contracts[' + index + '][start_date]" class="form-control form-control-sm" value="' + today + '">'
                + '</td>'
                + '<td>'
                + '<input type="date" name="contracts[' + index + '][end_date]" class="form-control form-control-sm" value="">'
                + '</td>'
                + '<td>Nouveau</td>'
                + '<td><button type="button" class="btn btn-sm btn-outline-danger js-remove-new-contract"><i class="bi bi-trash"></i></button></td>';
            tbody.appendChild(row);
            row.querySelector('.js-remove-new-contract').addEventListener('click', function () {
                row.remove();
            });
        });
    }

    var error = document.querySelector(
        '.tab-pane .error, .tab-pane .is-invalid, .tab-pane .form-error, .tab-pane .error-message'
    );
    if (!error) {
        return;
    }
    var pane = error.closest('.tab-pane');
    if (!pane || !pane.id) {
        return;
    }
    var btn = document.querySelector('[data-bs-target="#' + pane.id + '"]');
    if (!btn || typeof bootstrap === 'undefined' || !bootstrap.Tab) {
        return;
    }
    bootstrap.Tab.getOrCreateInstance(btn).show();
});
