/**
 * Évite le clipping des menus Actions (dropdown) par overflow en les déplaçant dans body.
 * S'applique à tous les .actions-dropdown sur la page.
 */
$(document).ready(function() {
    $(document).on('show.bs.dropdown', '.actions-dropdown', function() {
        var $dropdown = $(this);
        var $menu = $dropdown.find('.actions-dropdown-menu');
        if ($menu.length) {
            $menu.appendTo('body');
        }
    });
    $(document).on('hide.bs.dropdown', '.actions-dropdown', function() {
        var entityId = $(this).data('entity-id');
        var $menu = $('body').find('.actions-dropdown-menu[data-entity-id="' + entityId + '"]');
        if ($menu.length) {
            $menu.appendTo('.actions-dropdown[data-entity-id="' + entityId + '"]');
        }
    });
});
