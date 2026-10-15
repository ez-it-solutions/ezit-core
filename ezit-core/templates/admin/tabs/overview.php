<?php
/**
 * Dashboard tab — status hero + registered products table.
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
        <svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="#76b900" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="5" rx="1.5"/><rect x="13" y="10" width="8" height="11" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/></svg>
    </span>
    <span class="ezt__brand-name"><?php esc_html_e('Ez IT Solutions', 'ezit-core'); ?></span>
</div>
<h2 class="ezt__heading" data-ezt-anim>
    <?php echo $stats['unlicensed'] > 0
        ? esc_html(sprintf(
            /* translators: %d: number of unlicensed products */
            _n('%d product needs a license', '%d products need a license', $stats['unlicensed'], 'ezit-core'),
            $stats['unlicensed']
        ))
        : esc_html__('Everything\'s running smoothly', 'ezit-core'); ?>
</h2>
<p class="ezt__text" data-ezt-anim>
    <?php
    printf(
        /* translators: 1: product count, 2: licensed count, 3: license server host */
        esc_html__('%1$d product(s) registered · %2$d licensed · Server: %3$s', 'ezit-core'),
        (int) $stats['products'],
        (int) $stats['licensed'],
        esc_html(wp_parse_url($core->api_url(), PHP_URL_HOST) ?: $core->api_url())
    );
    ?>
</p>
<div class="ezt__footer" data-ezt-anim>
    <a href="<?php echo esc_url(admin_url('admin.php?page=ezit-core&tab=licenses')); ?>" class="ezt__cta"><?php esc_html_e('Manage Licenses', 'ezit-core'); ?></a>
    <span class="ezt__price"><?php printf(esc_html__('Core %s', 'ezit-core'), '<strong>v' . esc_html(EZIT_CORE_VERSION) . '</strong>'); ?></span>
</div>

<?php if ($products) : ?>
<div class="ezit-card" data-ezt-anim>
    <div class="ezit-card__head">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 9h20"/><path d="M7 14h4"/></svg>
        <h3><?php esc_html_e('Registered products', 'ezit-core'); ?></h3>
    </div>
    <table class="ezit-table">
        <thead><tr>
            <th><?php esc_html_e('Product', 'ezit-core'); ?></th>
            <th><?php esc_html_e('Type', 'ezit-core'); ?></th>
            <th><?php esc_html_e('Version', 'ezit-core'); ?></th>
            <th><?php esc_html_e('License', 'ezit-core'); ?></th>
            <th><?php esc_html_e('Licensed to', 'ezit-core'); ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($products as $slug => $p) :
            $lic     = $licensing->license($slug);
            $payload = $lic && ! empty($lic['license_file'])
                ? EzIT_Licensing::cert_payload($lic['license_file'])
                : null;
            $active  = $licensing->is_active($slug);
        ?>
        <tr>
            <td><strong><?php echo esc_html($p['name']); ?></strong></td>
            <td><?php echo esc_html($p['type'] ?? 'theme'); ?></td>
            <td><?php echo esc_html($p['version'] ?: '—'); ?></td>
            <td><span class="ezit-badge <?php echo $active ? 'is-active' : 'is-inactive'; ?>">
                <?php echo $active ? esc_html__('Active', 'ezit-core') : esc_html__('Inactive', 'ezit-core'); ?>
            </span></td>
            <td><?php echo esc_html($payload['client_name'] ?? '—'); ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
