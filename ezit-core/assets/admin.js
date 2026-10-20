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

    // ── Branded confirm — data-confirm forms, zero native dialogs ────
    // <form data-confirm="…" data-confirm-title="…" data-confirm-ok="…">
    // Intercept submit, ask in our own overlay, re-submit on OK.
    function ezitConfirm(opts, onOk) {
        var ov = document.createElement('div');
        ov.className = 'ezit-confirm';
        var box = document.createElement('div');
        box.className = 'ezit-confirm__box';
        var h = document.createElement('h3');
        h.textContent = opts.title;
        var p = document.createElement('p');
        p.textContent = opts.message;
        var row = document.createElement('div');
        row.className = 'ezit-confirm__row';
        var cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'ezit-confirm__cancel';
        cancel.textContent = opts.cancel;
        var ok = document.createElement('button');
        ok.type = 'button';
        ok.className = 'ezit-confirm__ok';
        ok.textContent = opts.ok;
        row.appendChild(cancel);
        row.appendChild(ok);
        box.appendChild(h);
        box.appendChild(p);
        box.appendChild(row);
        ov.appendChild(box);
        document.body.appendChild(ov);
        var done = function () { ov.remove(); document.removeEventListener('keydown', esc); };
        var esc = function (e) { if (e.key === 'Escape') done(); };
        ov.addEventListener('click', function (e) { if (e.target === ov) done(); });
        cancel.addEventListener('click', done);
        ok.addEventListener('click', function () { done(); onOk(); });
        document.addEventListener('keydown', esc);
        ok.focus();
    }

    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (!(f instanceof HTMLFormElement) || !f.getAttribute('data-confirm')) {
            return;
        }
        if (f.dataset.ezitConfirmed === '1') {
            return; // second pass after OK — let it through
        }
        e.preventDefault();
        var submitter = e.submitter || null;
        ezitConfirm({
            title:   f.getAttribute('data-confirm-title') || 'Are you sure?',
            message: f.getAttribute('data-confirm'),
            ok:      f.getAttribute('data-confirm-ok') || 'Continue',
            cancel:  f.getAttribute('data-confirm-cancel') || 'Cancel',
        }, function () {
            f.dataset.ezitConfirmed = '1';
            if (f.requestSubmit) {
                f.requestSubmit(submitter || undefined);
            } else {
                f.submit();
            }
        });
    }, true);

    // ── Toasts — bottom-right pills (shared .ezit-flash styling) ──
    function ezitToast(msg, isErr) {
        var host = document.querySelector('.ezit-pg') || document.body;
        var t = document.createElement('div');
        t.className = 'ezit-flash ' + (isErr ? 'is-err' : 'is-ok');
        t.setAttribute('role', 'status');
        var ns = 'http://www.w3.org/2000/svg';
        var svg = document.createElementNS(ns, 'svg');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('width', '18'); svg.setAttribute('height', '18');
        svg.setAttribute('fill', 'none'); svg.setAttribute('stroke', 'currentColor');
        svg.setAttribute('stroke-width', '2'); svg.setAttribute('stroke-linecap', 'round');
        svg.setAttribute('stroke-linejoin', 'round');
        var c = document.createElementNS(ns, 'circle');
        c.setAttribute('cx', '12'); c.setAttribute('cy', '12'); c.setAttribute('r', '10');
        var p1 = document.createElementNS(ns, 'path');
        p1.setAttribute('d', isErr ? 'M12 8v5m0 3h.01' : 'm8.5 12.5 2.5 2.5 5-5.5');
        svg.appendChild(c); svg.appendChild(p1);
        var p = document.createElement('p');
        p.textContent = msg;
        t.appendChild(svg); t.appendChild(p);
        host.appendChild(t);
        window.setTimeout(function () {
            t.classList.add('is-out');
            window.setTimeout(function () { t.remove(); }, 320);
        }, isErr ? 6000 : 4000);
    }

    // ── Marketplace refresh — swap the grid in place, no reload ────
    // The button's href still runs handle_refresh() server-side
    // (clears the update/version/catalog transients, then renders
    // fresh). We fetch it, pull the new grid + version modals + the
    // fetched-age stamp out of the response, and patch our DOM.
    var refreshBtn = document.querySelector('.ezt__refresh');
    if (refreshBtn && window.fetch && window.DOMParser) {
        refreshBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (refreshBtn.classList.contains('is-busy')) {
                return;
            }
            refreshBtn.classList.add('is-busy');
            window.fetch(refreshBtn.href, { credentials: 'same-origin' })
                .then(function (r) { return r.text(); })
                .then(function (html) {
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    var grid = document.querySelector('.ezit-grid');
                    var fresh = doc.querySelector('.ezit-grid');
                    if (grid && fresh) {
                        grid.replaceWith(document.importNode(fresh, true));
                    }
                    // Version modals print at admin_footer — replace ours
                    // with the response's so picker state stays honest.
                    document.querySelectorAll('.ezit-vm, .ezit-vm__toggle').forEach(function (n) { n.remove(); });
                    doc.querySelectorAll('.ezit-vm__toggle, .ezit-vm').forEach(function (n) {
                        document.body.appendChild(document.importNode(n, true));
                    });
                    var stamp = document.querySelector('.ezit-catalog-at');
                    var freshStamp = doc.querySelector('.ezit-catalog-at');
                    if (stamp && freshStamp) {
                        stamp.replaceWith(document.importNode(freshStamp, true));
                    }
                    refreshBtn.classList.remove('is-busy');
                    ezitToast('Marketplace refreshed — latest catalog loaded.', false);
                })
                .catch(function () {
                    refreshBtn.classList.remove('is-busy');
                    ezitToast('Refresh failed — check your connection and try again.', true);
                });
        });
    }

    // ── Applied-updates dismiss — clear in place, no reload ────────
    // The link still works without JS; with JS we fire the clear
    // endpoint in the background and fade the strip out.
    document.addEventListener('click', function (e) {
        var a = e.target.closest && e.target.closest('.ezit-applied__dismiss');
        if (!a) {
            return;
        }
        e.preventDefault();
        var card = a.closest('.ezit-applied');
        if (card) {
            card.style.transition = 'opacity .25s ease, transform .25s ease';
            card.style.opacity = '0';
            card.style.transform = 'translateY(-6px)';
            window.setTimeout(function () { card.remove(); }, 260);
        }
        if (window.fetch) {
            window.fetch(a.href, { credentials: 'same-origin' }).catch(function () {});
        }
    });

    // ── Catalog age ticker ──────────────────────────────────────────
    // Nodes are re-queried every tick — the AJAX marketplace refresh
    // replaces the stamp element, and a cached NodeList would keep
    // ticking a detached one.

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
        document.querySelectorAll('.ezit-catalog-at__age[data-ts]').forEach(function (el) {
            var ts = parseInt(el.getAttribute('data-ts'), 10);
            if (ts > 0) {
                el.textContent = el.getAttribute('data-label') + ' ' + ago(ts);
            }
        });
    }

    tick();
    window.setInterval(tick, 1000);
})();
