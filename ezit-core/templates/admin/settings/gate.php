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

<?php
// Emergency override — recovery-code-protected force open/closed.
$ov         = EzIT_Gate::override();
$srv_state  = $licensing->server_state();
$has_digest = false;
foreach ($core->products() as $slug => $p) {
    if ($licensing->recovery_digest($slug)) {
        $has_digest = true;
        break;
    }
}
?>
<div class="ezit-card">
    <div class="ezit-card__head">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4m0 4h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
        <h3><?php esc_html_e('Emergency override', 'ezit-core'); ?></h3>
    </div>

    <div class="ezit-field">
        <label><?php esc_html_e('License server', 'ezit-core'); ?></label>
        <span class="ezit-badge <?php echo $srv_state === 'up' ? 'is-active' : 'is-blocked'; ?>">
            <?php
            echo esc_html(match ($srv_state) {
                'maintenance' => __('Maintenance mode — answering SERVER_UNAVAILABLE', 'ezit-core'),
                'down'        => __('Unreachable — keeping last verdict', 'ezit-core'),
                default       => __('Reachable', 'ezit-core'),
            });
            ?>
        </span>
        <span class="hint"><?php esc_html_e('During an outage the site keeps its last verdict and re-checks on the server\'s Retry-After schedule. The override below is the escape hatch if you need to force the site open (or closed) right now.', 'ezit-core'); ?></span>
    </div>

    <div class="ezit-field">
        <label for="ezit_ov_mode"><?php esc_html_e('Override', 'ezit-core'); ?></label>
        <div class="ezit-ov-row">
            <select id="ezit_ov_mode" name="ezit_ov_mode">
                <option value="off" <?php selected($ov['mode'], 'off'); ?>><?php esc_html_e('Normal — gate follows license checks', 'ezit-core'); ?></option>
                <option value="open" <?php selected($ov['mode'], 'open'); ?>><?php esc_html_e('Force OPEN — everyone passes', 'ezit-core'); ?></option>
                <option value="closed" <?php selected($ov['mode'], 'closed'); ?>><?php esc_html_e('Force CLOSED — everyone gated', 'ezit-core'); ?></option>
            </select>
            <select id="ezit_ov_ttl" name="ezit_ov_ttl">
                <?php
                // Show the armed override's remaining life, snapped to the
                // nearest offered window — re-saving keeps reality honest.
                $ov_ttl_opts = [4 => __('for 4 hours', 'ezit-core'), 24 => __('for 24 hours', 'ezit-core'), 72 => __('for 3 days', 'ezit-core'), 168 => __('for 7 days', 'ezit-core')];
                $ov_ttl_sel  = 24;
                if ($ov['mode'] !== 'off') {
                    $remaining_h = max(0, ((int) $ov['until'] - time()) / HOUR_IN_SECONDS);
                    $ov_ttl_sel  = array_reduce(array_keys($ov_ttl_opts),
                        static fn ($best, $h) => abs($h - $remaining_h) < abs($best - $remaining_h) ? $h : $best, 24);
                }
                foreach ($ov_ttl_opts as $h => $label) : ?>
                <option value="<?php echo esc_attr($h); ?>" <?php selected($ov_ttl_sel, $h); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <span class="hint"><?php esc_html_e('Overrides always auto-expire — you can\'t forget a force-open site. Select Normal and save to clear early (no code needed).', 'ezit-core'); ?></span>
    </div>

    <div class="ezit-field">
        <label for="ezit_ov_code"><?php esc_html_e('Recovery code', 'ezit-core'); ?></label>
        <input id="ezit_ov_code" name="ezit_ov_code" type="text" autocomplete="off" placeholder="XXXX-XXXX-XXXX-XXXX">
        <span class="hint">
            <?php esc_html_e('Required to arm an override — validated entirely offline against a digest inside the signed license certificate, so it works while the server is down. Find it on the license server dashboard under Products → Recovery code.', 'ezit-core'); ?>
            <?php if (! $has_digest) : ?>
                <br><strong style="color:#b3483a"><?php esc_html_e('No recovery code on file yet — the site needs one successful verify against an updated license server first.', 'ezit-core'); ?></strong>
            <?php endif; ?>
        </span>
    </div>

    <?php if ($ov['mode'] !== 'off') : ?>
    <div class="ezit-flash is-err"><p>
        <?php
        printf(
            /* translators: 1: open|closed, 2: expiry time, 3: user */
            esc_html__('Override active: gate force-%1$s until %2$s (set by %3$s).', 'ezit-core'),
            esc_html(strtoupper($ov['mode'])),
            esc_html(wp_date('M j, g:i a', (int) $ov['until'])),
            esc_html($ov['by'] ?: 'admin')
        );
        ?>
    </p></div>
    <?php endif; ?>
</div>
