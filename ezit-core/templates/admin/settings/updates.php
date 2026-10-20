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
        <?php
        // A pin is stronger than the ignore list: it also hides the
        // update badge and removes the product from WP's update
        // transient. Pinnable here, on the Marketplace cards/chips, or
        // via EZIT_PIN_PRODUCTS in wp-config.php.
        $pinned = EzIT_Updater::pin_list();
        foreach ($core->products() as $slug => $p) :
            $is_pinned = in_array($slug, $pinned, true);
        ?>
        <div class="ezit-check" style="justify-content:space-between">
            <label class="ezit-check" style="margin:0">
                <input type="checkbox" name="ezit_ignore_forced[]"
                       value="<?php echo esc_attr($slug); ?>"
                       <?php checked($all || in_array($slug, $ignored, true)); ?>
                       <?php disabled($all); ?>>
                <span>
                    <?php echo esc_html($p['name'] ?? $slug); ?>
                    <span class="ezit-meta">(<?php echo esc_html($slug); ?> · v<?php echo esc_html($p['version'] ?: '?'); ?>)</span>
                    <?php if ($is_pinned) : ?>
                        <span class="ezit-badge ezit-badge--pinned"
                              title="<?php esc_attr_e('Updates paused at this version — automatic and forced installs skip this product until you resume them', 'ezit-core'); ?>">
                            <?php esc_html_e('Pinned', 'ezit-core'); ?>
                        </span>
                    <?php endif; ?>
                </span>
            </label>
            <?php if ($is_pinned) : ?>
                <a class="ezit-pin-pill is-resume" href="<?php echo esc_url(wp_nonce_url(
                    admin_url('admin-post.php?action=ezit_unpin&slug=' . $slug . '&from=settings'),
                    'ezit_unpin', 'ezit_unpin_nonce')); ?>"
                    title="<?php esc_attr_e('Resume automatic and forced updates', 'ezit-core'); ?>">
                    <?php esc_html_e('Resume updates', 'ezit-core'); ?>
                </a>
            <?php else : ?>
                <a class="ezit-pin-pill" href="<?php echo esc_url(wp_nonce_url(
                    admin_url('admin-post.php?action=ezit_pin&slug=' . $slug . '&from=settings'),
                    'ezit_pin', 'ezit_pin_nonce')); ?>"
                    data-confirm-title="<?php esc_attr_e('Pin updates?', 'ezit-core'); ?>"
                    data-confirm="<?php esc_attr_e('Automatic and forced updates pause at this version until you resume them.', 'ezit-core'); ?>"
                    data-confirm-ok="<?php esc_attr_e('Pin', 'ezit-core'); ?>"
                    title="<?php esc_attr_e('Hold this version — automatic and forced updates pause until you resume them', 'ezit-core'); ?>">
                    <?php esc_html_e('Pin updates', 'ezit-core'); ?>
                </a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <span class="hint"><?php
            esc_html_e('Ticked products keep forced updates visible but never auto-apply them. Pinning goes further — a pinned product skips every automatic update, the update badge, and the WP update transient until you resume it. To pin before files are even swapped (debug installs), define EZIT_PIN_PRODUCTS in wp-config.php.', 'ezit-core'); ?></span>
    </div>
</div>
