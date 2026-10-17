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
    $lic       = $licensing->license($slug) ?: [];
?>
    <div class="ezit-card ezit-license" data-ezt-anim>
        <div class="ezit-license__head">
            <h3><?php echo esc_html($p['name']); ?></h3>
            <span class="ezit-badge <?php echo $is_active ? 'is-active' : 'is-inactive'; ?>">
                <?php echo $is_active ? esc_html__('Active', 'ezit-core') : esc_html__('Inactive', 'ezit-core'); ?>
            </span>
        </div>
        <p class="ezit-meta"><?php echo esc_html(($p['type'] ?? 'theme') . ' · v' . ($p['version'] ?: '—')); ?></p>
        <p class="ezit-meta" style="font-family:monospace;font-size:11px">
            <?php
            // Config identity — masked keys so a stale deploy or a
            // dashboard mismatch is visible without exposing the secret.
            $mask = static function (?string $v): string {
                $v = (string) $v;
                return $v === '' ? '—(not set)—'
                    : esc_html(substr($v, 0, 4)) . '…' . esc_html(substr($v, -4));
            };
            printf(
                'product %s · key %s',
                esc_html($p['product_id']),
                $mask($p['api_key'] ?? '')
            );
            ?>
        </p>

        <?php if ($is_active) :
            // Cert payload → seats, domain binding, expiry, licensee.
            // Informational decode only — the HMAC is verified server-side.
            $cert = EzIT_Licensing::cert_payload((string) ($lic['license_file'] ?? '')) ?: [];
            $seat_used = $cert['used_activations'] ?? $cert['activations_used'] ?? $cert['lic_use'] ?? null;
            $seat_max  = $cert['max_activations'] ?? $cert['max_usage'] ?? null;
            $details   = array_filter([
                __('Licensed to', 'ezit-core')  => $cert['client_name'] ?? $cert['registered_name'] ?? $cert['licensed_to'] ?? '',
                __('Domain', 'ezit-core')       => $cert['domain'] ?? '',
                __('Expires', 'ezit-core')      => $cert['expires_at'] ?? $cert['expiry_date'] ?? '',
                __('Activations', 'ezit-core')  => ($seat_used !== null || $seat_max !== null)
                                                    ? trim((string) ($seat_used ?? '?') . ' / ' . (string) ($seat_max ?? '∞')) : '',
                __('Last verified', 'ezit-core') => ! empty($lic['checked_at'])
                                                    ? human_time_diff((int) $lic['checked_at']) . ' ' . __('ago', 'ezit-core') : '',
            ]);
            if ($details) : ?>
            <dl class="ezit-meta" style="display:grid;grid-template-columns:auto 1fr;gap:2px 14px;margin:4px 0 10px">
                <?php foreach ($details as $k => $v) : ?>
                <dt style="font-weight:600;color:#787c82"><?php echo esc_html($k); ?></dt>
                <dd style="margin:0"><?php echo esc_html((string) $v); ?></dd>
                <?php endforeach; ?>
            </dl>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($is_active) : ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('ezit_license', 'ezit_license_nonce'); ?>
            <input type="hidden" name="action" value="ezit_deactivate">
            <input type="hidden" name="slug" value="<?php echo esc_attr($slug); ?>">
            <button class="ezit-btn ezit-btn--ghost"><?php esc_html_e('Deactivate', 'ezit-core'); ?></button>
        </form>
        <?php else :
            $revoked     = ! empty($lic['revoked']);
            $reason_code = (string) ($lic['reason_code'] ?? '');
            $blocked     = $revoked || $reason_code !== '';
            // Withdrawn product — nothing the admin can re-enter will
            // activate, so the form submits are disabled outright.
            $retired     = $reason_code === 'PRODUCT_DISABLED';
        ?>
        <?php if ($blocked) : ?>
        <div class="ezit-notice <?php echo $retired ? 'is-warn' : 'is-err'; ?>">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4m0 4h.01"/></svg>
            <p><?php echo esc_html(EzIT_Licensing::blocked_label($lic)); ?></p>
        </div>
        <?php endif; ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"<?php echo $revoked && ! $retired ? ' data-requires-change' : ''; ?>>
            <?php wp_nonce_field('ezit_license', 'ezit_license_nonce'); ?>
            <input type="hidden" name="action" value="ezit_activate">
            <input type="hidden" name="slug" value="<?php echo esc_attr($slug); ?>">
            <div class="ezit-field">
                <label for="ezit-code-<?php echo esc_attr($slug); ?>"><?php esc_html_e('License code', 'ezit-core'); ?></label>
                <input id="ezit-code-<?php echo esc_attr($slug); ?>" type="text" name="license_code"
                       value="<?php echo esc_attr($p['license_code'] ?? ''); ?>"<?php echo $retired ? ' disabled' : ' required'; ?>>
            </div>
            <div class="ezit-field">
                <label for="ezit-name-<?php echo esc_attr($slug); ?>"><?php esc_html_e('License name (optional)', 'ezit-core'); ?></label>
                <input id="ezit-name-<?php echo esc_attr($slug); ?>" type="text" name="client_name"<?php echo $retired ? ' disabled' : ''; ?>>
                <span class="hint"><?php esc_html_e('Label shown as "Licensed to" on the license server — e.g. the client or site name.', 'ezit-core'); ?></span>
            </div>
            <button class="ezit-btn"<?php echo ($revoked || $retired) ? ' disabled' : ''; ?>><?php esc_html_e('Activate license', 'ezit-core'); ?></button>
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
