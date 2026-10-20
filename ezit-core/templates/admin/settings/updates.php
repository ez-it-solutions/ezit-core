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
</div>
