<?php
/**
 * Ez IT admin shell — port of tabs/plugin.html page chrome.
 * Top bar, hero, tabs+sidebar grid, quick links, footer.
 *
 * Vars in scope (set by EzIT_Admin::page_dashboard):
 *   $tabs   — id => [label, vars, template, badge?]
 *   $active — active tab id
 *   $core   — EzIT_Core
 *   $stats  — ['products'=>n,'licensed'=>n,'unlicensed'=>n]
 *   $guide  — list of ['label'=>…,'done'=>bool] for the Site Guide card
 *   $flash  — pre-built notice HTML (empty string when none)
 */
if (! defined('ABSPATH')) {
    exit;
}

$guide_done  = count(array_filter($guide, static fn ($g) => $g['done']));
$guide_total = count($guide);
$guide_pct   = $guide_total ? (int) round($guide_done / $guide_total * 100) : 0;

// Flush-merge edge for the ACTIVE panel — must exist in the HTML or the
// panel paints a rounded corner where the tab bridge expects a square
// one (white notch before JS runs). Top-dir: first tab => left, last => right.
$tab_keys   = array_keys($tabs);
$active_idx = array_search($active, $tab_keys, true);
$edge       = $active_idx === 0 ? 'left'
            : ($active_idx === count($tab_keys) - 1 ? 'right' : '');

// Loading layer under the panels — colored like the ACTIVE panel so the
// card never shows dead white between first paint and panel content.
$panels_style = '';
if (preg_match('/--panel-bg:\s*([^;]+)/', $tabs[$active]['vars'], $m)) {
    $panels_style .= '--panels-bg:' . trim($m[1]) . ';';
}
if (preg_match('/--panel-muted:\s*([^;]+)/', $tabs[$active]['vars'], $m)) {
    $panels_style .= '--loading-fg:' . trim($m[1]) . ';';
}
?>
<div class="ezit-pg">

    <!-- ── Top app bar ──────────────────────────────────────────── -->
    <header class="pg-bar">
        <a class="pg-bar__brand" href="<?php echo esc_url(admin_url('admin.php?page=ezit-core')); ?>">
            <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M13.4 2 4.6 13.4h6.2L9.4 22 19.4 9.6h-6.2z"/></svg>
            <?php esc_html_e('Ez IT Core', 'ezit-core'); ?>
        </a>
        <span class="pg-bar__crumb"><?php echo esc_html($tabs[$active]['label']); ?></span>
        <a class="pg-bar__site" href="<?php echo esc_url(home_url('/')); ?>" target="_blank" rel="noopener">
            <?php esc_html_e('View site', 'ezit-core'); ?> &#8599;
        </a>
    </header>

    <!-- ── Hero: title + search ─────────────────────────────────── -->
    <section class="pg-hero">
        <div class="pg-hero__inner">
            <div class="pg-hero__eyebrow"><?php esc_html_e('Ez IT Solutions · Plugin Dashboard', 'ezit-core'); ?></div>
            <h1><?php esc_html_e('How can we help?', 'ezit-core'); ?></h1>
            <p><?php esc_html_e('Overview, licensing, the marketplace and configuration for this site — everything in one place.', 'ezit-core'); ?></p>
            <div class="pg-hero__search">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="search" placeholder="<?php esc_attr_e('Search settings, tools, docs…', 'ezit-core'); ?>" aria-label="<?php esc_attr_e('Search', 'ezit-core'); ?>">
            </div>
        </div>
    </section>

    <main class="pg-main">
        <?php echo $flash; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built/escaped in flash() ?>

        <div class="pg-grid">

            <!-- ── Tab component — same defaults as plugin.html ──── -->
            <div class="ezt ezit-tabs" data-ezt data-ezt-dir="top" data-ezt-interval="0"
                 data-ezt-style="mixed" data-ezt-timer="none" data-ezt-anim="pull"
                 data-ezt-url-param="tab">
                <ul class="ezt__tabs" role="tablist" aria-orientation="horizontal">
                    <?php foreach ($tabs as $id => $t) : ?>
                    <li style="<?php echo esc_attr(preg_replace('/--panel-(bg|fg|badge-bg|badge-fg|progress)/', '--tab-$1', $t['vars'])); ?>">
                        <button class="ezt__tab" role="tab" id="ezit-tab-<?php echo esc_attr($id); ?>"
                                aria-controls="ezit-panel-<?php echo esc_attr($id); ?>"
                                aria-selected="<?php echo $id === $active ? 'true' : 'false'; ?>"
                                <?php echo $id === $active ? '' : 'tabindex="-1"'; ?> data-ezt-tab>
                            <span class=<?php echo $panels_style !== '' ? ' style="' . esc_attr($panels_style) . '"' : ''; ?>>
                    <div class="ezt__loading" aria-hidden="true">
                        <span class="ezt__loading-ring"></span>
                        <span class="ezt__loading-text"><?php esc_html_e('Loading…', 'ezit-core'); ?></span>
                    </div"ezt__tab-label"><?php echo esc_html($t['label']); ?></span>
                            <?php if (! empty($t['badge'])) : ?>
                            <span class="ezt__badge"><?php echo esc_html($t['badge']); ?></span>
                            <?php endif; ?>
                            <span class="ezt__progress" aria-hidden="true"><span class="ezt__progress-bar"></span></span>
                        </button>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <div class="ezt__panels">
                    <div class="ezt__timer ezt__timer--top" aria-hidden="true"><span class="ezt__timer-fill"></span></div>

                    <?php foreach ($tabs as $id => $t) : ?>
                    <section class="ezt__panel<?php echo $id === $active ? ' ezt__panel--active' : ''; ?>"
                             role="tabpanel" id="ezit-panel-<?php echo esc_attr($id); ?>"
                             aria-labelledby="ezit-tab-<?php echo esc_attr($id); ?>"
                             <?php echo $id === $active ? '' : 'hidden'; ?> data-ezt-panel
                             <?php echo $id === $active && $edge !== '' ? 'data-ezt-edge="' . esc_attr($edge) . '"' : ''; ?>
                             style="<?php echo esc_attr($t['vars']); ?>">
                        <div class="ezt__panel-inner" style="max-width:none;width:100%">
                            <?php include $t['template']; ?>
                        </div>
                    </section>
                    <?php endforeach; ?>

                    <div class="ezt__timer ezt__timer--bottom" aria-hidden="true"><span class="ezt__timer-fill"></span></div>
                </div>
                <div class="ezt__timer ezt__timer--overlay" aria-hidden="true"><span class="ezt__timer-fill"></span></div>
            </div>

            <!-- ── Sidebar ───────────────────────────────────────── -->
            <aside>
                <div class="pg-card pg-guide">
                    <h3><?php esc_html_e('Site Guide', 'ezit-core'); ?></h3>
                    <div class="pg-guide__meta">
                        <span class="pg-guide__bar"><i style="width:<?php echo (int) $guide_pct; ?>%"></i></span>
                        <span><?php echo esc_html("{$guide_done}/{$guide_total}"); ?></span>
                    </div>
                    <ul>
                        <?php foreach ($guide as $g) : ?>
                        <li<?php echo $g['done'] ? ' class="is-done"' : ''; ?>><?php echo esc_html($g['label']); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="pg-card pg-support">
                    <h3><?php esc_html_e('Need Support?', 'ezit-core'); ?></h3>
                    <p><?php esc_html_e('Can\'t find the answer? Open a ticket — the Ez IT team replies within one business day.', 'ezit-core'); ?></p>
                    <a href="https://ez-it-solutions.com/support" target="_blank" rel="noopener"><?php esc_html_e('Contact Support', 'ezit-core'); ?></a>
                </div>
            </aside>

        </div><!-- /.pg-grid -->

        <!-- ── Quick links ───────────────────────────────────────── -->
        <div class="pg-card pg-quick">
            <h3><?php esc_html_e('Quick links', 'ezit-core'); ?></h3>
            <ul>
                <li><a href="<?php echo esc_url(admin_url('post-new.php?post_type=page')); ?>"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M12 18v-6M9 15h6"/></svg><?php esc_html_e('Add new page', 'ezit-core'); ?></a></li>
                <li><a href="<?php echo esc_url(admin_url('post-new.php')); ?>"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19l7-7 3 3-7 7-3-3z"/><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/></svg><?php esc_html_e('Add new post', 'ezit-core'); ?></a></li>
                <li><a href="<?php echo esc_url(admin_url('plugins.php')); ?>"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.6 13.41 13.41 20.6a2 2 0 0 1-2.82 0L2 12V2h10l8.59 8.59a2 2 0 0 0 0-2.82z"/><circle cx="7" cy="7" r="1.5"/></svg><?php esc_html_e('Manage plugins', 'ezit-core'); ?></a></li>
                <li><a href="<?php echo esc_url(admin_url('themes.php')); ?>"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 12h8M12 8v8"/></svg><?php esc_html_e('Site style', 'ezit-core'); ?></a></li>
                <li><a href="<?php echo esc_url(admin_url('upload.php')); ?>"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="14" rx="2"/><circle cx="8.5" cy="9" r="1.5"/><path d="m21 15-5-5L5 21"/></svg><?php esc_html_e('Media library', 'ezit-core'); ?></a></li>
                <li><a href="<?php echo esc_url(admin_url('options-general.php')); ?>"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="14" rx="2"/><path d="M3 9h18"/></svg><?php esc_html_e('Site settings', 'ezit-core'); ?></a></li>
            </ul>
        </div>
    </main>

    <!-- ── Footer ───────────────────────────────────────────────── -->
    <footer class="pg-foot">
        <div class="pg-foot__inner">
            <span class="pg-foot__brand">
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M13.4 2 4.6 13.4h6.2L9.4 22 19.4 9.6h-6.2z"/></svg>
                <?php esc_html_e('Ez IT Solutions', 'ezit-core'); ?>
            </span>
            <nav class="pg-foot__links">
                <a href="https://ez-it-solutions.com" target="_blank" rel="noopener"><?php esc_html_e('Documentation', 'ezit-core'); ?></a>
                <a href="https://ez-it-solutions.com" target="_blank" rel="noopener"><?php esc_html_e('Changelog', 'ezit-core'); ?></a>
                <a href="https://ez-it-solutions.com/support" target="_blank" rel="noopener"><?php esc_html_e('Support', 'ezit-core'); ?></a>
                <a href="https://ez-it-solutions.com" target="_blank" rel="noopener"><?php esc_html_e('ez-it-solutions.com', 'ezit-core'); ?></a>
            </nav>
        </div>
        <div class="pg-foot__base">
            <div>
                <?php
                printf(
                    /* translators: %s: Ez IT Core version */
                    esc_html__('Ez IT Core v%s · Thank you for running Ez IT products.', 'ezit-core'),
                    esc_html(EZIT_CORE_VERSION)
                );
                ?>
            </div>
        </div>
    </footer>

</div><!-- /.ezit-pg -->
