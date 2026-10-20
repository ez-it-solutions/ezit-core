<?php
/**
 * Settings section — update preferences.
 * Forced releases come from the license server ("force at" on a
 * published update). This panel is the site's escape hatch: ignore a
 * single product's forced updates, or all of them — regardless of
 * what the marketplace or license server asks for.
 *
 * Vars in scope: $core
 */
if (! defined('ABSPATH')) {
    exit;
}
$ignored = EzIT_Updater::ignore_list();
$all     = in_array('all', $ignored, true);
?>
<div class="ezit-card">
    <div class="ezit-card__head">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/></svg>
        <h3><?php esc_html_e('Updates', 'ezit-core'); ?></h3>
    </div>
    <p class="hint" style="margin-top:0"><?php
        esc_html_e('Forced releases install themselves the next time an admin opens any Ez IT page — no waiting on cron. Anything ticked below is exempt: the update still shows as available, but it never self-installs.', 'ezit-core'); ?></p>
    <div class="ezit-field">
        <label class="ezit-check">
            <input type="checkbox" name="ezit_ignore_forced[]" value="all"
                   <?php checked($all); ?>>
            <strong><?php esc_html_e('Ignore all forced updates', 'ezit-core'); ?></strong>
        </label>
        <span class="hint"><?php
            esc_html_e('Opt this whole site out — every product becomes a normal, manual update.', 'ezit-core'); ?></span>
    </div>
    <div class="ezit-field">
        <label><?php esc_html_e('Per product', 'ezit-core'); ?></label>
        <?php foreach ($core->products() as $slug => $p) : ?>
        <label class="ezit-check">
            <input type="checkbox" name="ezit_ignore_forced[]"
                   value="<?php echo esc_attr($slug); ?>"
                   <?php checked($all || in_array($slug, $ignored, true)); ?>
                   <?php disabled($all); ?>>
            <?php echo esc_html($p['name'] ?? $slug); ?>
            <span class="ezit-meta">(<?php echo esc_html($slug); ?>)</span>
        </label>
        <?php endforeach; ?>
        <span class="hint"><?php
            esc_html_e('Ticked products keep forced updates visible but never auto-apply them.', 'ezit-core'); ?></span>
    </div>
    <?php
    // Version pins — set from the Marketplace card when an admin installs
    // an older release (or via EZIT_PIN_PRODUCTS in wp-config.php). A pin
    // is stronger than the ignore list: it also hides the update badge
    // and removes the product from WP's update transient.
    $pinned = EzIT_Updater::pin_list();
    if ($pinned) :
    ?>
    <div class="ezit-field">
        <label><?php esc_html_e('Pinned versions', 'ezit-core'); ?></label>
        <?php foreach ($pinned as $slug) :
            $p = $core->product($slug); ?>
        <div class="ezit-check" style="justify-content:space-between">
            <span>
                <?php echo esc_html(($p['name'] ?? $slug) . ' — v' . ($p['version'] ?? '?')); ?>
                <span class="ezit-meta">(<?php echo esc_html($slug); ?>)</span>
            </span>
            <a href="<?php echo esc_url(wp_nonce_url(
                admin_url('admin-post.php?action=ezit_unpin&slug=' . $slug . '&from=settings'),
                'ezit_unpin', 'ezit_unpin_nonce')); ?>">
                <?php esc_html_e('Resume updates', 'ezit-core'); ?>
            </a>
        </div>
        <?php endforeach; ?>
        <span class="hint"><?php
            esc_html_e('Pinned products skip every automatic update — forced releases, badges, and the WP update transient — until resumed. To pin before files are even swapped (debug installs), define EZIT_PIN_PRODUCTS in wp-config.php.', 'ezit-core'); ?></span>
    </div>
    <?php endif; ?>
</div>
