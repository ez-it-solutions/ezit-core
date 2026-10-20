/**
 * Ez IT Core — shared admin helpers.
 *
 * 1. Flash toasts: .ezit-flash renders server-side from ?ezit_msg; we
 *    fade it out after a few seconds, wire the circled-X close button,
 *    and strip the flash params from the URL so a refresh doesn't
 *    re-fire the toast.
 *
 * 2. Live "Fetched N ago" counters: .ezit-catalog-at__age carries the
 *    last-fetch epoch (data-ts) and its translated label (data-label).
 *    We retick every second so the number is real instead of a frozen
 *    page-load snapshot.
 */
(function () {
    'use strict';

    // ── Flash toasts ────────────────────────────────────────────────
    var flash = document.querySelector('.ezit-flash');
    if (flash) {
        var gone = false;
        var dismiss = function () {
            if (gone) {
                return;
            }
            gone = true;
            flash.classList.add('is-out');
            window.setTimeout(function () { flash.remove(); }, 350);
        };
        var x = flash.querySelector('.ezit-flash__close');
        if (x) {
            x.addEventListener('click', dismiss);
        }
        window.setTimeout(dismiss, flash.classList.contains('is-err') ? 7000 : 5000);
    }
    try {
        var u = new URL(window.location.href);
        if (u.searchParams.has('ezit_msg') || u.searchParams.has('ezit_detail')) {
            u.searchParams.delete('ezit_msg');
            u.searchParams.delete('ezit_detail');
            window.history.replaceState({}, '', u);
        }
    } catch (e) { /* older browsers keep the params — harmless */ }

    // ── Catalog age ticker ──────────────────────────────────────────
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
