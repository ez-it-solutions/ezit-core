<?php
/**
 * Settings tab — pg-sethead toolbar + license server config.
 * Vars in scope: $core, $stats
 */
if (! defined('ABSPATH')) {
    exit;
}
?>
<form id="ezit-settings-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('ezit_settings', 'ezit_settings_nonce'); ?>
    <input type="hidden" name="action" value="ezit_settings">
    <input type="hidden" name="section" value="<?php echo esc_attr(sanitize_key($_GET['section'] ?? '')); /* phpcs:ignore */ ?>">

    <div class="pg-sethead" data-ezt-anim>
        <span class="pg-sethead__chip" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="#5a8f00" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-1.8-.3 1.6 1.6 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.2a1.6 1.6 0 0 0-1-1.5 1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1.1a1.6 1.6 0 0 0 .3-1.8 1.6 1.6 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.2a1.6 1.6 0 0 0 1.5-1 1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3h0a1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.2a1.6 1.6 0 0 0 1 1.5h0a1.6 1.6 0 0 0 1.8-.3l.1.1a2 2 0 1 1 2.8 2.8l.1.1a1.6 1.6 0 0 0 .3 1.8v0a1.6 1.6 0 0 0 1.5 1h.2a2 2 0 1 1 0 4h-.2a1.6 1.6 0 0 0-1.5 1z"/></svg>
        </span>
        <div class="pg-sethead__text">
            <span class="pg-sethead__eyebrow"><?php esc_html_e('Plugin Settings', 'ezit-core'); ?></span>
            <h2><?php esc_html_e('Settings', 'ezit-core'); ?></h2>
            <p><?php esc_html_e('How Ez IT Core talks to the outside world.', 'ezit-core'); ?></p>
        </div>
        <div class="pg-sethead__side">
            <button type="submit" class="pg-sethead__btn"><?php esc_html_e('Save all', 'ezit-core'); ?></button>
        </div>
    </div>

    <?php
    // Settings sub-navigation — the same EzIT Tabs component as the top
    // bar, in its default side-column direction. data-ezt-interval="0"
    // disables auto-rotate; ?section= deep-links each panel.
    $sections = [
        'server' => __('License server', 'ezit-core'),
        'gate'   => __('Site gate', 'ezit-core'),
        'page'   => __('Holding page', 'ezit-core'),
        'login'  => __('Login page', 'ezit-core'),
        'alerts' => __('Alerts & banners', 'ezit-core'),
        'studio' => __('Site Studio', 'ezit-core'),
        'email-log' => __('Email log', 'ezit-core'),
    ];
    $section = sanitize_key($_GET['section'] ?? 'server'); // phpcs:ignore
    if (! isset($sections[$section])) {
        $section = 'server';
    }
    // Light panel theme for the nested component.
    $pvars = '--panel-bg:#eef0f2; --panel-fg:#14151f; --panel-muted:#5c5f6b;'
           . ' --panel-btn-bg:#14151f; --panel-btn-fg:#ffffff;'
           . ' --panel-deco:rgba(20,21,31,.05); --panel-progress:#76b900;';
    ?>
    <div class="ezt ezt-subnav" data-ezt data-ezt-fill="off" data-ezt-interval="0"
         data-ezt-timer="none" data-ezt-anim="pull" data-ezt-url-param="section">
        <ul class="ezt__tabs" role="tablist" aria-orientation="vertical">
            <?php foreach ($sections as $id => $label) : ?>
            <li>
                <button type="button" class="ezt__tab" role="tab" id="ezit-sub-<?php echo esc_attr($id); ?>"
                        aria-controls="ezit-panel-<?php echo esc_attr($id); ?>"
                        aria-selected="<?php echo $id === $section ? 'true' : 'false'; ?>"
                        <?php echo $id === $section ? '' : 'tabindex="-1"'; ?> data-ezt-tab>
                    <span class="ezt__tab-label"><?php echo esc_html($label); ?></span>
                    <span class="ezt__progress" aria-hidden="true"><span class="ezt__progress-bar"></span></span>
                </button>
            </li>
            <?php endforeach; ?>
        </ul>
        <div class="ezt__panels">
            <?php foreach ($sections as $id => $label) : ?>
            <section class="ezt__panel<?php echo $id === $section ? ' ezt__panel--active' : ''; ?>"
                     role="tabpanel" id="ezit-panel-<?php echo esc_attr($id); ?>"
                     aria-labelledby="ezit-sub-<?php echo esc_attr($id); ?>"
                     <?php echo $id === $section ? '' : 'hidden'; ?> data-ezt-panel
                     style="<?php echo esc_attr($pvars); ?>">
                <div class="ezt__panel-inner">
                    <?php include EZIT_CORE_PATH . 'templates/admin/settings/' . $id . '.php'; ?>
                </div>
            </section>
            <?php endforeach; ?>
        </div>
    </div>
</form>

<script>
// The hidden `section` field is rendered server-side from ?section= —
// keep it in step with client-side subnav switches so the save handler
// knows which section's data to persist.
document.addEventListener('ezt:tab-change', (e) => {
    const param = e.target.dataset?.eztUrlParam;
    if (!param) return;
    const input = e.target.closest('form')?.querySelector(`input[name="${param}"]`);
    if (input && e.detail.id) input.value = e.detail.id;

    // Email log swaps client-side — pull fresh rows so the table is
    // never a stale render of whenever the page first loaded.
    if (e.detail.id === 'email-log') refreshMailLog();
});

async function refreshMailLog() {
    const tbody = document.getElementById('ezit-maillog-rows');
    if (!tbody || typeof ajaxurl === 'undefined') return;
    try {
        const res  = await fetch(ajaxurl + '?action=ezit_maillog');
        const data = await res.json();
        const rows = data?.data?.rows ?? [];
        const wrap  = document.getElementById('ezit-maillog-wrap');
        const empty = document.getElementById('ezit-maillog-empty');
        tbody.innerHTML = '';
        const td = (txt, css = '') => {
            const d = document.createElement('td');
            d.textContent = txt;
            if (css) d.style.cssText = css;
            return d;
        };
        rows.forEach((r, i) => {
            const tr = document.createElement('tr');
            tr.className = 'ezit-maillog-row' + (r.has_body ? ' has-body' : '');
            tr.dataset.mlogIdx = i;
            tr.appendChild(td(r.time || '', 'white-space:nowrap'));
            const badge = document.createElement('span');
            badge.className = 'ezit-badge ' + (r.status === 'sent' ? 'is-active' : 'is-inactive');
            badge.textContent = r.status === 'sent' ? 'Sent' : 'Failed';
            const st = document.createElement('td');
            st.appendChild(badge);
            tr.appendChild(st);
            tr.appendChild(td(r.to || ''));
            const subj = td(r.subject || '');
            subj.className = 'ezit-maillog-subj';
            if (r.has_body) {
                const arrow = document.createElement('span');
                arrow.className = 'ezit-meta';
                arrow.textContent = ' \u2192';
                subj.appendChild(arrow);
            }
            tr.appendChild(subj);
            tr.appendChild(td(r.error || '', 'color:#b3483a;font-size:12.5px'));
            tbody.appendChild(tr);
        });
        if (wrap)  wrap.style.display  = rows.length ? '' : 'none';
        if (empty) empty.style.display = rows.length ? 'none' : '';
    } catch (err) { /* leave the server-rendered rows in place */ }
}

// Click a row with a stored body → preview modal. The iframe loads the
// mail as its own document, so the email's styles render untouched.
const mvModal = document.getElementById('ezit-maillog-modal');
function openMailView(tr) {
    const idx = tr.dataset.mlogIdx;
    if (!mvModal || idx === undefined || !tr.classList.contains('has-body')) return;
    const url = 'admin-post.php?action=ezit_maillog_view&i=' + encodeURIComponent(idx);
    document.getElementById('ezit-mailview-frame').src = url;
    document.getElementById('ezit-mailview-open').href = url;
    document.getElementById('ezit-mailview-title').textContent =
        tr.querySelector('.ezit-maillog-subj')?.childNodes[0]?.textContent?.trim() || '';
    mvModal.hidden = false;
    document.body.style.overflow = 'hidden';
}
function closeMailView() {
    if (!mvModal) return;
    mvModal.hidden = true;
    document.getElementById('ezit-mailview-frame').src = 'about:blank';
    document.body.style.overflow = '';
}
document.getElementById('ezit-maillog-rows')?.addEventListener('click', (e) => {
    const tr = e.target.closest('tr.ezit-maillog-row');
    if (tr) openMailView(tr);
});
document.getElementById('ezit-mailview-close')?.addEventListener('click', closeMailView);
mvModal?.addEventListener('click', (e) => { if (e.target === mvModal) closeMailView(); });
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeMailView(); });
</script>
