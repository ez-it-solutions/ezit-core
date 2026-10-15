<?php
/**
 * Licenses tab — panel hero + one activation card per product.
 * Vars in scope: $core, $stats
 */
if (! defined('ABSPATH')) {
    exit;
}

$licensing = $core->licensing();
$products  = $core->products();
?>
<div class="ezt__brand" data-ezt-anim>
    <span class="ezt__brand-mark" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="#76b900" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="15" r="4"/><path d="M10.9 12.1 20 3m-3 3 3 3"/></svg>
    </span>
    <span class="ezt__brand-name"><?php esc_html_e('Licenses', 'ezit-core'); ?></span>
</div>
<h2 class="ezt__heading" data-ezt-anim><?php esc_html_e('License & Activation', 'ezit-core'); ?></h2>
<p class="ezt__text" data-ezt-anim>
    <?php
    printf(
        /* translators: 1: licensed count, 2: total products */
        esc_html__('%1$d of %2$d registered products licensed. Activation and renewals are checked against the Ez IT license server.', 'ezit-core'),
        (int) $stats['licensed'],
        (int) $stats['products']
    );
    ?>
</p>

<?php if (! $products) : ?>
<div class="ezit-card" data-ezt-anim><div class="ezit-empty">
    <svg viewBox="0 0 24 24" width="42" height="42" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="7.5" cy="15.5" r="4.5"/><path d="M10.5 12.5 21 2"/><path d="m16 7 3 3"/></svg>
    <p><?php esc_html_e('No products registered yet — nothing to license.', 'ezit-core'); ?></p>
</div></div>
<?php endif; ?>

<div class="ezit-cards">
<?php foreach ($products as $slug => $p) :
    if ($p['product_id'] === '') {
        continue;
    }
    $is_active = $licensing->is_active($slug);
?>
    <div class="ezit-card ezit-license" data-ezt-anim>
        <div class="ezit-license__head">
            <h3><?php echo esc_html($p['name']); ?></h3>
            <span class="ezit-badge <?php echo $is_active ? 'is-active' : 'is-inactive'; ?>">
                <?php echo $is_active ? esc_html__('Active', 'ezit-core') : esc_html__('Inactive', 'ezit-core'); ?>
            </span>
        </div>
        <p class="ezit-meta"><?php echo esc_html(($p['type'] ?? 'theme') . ' · v' . ($p['version'] ?: '—')); ?></p>

        <?php if ($is_active) : ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('ezit_license', 'ezit_license_nonce'); ?>
            <input type="hidden" name="action" value="ezit_deactivate">
            <input type="hidden" name="slug" value="<?php echo esc_attr($slug); ?>">
            <button class="ezit-btn ezit-btn--ghost"><?php esc_html_e('Deactivate', 'ezit-core'); ?></button>
        </form>
        <?php else :
            $lic     = $licensing->license($slug) ?: [];
            $revoked = ! empty($lic['revoked']);
        ?>
        <?php if ($revoked) : ?>
        <div class="ezit-notice">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4m0 4h.01"/></svg>
            <p><?php esc_html_e('This activation was disabled on the license server. Enter a different license code to re-activate, or contact Ez IT Solutions.', 'ezit-core'); ?></p>
        </div>
        <?php endif; ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"<?php echo $revoked ? ' data-requires-change' : ''; ?>>
            <?php wp_nonce_field('ezit_license', 'ezit_license_nonce'); ?>
            <input type="hidden" name="action" value="ezit_activate">
            <input type="hidden" name="slug" value="<?php echo esc_attr($slug); ?>">
            <div class="ezit-field">
                <label for="ezit-code-<?php echo esc_attr($slug); ?>"><?php esc_html_e('License code', 'ezit-core'); ?></label>
                <input id="ezit-code-<?php echo esc_attr($slug); ?>" type="text" name="license_code"
                       value="<?php echo esc_attr($p['license_code'] ?? ''); ?>" required>
            </div>
            <div class="ezit-field">
                <label for="ezit-name-<?php echo esc_attr($slug); ?>"><?php esc_html_e('License name (optional)', 'ezit-core'); ?></label>
                <input id="ezit-name-<?php echo esc_attr($slug); ?>" type="text" name="client_name">
                <span class="hint"><?php esc_html_e('Label shown as "Licensed to" on the license server — e.g. the client or site name.', 'ezit-core'); ?></span>
            </div>
            <button class="ezit-btn"<?php echo $revoked ? ' disabled' : ''; ?>><?php esc_html_e('Activate license', 'ezit-core'); ?></button>
        </form>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
</div>

<?php foreach ($products as $slug => $p) :
    if ($p['product_id'] !== '') {
        continue;
    }
?>
<div class="ezit-card" data-ezt-anim><p class="ezit-muted">
    <?php printf(
        /* translators: %s: product name */
        esc_html__('"%s" has no product_id configured — add one in its register_product() call to enable licensing.', 'ezit-core'),
        esc_html($p['name'])
    ); ?>
</p></div>
<?php endforeach; ?>

<div class="ezt__footer" data-ezt-anim>
    <span class="ezt__price"><?php esc_html_e('Keys and seats live in your ', 'ezit-core'); ?><strong>Ez IT</strong> <?php esc_html_e('account', 'ezit-core'); ?></span>
</div>
