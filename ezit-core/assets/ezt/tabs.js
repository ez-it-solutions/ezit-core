/**
 * EzIT Tabs — auto-rotating tabbed layout.
 *
 * Vanilla JS, dependency-free. Initialized on every [data-ezt] component.
 * - Auto-advances every `data-ezt-interval` ms (default 5000)
 * - Progress bar on the active tab tracks the countdown via CSS animation
 * - Pauses on hover / focus-within, resumes on leave
 * - Click or Arrow/Home/End keys to navigate (WAI-ARIA tab pattern)
 * - Respects prefers-reduced-motion (auto-rotate still runs, no transitions)
 */
(function () {
    'use strict';

    class EztTabs {
        constructor(root) {
            this.root = root;
            // Filter to THIS component — a nested [data-ezt] (e.g. a
            // settings page embedded in a panel) must not join our lists.
            this.tabs = [...root.querySelectorAll('[data-ezt-tab]')]
                .filter((t) => t.closest('[data-ezt]') === root);
            this.panels = [...root.querySelectorAll('[data-ezt-panel]')]
                .filter((p) => p.closest('[data-ezt]') === root);
            const intervalAttr = parseInt(root.dataset.eztInterval, 10);
            this.interval = isNaN(intervalAttr) ? 5000 : intervalAttr;
            this.current = this.tabs.findIndex(t => t.getAttribute('aria-selected') === 'true');
            if (this.current < 0) this.current = 0;
            this.timer = null;
            this.paused = false;
            // Timer visuals exist only while auto-rotate is armed —
            // Speed=Off hides every placement (independent of pause).
            root.classList.toggle('ezt--timer-on', this.interval > 0);

            // Auto fill: unless the author pinned data-ezt-fill, the
            // component decides itself whether tabs stretch (syncFill).
            this._fillAuto = !root.hasAttribute('data-ezt-fill');

            root.style.setProperty('--ezt-interval', this.interval + 'ms');
            root._ezt = this;

            this.syncTabColors();
            this.bind();
            this.show(this.current, { initial: true });
            this.start();
        }

        /* Mirror each panel's theme vars onto its tab so the active tab
           matches the content area (badge + progress overrides optional). */
        syncTabColors() {
            const vars = {
                '--panel-bg': '--tab-bg',
                '--panel-fg': '--tab-fg',
                '--panel-badge-bg': '--tab-badge-bg',
                '--panel-badge-fg': '--tab-badge-fg',
                '--panel-progress': '--tab-progress',
            };
            this.tabs.forEach((tab, i) => {
                const st = getComputedStyle(this.panels[i]);
                // Set on the li so the button AND li pseudos (Mixed fake tabs) inherit.
                const target = tab.closest('li') || tab;
                // Bold-state width reservation — CSS overlays an invisible
                // bold clone via attr(data-label) so selection never reflows.
                const label = tab.querySelector('.ezt__tab-label');
                if (label && !label.dataset.label) {
                    label.dataset.label = label.textContent.trim();
                }
                for (const [from, to] of Object.entries(vars)) {
                    const v = st.getPropertyValue(from).trim();
                    if (v) target.style.setProperty(to, v);
                }
            });
        }

        bind() {
            this.tabs.forEach((tab, i) => {
                tab.addEventListener('click', () => this.show(i));
            });

            this.root.addEventListener('keydown', (e) => this.onKeydown(e));
            // Focus ring is keyboard-only — track the last input modality.
            // Document-level so a click anywhere (even outside the
            // component) drops the flag, not just clicks on this root.
            document.addEventListener('pointerdown', () => this.root.classList.remove('ezt--kb'));

            // Pause while the user is interacting with the component.
            this.root.addEventListener('mouseenter', () => this.pause());
            this.root.addEventListener('mouseleave', () => this.resume());
            this.root.addEventListener('focusin', () => this.pause());
            this.root.addEventListener('focusout', (e) => {
                if (!this.root.contains(e.relatedTarget)) this.resume();
            });

            // Pause when tab is hidden — avoids skipped progress + drift.
            document.addEventListener('visibilitychange', () => {
                document.hidden ? this.pause() : this.resume();
            });

            // Re-evaluate fill mode whenever either column's height
            // changes (tabs added, panel content reflows, fonts load).
            this._ro = new ResizeObserver(() => this.syncFill());
            const tabsEl = this.tabs[0]?.closest('.ezt__tabs');
            const wrap = this.panels[0]?.closest('.ezt__panels');
            if (tabsEl) this._ro.observe(tabsEl);
            if (wrap) this._ro.observe(wrap);
        }

        /* Auto fill: with no author-pinned data-ezt-fill, compare the tab
           column's natural height with the active panel's content height.
           Tabs taller  -> stretch so the last tab merges with the card's
           far edge. Panel taller -> pack the tabs at the start edge. */
        syncFill() {
            if (!this._fillAuto || this.root.dataset.eztDir === 'top') return;
            const tabsEl = this.tabs[0]?.closest('.ezt__tabs');
            const wrap = this.panels[0]?.closest('.ezt__panels');
            if (!tabsEl || !wrap) return;

            // Always measure in "packed" mode (and ignore any in-progress
            // height morph) so the decision can't oscillate.
            const inlineH = wrap.style.height;
            wrap.style.height = 'auto';
            this.root.setAttribute('data-ezt-fill', 'off');
            const tabsH = tabsEl.offsetHeight;
            const contentH = wrap.offsetHeight;
            if (tabsH > contentH + 1) this.root.removeAttribute('data-ezt-fill');
            wrap.style.height = inlineH;
        }

        onKeydown(e) {
            const keys = ['ArrowDown', 'ArrowRight', 'ArrowUp', 'ArrowLeft', 'Home', 'End'];
            if (!keys.includes(e.key)) return;
            // Bubbles from nested components too — only act when the
            // focused tab belongs to THIS instance.
            if (document.activeElement?.closest?.('[data-ezt]') !== this.root) return;

            e.preventDefault();
            this.root.classList.add('ezt--kb');
            const n = this.tabs.length;
            let i = this.current;

            if (e.key === 'ArrowDown' || e.key === 'ArrowRight') i = (i + 1) % n;
            if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') i = (i - 1 + n) % n;
            if (e.key === 'Home') i = 0;
            if (e.key === 'End') i = n - 1;

            this.show(i);
            this.tabs[i].focus();
        }

        show(index, { initial = false } = {}) {
            if (index === this.current && !initial) {
                this.restart(); // re-clicking still resets the countdown
                return;
            }

            const prevTab = this.tabs[this.current];

            this.tabs.forEach((tab, i) => {
                const active = i === index;
                tab.setAttribute('aria-selected', String(active));
                tab.tabIndex = active ? 0 : -1;
            });

            // 'Pull' transition choreography (skip on first paint).
            if (!initial) {
                // Outgoing tab: pulled into the content area while it still
                // holds its panel colors, then reappears as a plain tab.
                if (prevTab) {
                    prevTab.classList.remove(
                        'ezt__tab--leaving',
                        'ezt__tab--entering',
                        'ezt__tab--reappearing'
                    );
                    prevTab.classList.add('ezt__tab--leaving');
                    const onOutEnd = (e) => {
                        if (e.animationName !== 'ezt-tab-out') return;
                        prevTab.removeEventListener('animationend', onOutEnd);
                        prevTab.classList.remove('ezt__tab--leaving');
                        prevTab.classList.add('ezt__tab--reappearing');
                    };
                    prevTab.addEventListener('animationend', onOutEnd);
                    // Non-pull anims never fire 'ezt-tab-out' — clean up by
                    // timeout or the class (and Mixed fake tabs) would linger.
                    window.setTimeout(() => {
                        if (prevTab.classList.contains('ezt__tab--leaving')) {
                            prevTab.classList.remove('ezt__tab--leaving');
                            if (prevTab.getAttribute('aria-selected') !== 'true') {
                                prevTab.classList.add('ezt__tab--reappearing');
                            }
                        }
                    }, 600);
                }

                // Incoming tab: grows out of the content area into place.
                const newTab = this.tabs[index];
                newTab.classList.remove(
                    'ezt__tab--leaving',
                    'ezt__tab--entering',
                    'ezt__tab--reappearing'
                );
                newTab.classList.add('ezt__tab--entering');
                const onInEnd = (e) => {
                    if (e.animationName !== 'ezt-tab-in') return;
                    newTab.removeEventListener('animationend', onInEnd);
                    newTab.classList.remove('ezt__tab--entering');
                };
                newTab.addEventListener('animationend', onInEnd);
                window.setTimeout(() => {
                    newTab.classList.remove('ezt__tab--entering');
                }, 650);
            }

            // Adaptive height — capture the wrapper height BEFORE the
            // swap; once the new panel goes in-flow it reports the new
            // natural height, and we morph old → new.
            const wrap = this.panels[0]?.closest('.ezt__panels');
            const oldH = wrap ? wrap.offsetHeight : 0;

            // Paint the wrapper with the INCOMING panel color before the
            // crossfade — the gap behind a fading-out panel reads as
            // white otherwise.
            if (wrap && this.panels[index]) {
                const bg = getComputedStyle(this.panels[index])
                    .getPropertyValue('--panel-bg').trim();
                if (bg) wrap.style.setProperty('--panels-bg', bg);
            }

            this.panels.forEach((panel, i) => {
                const active = i === index;
                if (active) {
                    panel.hidden = false;
                    // First/last tab ↔ square the card's matching corner.
                    const horizontal = this.root.dataset.eztDir === 'top';
                    if (i === 0) panel.dataset.eztEdge = horizontal ? 'left' : 'top';
                    else if (i === this.panels.length - 1) panel.dataset.eztEdge = horizontal ? 'right' : 'bottom';
                    else delete panel.dataset.eztEdge;
                    // Force reflow so the enter transition re-triggers.
                    void panel.offsetWidth;
                    panel.classList.add('ezt__panel--active');
                } else {
                    panel.classList.remove('ezt__panel--active');
                    // Keep it in the a11y tree only while animating out.
                    window.setTimeout(() => {
                        if (!panel.classList.contains('ezt__panel--active')) {
                            panel.hidden = true;
                        }
                    }, 500);
                }
            });

            if (wrap) {
                // Settle the fill mode first — it may change the final
                // height (stretched tabs can grow the row).
                this.syncFill();
                const newH = wrap.offsetHeight;
                if (!initial && Math.abs(newH - oldH) > 1) {
                    wrap.style.height = oldH + 'px';
                    void wrap.offsetHeight;
                    wrap.style.transition = 'height .5s var(--ezt-ease)';
                    wrap.style.height = newH + 'px';
                    clearTimeout(this._heightTimer);
                    this._heightTimer = window.setTimeout(() => {
                        wrap.style.transition = '';
                        wrap.style.height = '';
                    }, 550);
                }
            }

            this.current = index;

            // Deep-linking opt-in: [data-ezt-url-param="x"] rewrites ?x=<tab-id>
            // via replaceState so refresh keeps the tab (no reload).
            const param = this.root.dataset.eztUrlParam;
            if (param && this.tabs[index]) {
                const id = (this.tabs[index].getAttribute('aria-controls') || '')
                    .replace(/^.*-panel-/, '');
                if (id && window.history?.replaceState) {
                    const u = new URL(location.href);
                    u.searchParams.set(param, id);
                    history.replaceState(null, '', u);
                }
                // Keep the page breadcrumb in step with the active tab.
                const crumb = document.querySelector('.pg-bar__crumb');
                const label = this.tabs[index].querySelector('.ezt__tab-label');
                if (crumb && label) crumb.textContent = label.textContent.trim();
            }

            this.restart();
        }

        next() {
            this.show((this.current + 1) % this.tabs.length);
        }

        /* Update the rotation speed at runtime. ms <= 0 = auto-rotate off
           (progress indicators freeze at 0; tabs switch on click only). */
        setInterval(ms) {
            this.interval = ms;
            this.root.dataset.eztInterval = String(ms);
            this.root.style.setProperty('--ezt-interval', ms + 'ms');
            this.root.classList.toggle('ezt--timer-on', ms > 0);
            if (!ms) {
                this.root.classList.remove('is-playing');
                this.allFills().forEach((bar) => {
                    bar.style.animation = 'none';
                    bar.style.width = '0';
                    bar.style.animation = '';
                });
            }
            this.restart();
        }

        start() {
            if (this.timer || this.paused || !this.interval) return;
            this.root.classList.add('is-playing');
            this.restartProgressBar();
            this.timer = window.setTimeout(() => {
                this.timer = null;
                this.next();
            }, this.interval);
        }

        restart() {
            this.stop();
            this.start();
        }

        stop() {
            if (this.timer) {
                window.clearTimeout(this.timer);
                this.timer = null;
            }
        }

        pause() {
            this.paused = true;
            this.stop();
            // Freeze computed widths so every visible fill pauses in place.
            this.activeFills().forEach((bar) => {
                const w = getComputedStyle(bar).width;
                bar.style.animation = 'none';
                bar.style.width = w;
            });
            this.root.classList.remove('is-playing');
        }

        resume() {
            this.paused = false;
            this.allFills().forEach((bar) => { bar.style.width = ''; });
            this.start();
        }

        allFills() {
            return [...this.root.querySelectorAll('.ezt__progress-bar, .ezt__timer-fill')]
                .filter((f) => f.closest('[data-ezt]') === this.root);
        }

        /* Fills that should visibly tick for the current timer placement. */
        activeFills() {
            const mode = this.root.dataset.eztTimer || 'tab';
            if (mode === 'tab') {
                const bar = this.tabs[this.current]?.querySelector('.ezt__progress-bar');
                return bar ? [bar] : [];
            }
            const sel = {
                'panel-top': '.ezt__timer--top',
                'panel-bottom': '.ezt__timer--bottom',
                'overlay': '.ezt__timer--overlay',
            }[mode];
            const el = sel ? this.root.querySelector(sel + ' .ezt__timer-fill') : null;
            return el ? [el] : [];
        }

        restartProgressBar() {
            this.allFills().forEach((bar) => {
                bar.style.animation = 'none';
                bar.style.width = '';
                void bar.offsetWidth; // reflow → animation restarts via .is-playing
                bar.style.animation = '';
            });
        }
    }

    /* Settings bar: [data-ezt-style-opt] and [data-ezt-timer-opt] buttons
       immediately before a [data-ezt] component drive its data attributes. */
    function bindSettings() {
        document.querySelectorAll('.ezt-settings').forEach((bar) => {
            const component = bar.nextElementSibling?.matches?.('[data-ezt]')
                ? bar.nextElementSibling
                : document.querySelector('[data-ezt]');
            if (!component) return;

            const wire = (attr, selector, prop) => {
                bar.querySelectorAll(selector).forEach((btn) => {
                    btn.addEventListener('click', () => {
                        component.setAttribute(attr, btn.dataset[prop]);
                        bar.querySelectorAll(selector).forEach((b) => {
                            b.classList.toggle('is-active', b === btn);
                            b.setAttribute('aria-pressed', String(b === btn));
                        });
                        if (component._ezt && !component._ezt.paused) {
                            component._ezt.restart();
                        }
                    });
                });
            };

            wire('data-ezt-style', '[data-ezt-style-opt]', 'eztStyleOpt');
            wire('data-ezt-timer', '[data-ezt-timer-opt]', 'eztTimerOpt');
            wire('data-ezt-anim', '[data-ezt-anim-opt]', 'eztAnimOpt');

            // Speed group needs the runtime method, not just the attribute.
            bar.querySelectorAll('[data-ezt-interval-opt]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const ms = parseInt(btn.dataset.eztIntervalOpt, 10);
                    if (component._ezt) component._ezt.setInterval(ms);
                    else component.dataset.eztInterval = String(ms);
                    bar.querySelectorAll('[data-ezt-interval-opt]').forEach((b) => {
                        b.classList.toggle('is-active', b === btn);
                        b.setAttribute('aria-pressed', String(b === btn));
                    });
                });
            });
        });
    }

    function init() {
        document.querySelectorAll('[data-ezt]').forEach((root) => new EztTabs(root));
        bindSettings();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
