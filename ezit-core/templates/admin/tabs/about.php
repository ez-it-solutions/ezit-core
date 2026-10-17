<?php
/**
 * About tab — Ez IT Solutions card + auto-detected product inventory
 * + environment details.
 * Vars in scope: $core, $stats
 */
if (! defined('ABSPATH')) {
    exit;
}

$detected = EzIT_Themes::detected($core);
?>
<div class="ezt__brand" data-ezt-anim>
    <span class="ezt__brand-mark" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="#76b900" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13.4 2 4.6 13.4h6.2L9.4 22 19.4 9.6h-6.2z"/></svg>
    </span>
    <span class="ezt__brand-name"><?php esc_html_e('Ez IT Solutions', 'ezit-core'); ?></span>
</div>
<h2 class="ezt__heading" data-ezt-anim><?php esc_html_e('About Ez IT', 'ezit-core'); ?></h2>
<p class="ezt__text" data-ezt-anim>
    <?php esc_html_e('Local IT, web and managed-services partner — and the team behind this site’s platform.', 'ezit-core'); ?>
</p>
<div class="ezt__footer" data-ezt-anim>
    <a href="https://ez-it-solutions.com" target="_blank" rel="noopener" class="ezt__cta"><?php esc_html_e('ez-it-solutions.com', 'ezit-core'); ?></a>
    <a href="https://ez-it-solutions.com/support" target="_blank" rel="noopener" class="ezit-linkbtn"><?php esc_html_e('Support', 'ezit-core'); ?></a>
    <a href="https://billing.ez-it-solutions.com" target="_blank" rel="noopener" class="ezit-linkbtn"><?php esc_html_e('Billing portal', 'ezit-core'); ?></a>
</div>

<div class="ezit-card" data-ezt-anim>
    <div class="ezit-card__head">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 9h20"/><path d="M7 14h4"/></svg>
        <h3><?php esc_html_e('Detected Ez IT products', 'ezit-core'); ?></h3>
    </div>
    <p class="ezit-meta" style="margin-bottom:14px">
        <?php esc_html_e('Found by scanning installed themes and plugins for Ez IT product markers — including products that aren’t activated or registered yet.', 'ezit-core'); ?>
    </p>
    <table class="ezit-table">
        <thead><tr>
            <th><?php esc_html_e('Product', 'ezit-core'); ?></th>
            <th><?php esc_html_e('Type', 'ezit-core'); ?></th>
            <th><?php esc_html_e('Version', 'ezit-core'); ?></th>
            <th><?php esc_html_e('State', 'ezit-core'); ?></th>
            <th><?php esc_html_e('License', 'ezit-core'); ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($detected as $d) : ?>
        <tr>
            <td><strong><?php echo esc_html($d['name']); ?></strong>
                <?php if ($d['product_id'] !== '') : ?>
                    <div class="ezit-meta"><?php echo esc_html(sprintf(__('Product ID: %s', 'ezit-core'), $d['product_id'])); ?></div>
                <?php endif; ?>
                <?php if (($d['vertical'] ?? '') !== '' && $d['vertical'] !== 'core') : ?>
                    <div class="ezit-meta"><?php echo esc_html(sprintf(__('Vertical: %s', 'ezit-core'), $d['vertical'])); ?></div>
                <?php endif; ?>
            </td>
            <td><?php echo esc_html($d['type']); ?></td>
            <td><?php echo esc_html($d['version'] !== '' ? $d['version'] : '—'); ?></td>
            <td>
                <?php if ($d['state'] === 'active') : ?>
                    <span class="ezit-badge is-active"><?php esc_html_e('Active', 'ezit-core'); ?></span>
                <?php else : ?>
                    <span class="ezit-badge is-neutral"><?php esc_html_e('Installed', 'ezit-core'); ?></span>
                <?php endif; ?>
                <?php if (! $d['registered'] && $d['type'] !== 'plugin' && $d['product_id'] !== '') : ?>
                    <div class="ezit-meta"><?php esc_html_e('Not registered', 'ezit-core'); ?></div>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($d['licensed'] === null) : ?>
                    <span class="ezit-muted">&mdash;</span>
                <?php else : ?>
                    <span class="ezit-badge <?php echo $d['licensed'] ? 'is-active' : 'is-inactive'; ?>">
                        <?php echo $d['licensed'] ? esc_html__('Licensed', 'ezit-core') : esc_html__('Unlicensed', 'ezit-core'); ?>
                    </span>
                    <?php if (! $d['licensed'] && ($d['reason'] ?? '') !== '') : ?>
                        <div class="ezit-meta" style="margin-top:4px;max-width:34ch"><?php echo esc_html($d['reason']); ?></div>
                    <?php endif; ?>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php
// ── Environment data (also feeds the copyable raw report) ────────────
$env = [
    __('Ez IT Core', 'ezit-core')     => 'v' . EZIT_CORE_VERSION,
    __('WordPress', 'ezit-core')      => get_bloginfo('version'),
    __('PHP', 'ezit-core')            => PHP_VERSION,
    __('Active theme', 'ezit-core')   => get_stylesheet(),
    __('License server', 'ezit-core') => $core->api_url(),
    __('Site URL', 'ezit-core')       => home_url(),
];
$mods = [
    'class-ezit-core.php'        => 'EzIT_Core',
    'class-ezit-api.php'         => 'EzIT_API',
    'class-ezit-licensing.php'   => 'EzIT_Licensing',
    'class-ezit-updater.php'     => 'EzIT_Updater',
    'class-ezit-marketplace.php' => 'EzIT_Marketplace',
    'class-ezit-gate.php'        => 'EzIT_Gate',
    'class-ezit-themes.php'      => 'EzIT_Themes',
    'class-ezit-login.php'       => 'EzIT_Login',
    'class-ezit-customizer.php'  => 'EzIT_Customizer',
    'class-ezit-banners.php'     => 'EzIT_Banners',
    'class-ezit-admin.php'       => 'EzIT_Admin',
];
$mod_status = []; // short name => ok|stale|missing
$stale      = false;
foreach ($mods as $file => $m) {
    $short = preg_replace('/^EzIT_/', '', $m);
    if (class_exists($m)) {
        $mod_status[$short] = 'ok';
    } else {
        // File present but class not loaded = the running bootstrap is
        // stale (old ezit-core.php or OPcache), not a missing upload.
        $mod_status[$short] = file_exists(EZIT_CORE_INC . $file) ? 'stale' : 'missing';
        $stale              = true;
    }
}
$glyphs = ['ok' => '✓', 'stale' => '⚠', 'missing' => '✗'];

// Plain-text report for the Copy button / support tickets.
$report = "Ez IT Core environment\n" . str_repeat('-', 40) . "\n";
foreach ($env as $k => $v) {
    $report .= sprintf("%-16s %s\n", $k . ':', $v);
}
$report .= "\nLoaded modules\n" . str_repeat('-', 40) . "\n";
foreach ($mod_status as $name => $st) {
    $report .= sprintf("%s %-14s\n", $glyphs[$st], $name);
}
?>
<div class="ezit-card" data-ezt-anim>
    <div class="ezit-card__head">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M9 9h6v6H9z"/><path d="M9 1v3M15 1v3M9 20v3M15 20v3M1 9h3M1 15h3M20 9h3M20 15h3"/></svg>
        <h3><?php esc_html_e('Environment', 'ezit-core'); ?></h3>
        <button type="button" class="ezit-copy" data-ezit-copy="#ezit-env-raw" style="margin-left:auto"><?php esc_html_e('Copy report', 'ezit-core'); ?></button>
    </div>
    <table class="ezit-table">
        <tbody>
            <?php foreach ($env as $label => $value) : ?>
            <tr><th scope="row"><?php echo esc_html($label); ?></th><td><?php echo esc_html($value); ?></td></tr>
            <?php endforeach; ?>
            <tr><th scope="row"><?php esc_html_e('Loaded modules', 'ezit-core'); ?></th><td>
                <div class="ezit-mods">
                    <?php foreach ($mod_status as $name => $st) : ?>
                    <span class="ezit-mod is-<?php echo esc_attr($st); ?>"><i><?php echo esc_html($glyphs[$st]); ?></i><?php echo esc_html($name); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php if ($stale) : ?>
                <div class="ezit-meta" style="margin-top:6px;color:#b3500a">
                    <?php esc_html_e('⚠ = file exists on disk but wasn’t loaded — the running ezit-core.php is stale (OPcache serving old bytecode, or the upload didn’t replace it). ✗ = file missing from the upload.', 'ezit-core'); ?>
                </div>
                <?php endif; ?>
            </td></tr>
        </tbody>
    </table>
    <details class="ezit-diag">
        <summary><span class="ezit-diag__title"><?php esc_html_e('Raw environment report', 'ezit-core'); ?></span></summary>
        <pre id="ezit-env-raw"><?php echo esc_html($report); ?></pre>
    </details>
</div>
