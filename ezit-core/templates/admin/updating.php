<?php
/**
 * Full-screen forced-update page — no tabs, no dashboard chrome.
 * intercept_forced() routes every Ez IT admin page here while a
 * forced release is armed; this page calls ezit_forced_apply via
 * AJAX and walks the upgrader's own feedback into the checklist.
 *
 * Vars in scope: $core, $flash, $forced (pending release | null)
 */
if (! defined('ABSPATH')) {
    exit;
}
?>
<div class="ezit-pg ezit-upd">

    <!-- Minimal brand bar — deliberately none of the tab chrome. -->
    <header class="pg-bar">
        <span class="pg-bar__brand">
            <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M13.4 2 4.6 13.4h6.2L9.4 22 19.4 9.6h-6.2z"/></svg>
            <?php esc_html_e('Ez IT Core', 'ezit-core'); ?>
        </span>
        <span class="pg-bar__crumb"><?php esc_html_e('Software update', 'ezit-core'); ?></span>
    </header>

    <main class="ezit-upd__stage">
        <?php echo $flash; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

        <section class="ezit-upd__card">
            <span class="ezit-upd__orb" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/></svg>
            </span>

            <?php if ($forced) : ?>
            <p class="ezit-upd__eyebrow"><?php esc_html_e('Required update', 'ezit-core'); ?></p>
            <h1><?php
                printf(
                    /* translators: %s: product name */
                    esc_html__('Updating %s', 'ezit-core'),
                    esc_html($forced['name'])
                ); ?></h1>
            <p class="ezit-upd__sub"><?php
                printf(
                    /* translators: %s: version number */
                    esc_html__('Version %s is a forced release — keeping this site secure and in sync.', 'ezit-core'),
                    esc_html($forced['version'])
                ); ?></p>

            <ol class="ezit-upd__steps" id="ezit-upd-steps" aria-live="polite">
                <li class="is-run"><?php esc_html_e('Preparing', 'ezit-core'); ?></li>
                <li><?php esc_html_e('Downloading the update package', 'ezit-core'); ?></li>
                <li><?php esc_html_e('Installing the new version', 'ezit-core'); ?></li>
                <li><?php esc_html_e('Finishing up', 'ezit-core'); ?></li>
            </ol>

            <div class="ezit-upd__bar" aria-hidden="true"><span id="ezit-upd-fill"></span></div>
            <p class="ezit-upd__hint" id="ezit-upd-hint"><?php
                esc_html_e('Please keep this page open — this only takes a moment.', 'ezit-core'); ?></p>
            <div class="ezit-upd__actions" id="ezit-upd-actions" hidden>
                <button type="button" class="pg-sethead__btn" id="ezit-upd-retry"><?php
                    esc_html_e('Try again', 'ezit-core'); ?></button>
                <a class="ezit-upd__link" id="ezit-upd-back" href="<?php
                    echo esc_url(admin_url('admin.php?page=ezit-core')); ?>"><?php
                    esc_html_e('Back to dashboard', 'ezit-core'); ?></a>
            </div>

            <?php else : ?>
            <p class="ezit-upd__eyebrow"><?php esc_html_e('Software update', 'ezit-core'); ?></p>
            <h1><?php esc_html_e('You’re all set', 'ezit-core'); ?></h1>
            <p class="ezit-upd__sub"><?php
                esc_html_e('Nothing is waiting to install — every Ez IT product is current.', 'ezit-core'); ?></p>
            <div class="ezit-upd__actions">
                <a class="pg-sethead__btn" href="<?php echo esc_url(admin_url('admin.php?page=ezit-core')); ?>"><?php
                    esc_html_e('Back to dashboard', 'ezit-core'); ?></a>
            </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<?php if ($forced) : ?>
<script>
// Drive the apply call and paint the real step list the upgrader
// reports back. The placeholder checklist animates while the request
// is in flight; the response swaps in actual server-side steps.
(function () {
    var steps = document.getElementById('ezit-upd-steps');
    var fill  = document.getElementById('ezit-upd-fill');
    var hint  = document.getElementById('ezit-upd-hint');
    var acts  = document.getElementById('ezit-upd-actions');
    var retry = document.getElementById('ezit-upd-retry');
    var idx = 0, ticking = null, ran = false;

    function paint() {
        var items = steps.querySelectorAll('li');
        items.forEach(function (li, i) {
            li.classList.toggle('is-done', i < idx);
            li.classList.toggle('is-run', i === idx);
        });
        fill.style.width = Math.min(95, (idx / items.length) * 100) + '%';
    }
    // Gentle advance so the screen never looks frozen mid-request.
    function tick() {
        var n = steps.querySelectorAll('li').length;
        if (idx < n - 1) { idx++; paint(); }
    }
    function finish(ok, messages) {
        clearInterval(ticking);
        if (messages && messages.length) {
            steps.innerHTML = '';
            messages.forEach(function (m) {
                var li = document.createElement('li');
                li.textContent = m;
                li.className = 'is-done';
                steps.appendChild(li);
            });
        } else {
            steps.querySelectorAll('li').forEach(function (li) { li.className = 'is-done'; });
        }
        if (ok) {
            fill.style.width = '100%';
            steps.closest('.ezit-upd__card').classList.add('is-ok');
        } else {
            steps.closest('.ezit-upd__card').classList.add('is-err');
            var lastRun = steps.querySelector('li.is-run');
            if (lastRun) lastRun.className = 'is-bad';
        }
    }
    function run() {
        if (ran) return;
        ran = true;
        acts.hidden = true;
        ticking = setInterval(tick, 1600);
        var body = new URLSearchParams({
            action: 'ezit_forced_apply',
            nonce:  <?php echo wp_json_encode(wp_create_nonce('ezit_forced_apply')); ?>
        });
        fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: body })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                var ok    = !!(res && res.success);
                var data  = (res && res.data) || {};
                finish(ok, data.steps);
                hint.textContent = data.message
                    || (ok ? 'All done.' : 'The update could not be completed.');
                acts.hidden = false;
                if (ok && data.back) {
                    document.getElementById('ezit-upd-back').href = data.back;
                    retry.hidden = true;
                    setTimeout(function () { location.href = data.back; }, 3000);
                } else {
                    retry.hidden = false;
                }
            })
            .catch(function () {
                finish(false);
                hint.textContent = 'Lost contact with the site — check your connection, then try again.';
                acts.hidden = false;
                retry.hidden = false;
            });
    }
    retry.addEventListener('click', function () { ran = false; idx = 0; paint(); run(); });
    paint();
    run();
})();
</script>
<?php endif; ?>
