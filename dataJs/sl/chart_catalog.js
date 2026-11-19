"use strict";

// Search and filter the static chart references; production charts use live data.
(function () {
    var search = document.getElementById('chart-library-search');
    var grid = document.getElementById('chart-library-grid');
    var count = document.getElementById('chart-library-count');
    var empty = document.getElementById('chart-library-empty');
    if (!search || !grid || !count || !empty) return;

    var items = Array.prototype.slice.call(grid.querySelectorAll('[data-chart-group]'));
    var buttons = Array.prototype.slice.call(document.querySelectorAll('[data-chart-filter]'));
    var active = 'all';

    function update() {
        var query = search.value.trim().toLocaleLowerCase();
        var visible = 0;
        items.forEach(function (item) {
            var group = item.getAttribute('data-chart-group') || '';
            var name = item.getAttribute('data-chart-name') || '';
            var match = (active === 'all' || active === group) && (!query || (name + ' ' + group.toLocaleLowerCase()).indexOf(query) !== -1);
            item.hidden = !match;
            if (match) visible++;
        });
        count.textContent = visible + ' of ' + items.length + ' charts';
        empty.hidden = visible !== 0;
    }

    buttons.forEach(function (button) {
        button.addEventListener('click', function () {
            active = button.getAttribute('data-chart-filter') || 'all';
            buttons.forEach(function (other) {
                var selected = other === button;
                other.classList.toggle('is-active', selected);
                other.setAttribute('aria-pressed', selected ? 'true' : 'false');
            });
            update();
        });
    });
    search.addEventListener('input', update);
    update();
})();
