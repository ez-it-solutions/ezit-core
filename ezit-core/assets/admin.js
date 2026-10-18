/**
 * Ez IT Core — shared admin helpers.
 *
 * Live "Fetched N ago" counters: .ezit-catalog-at__age carries the
 * last-fetch epoch (data-ts) and its translated label (data-label).
 * We retick every second so the number is real instead of a frozen
 * page-load snapshot.
 */
(function () {
    'use strict';

    var els = document.querySelectorAll('.ezit-catalog-at__age[data-ts]');
    if (!els.length) {
        return;
    }

    function ago(ts) {
        var s = Math.max(0, Math.floor(Date.now() / 1000) - ts);
        if (s < 5) {
            return 'just now';
        }
        if (s < 60) {
            return s === 1 ? '1 second ago' : s + ' seconds ago';
        }
        var m = Math.floor(s / 60);
        if (m < 60) {
            return m === 1 ? '1 minute ago' : m + ' minutes ago';
        }
        var h = Math.floor(m / 60);
        if (h < 24) {
            return h === 1 ? '1 hour ago' : h + ' hours ago';
        }
        var d = Math.floor(h / 24);
        return d === 1 ? '1 day ago' : d + ' days ago';
    }

    function tick() {
        els.forEach(function (el) {
            var ts = parseInt(el.getAttribute('data-ts'), 10);
            if (ts > 0) {
                el.textContent = el.getAttribute('data-label') + ' ' + ago(ts);
            }
        });
    }

    tick();
    window.setInterval(tick, 1000);
})();
