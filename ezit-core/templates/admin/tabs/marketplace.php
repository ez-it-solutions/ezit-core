<?php
/**
 * Marketplace tab — panel hero + installed products + remote catalog.
 * Vars in scope: $core, $stats
 */
if (! defined('ABSPATH')) {
    exit;
}
// One marketplace instance feeds the header stamp AND the grid —
// handle_refresh() + catalog() here make the "Fetched N ago" line
// reflect THIS page load's data (idempotent inside render()).
$ezit_market  = new EzIT_Marketplace($core);
$ezit_fresh   = $ezit_market->handle_refresh();
$ezit_market->catalog($ezit_fresh);
?>
<div class="ezt__brand" data-ezt-anim>
    <span class="ezt__brand-mark" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="#76b900" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 1.5-6h15L21 9"/><path d="M3 9v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9"/><path d="M3 9h18"/><path d="M9 13h6"/></svg>
    </span>
    <span class="ezt__brand-name"><?php esc_html_e('Marketplace', 'ezit-core'); ?></span>
</div>
<div class="ezt__head-row" data-ezt-anim>
    <h2 class="ezt__heading"><?php esc_html_e('Ez IT Marketplace', 'ezit-core'); ?></h2>
    <div class="ezt__head-side">
        <a class="ezit-btn ezt__refresh" href="<?php echo esc_url($ezit_market->refresh_url()); ?>">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg>
            <?php esc_html_e('Refresh', 'ezit-core'); ?>
        </a>
        <span class="ezit-catalog-at">
            <?php if ($ezit_market->catalog_fetched_at() > 0) : ?>
                <span class="ezit-catalog-at__age"
                      data-ts="<?php echo (int) $ezit_market->catalog_fetched_at(); ?>"
                      data-label="<?php esc_attr_e('Fetched', 'ezit-core'); ?>"
                      title="<?php echo esc_attr(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $ezit_market->catalog_fetched_at())); ?>">
                    <?php printf(
                        /* translators: %s: human-readable time difference */
                        esc_html__('Fetched %s ago', 'ezit-core'),
                        esc_html(human_time_diff($ezit_market->catalog_fetched_at(), time()))
                    ); ?>
                </span>
            <?php else : ?>
                <?php esc_html_e('Never fetched', 'ezit-core'); ?>
            <?php endif; ?>
            <?php if ($ezit_market->is_stale()) : ?>
                <br><?php esc_html_e('showing the cached copy (server unreachable)', 'ezit-core'); ?>
            <?php endif; ?>
        </span>
    </div>
</div>
<p class="ezt__text" data-ezt-anim>
    <?php esc_html_e('Every Ez IT theme, plugin and product — installed on this site and available in the catalog.', 'ezit-core'); ?>
</p>

<?php $ezit_market->render(); ?>
