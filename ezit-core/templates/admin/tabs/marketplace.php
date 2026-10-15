<?php
/**
 * Marketplace tab — panel hero + installed products + remote catalog.
 * Vars in scope: $core, $stats
 */
if (! defined('ABSPATH')) {
    exit;
}
?>
<div class="ezt__brand" data-ezt-anim>
    <span class="ezt__brand-mark" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="#76b900" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 1.5-6h15L21 9"/><path d="M3 9v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9"/><path d="M3 9h18"/><path d="M9 13h6"/></svg>
    </span>
    <span class="ezt__brand-name"><?php esc_html_e('Marketplace', 'ezit-core'); ?></span>
</div>
<h2 class="ezt__heading" data-ezt-anim><?php esc_html_e('Ez IT Marketplace', 'ezit-core'); ?></h2>
<p class="ezt__text" data-ezt-anim>
    <?php esc_html_e('Every Ez IT theme, plugin and product — installed on this site and available in the catalog.', 'ezit-core'); ?>
</p>

<?php (new EzIT_Marketplace($core))->render(); ?>
