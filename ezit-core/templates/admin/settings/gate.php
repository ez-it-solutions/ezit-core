<?php
/**
 * Settings section — site gate mode + gated product status.
 * Vars in scope: $core
 */
if (! defined('ABSPATH')) {
    exit;
}

$gate_s      = EzIT_Gate::settings();
$licensing   = $core->licensing();
$gated       = array_filter($core->products(), static fn ($p) => ! empty($p['gated']) && ($p['product_id'] ?? '') !== '');
$unlic_gated = 0;
?>
<div class="ezit-card">
    <div class="ezit-card__head">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
        <h3><?php esc_html_e('Site gate — Maintenance mode', 'ezit-core'); ?></h3>
    </div>

    <div class="ezit-field">
        <label for="ezit_gate_mode"><?php esc_html_e('Mode', 'ezit-core'); ?></label>
        <select id="ezit_gate_mode" name="ezit_gate_mode">
            <option value="off" <?php selected($gate_s['mode'], 'off'); ?>><?php esc_html_e('Off — site is open to everyone', 'ezit-core'); ?></option>
            <option value="licensed" <?php selected($gate_s['mode'], 'licensed'); ?>><?php esc_html_e('License enforcement — under-construction page when unlicensed', 'ezit-core'); ?></option>
            <option value="maintenance" <?php selected($gate_s['mode'], 'maintenance'); ?>><?php esc_html_e('Maintenance mode — under-construction page for everyone', 'ezit-core'); ?></option>
        </select>
        <span class="hint"><?php esc_html_e('Enforcement takes the site offline until the license is re-activated — the page stays generic, so visitors never see why. Maintenance mode is voluntary (e.g. while you rebuild) and can show the launch countdown on the Holding page tab. Logged-in editors always pass; gate users log in through the page (logout: ?ezit_gate_logout=1).', 'ezit-core'); ?></span>
    </div>

    <?php if ($gated) : ?>
    <ul class="ezit-gate-list">
        <?php foreach ($gated as $slug => $p) :
            $ok = $licensing->is_active($slug);
            $unlic_gated += $ok ? 0 : 1;
        ?>
        <li>
            <span class="ezit-badge <?php echo $ok ? 'is-active' : 'is-inactive'; ?>"><?php echo $ok ? esc_html__('Licensed', 'ezit-core') : esc_html__('Unlicensed', 'ezit-core'); ?></span>
            <?php echo esc_html($p['name']); ?>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php if ($gate_s['mode'] === 'licensed' && $unlic_gated > 0) : ?>
    <div class="ezit-flash is-err"><p><?php esc_html_e('Enforcement is armed but a product is unlicensed — visitors currently see the under-construction page.', 'ezit-core'); ?></p></div>
    <?php elseif ($gate_s['mode'] === 'maintenance') : ?>
    <div class="ezit-flash is-ok"><p><?php esc_html_e('Maintenance mode is on — visitors see the under-construction page until you switch modes.', 'ezit-core'); ?></p></div>
    <?php endif; ?>
    <?php else : ?>
    <p class="ezit-muted"><?php esc_html_e('No gated products registered — license enforcement has nothing to check.', 'ezit-core'); ?></p>
    <?php endif; ?>
</div>
