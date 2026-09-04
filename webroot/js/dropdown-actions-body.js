/**
 * Évite le clipping des menus Actions (dropdown) par overflow en les déplaçant dans body.
 * BS5 : show.bs.dropdown / hide.bs.dropdown partent du .dropdown-toggle et bubblent.
 */
document.addEventListener('show.bs.dropdown', function (event) {
    var root = event.target && event.target.closest ? event.target.closest('.actions-dropdown') : null;
    if (!root) {
        return;
    }
    var menu = root.querySelector('.actions-dropdown-menu');
    if (menu) {
        document.body.appendChild(menu);
    }
});

document.addEventListener('hide.bs.dropdown', function (event) {
    var root = event.target && event.target.closest ? event.target.closest('.actions-dropdown') : null;
    if (!root) {
        return;
    }
    var entityId = root.getAttribute('data-entity-id');
    if (!entityId) {
        return;
    }
    var menu = document.body.querySelector('.actions-dropdown-menu[data-entity-id="' + entityId + '"]');
    var host = document.querySelector('.actions-dropdown[data-entity-id="' + entityId + '"]');
    if (menu && host) {
        host.appendChild(menu);
    }
});
