<?php
/**
 * Settings tab — pg-sethead toolbar + license server config.
 * Vars in scope: $core, $stats
 */
if (! defined('ABSPATH')) {
    exit;
}
?>
<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('ezit_settings', 'ezit_settings_nonce'); ?>
    <input type="hidden" name="action" value="ezit_settings">

    <div class="pg-sethead" data-ezt-anim>
        <span class="pg-sethead__chip" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="#5a8f00" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-1.8-.3 1.6 1.6 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.2a1.6 1.6 0 0 0-1-1.5 1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1.1a1.6 1.6 0 0 0 .3-1.8 1.6 1.6 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.2a1.6 1.6 0 0 0 1.5-1 1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3h0a1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.2a1.6 1.6 0 0 0 1 1.5h0a1.6 1.6 0 0 0 1.8-.3l.1.1a2 2 0 1 1 2.8 2.8l.1.1a1.6 1.6 0 0 0 .3 1.8v0a1.6 1.6 0 0 0 1.5 1h.2a2 2 0 1 1 0 4h-.2a1.6 1.6 0 0 0-1.5 1z"/></svg>
        </span>
        <div class="pg-sethead__text">
            <span class="pg-sethead__eyebrow"><?php esc_html_e('Plugin Settings', 'ezit-core'); ?></span>
            <h2><?php esc_html_e('Settings', 'ezit-core'); ?></h2>
            <p><?php esc_html_e('How Ez IT Core talks to the outside world.', 'ezit-core'); ?></p>
        </div>
        <div class="pg-sethead__side">
            <button type="submit" class="pg-sethead__btn"><?php esc_html_e('Save all', 'ezit-core'); ?></button>
        </div>
    </div>

    <div class="ezit-card" data-ezt-anim>
        <div class="ezit-card__head">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-9-9"/><path d="M21 3 12 12"/><path d="M15 3h6v6"/></svg>
            <h3><?php esc_html_e('License server', 'ezit-core'); ?></h3>
        </div>
        <div class="ezit-field">
            <label for="ezit_api_url"><?php esc_html_e('Activation server URL', 'ezit-core'); ?></label>
            <input id="ezit_api_url" name="ezit_api_url" type="url"
                   value="<?php echo esc_attr(get_option('ezit_api_url', EZIT_API_URL)); ?>">
            <span class="hint"><?php esc_html_e('Where licenses are activated, verified and catalog items fetched. Leave as default unless self-hosting.', 'ezit-core'); ?></span>
        </div>
    </div>

    <?php
    $gate_s    = EzIT_Gate::settings();
    $licensing = $core->licensing();
    $gated     = array_filter($core->products(), static fn ($p) => ! empty($p['gated']) && ($p['product_id'] ?? '') !== '');
    $unlic_gated = 0;
    ?>
    <div class="ezit-card" data-ezt-anim>
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
            <span class="hint"><?php esc_html_e('Enforcement takes the site offline until the license is re-activated — the page stays generic, so visitors never see why. Maintenance mode is voluntary (e.g. while you rebuild) and can show the launch countdown below. Logged-in editors always pass; gate users log in through the page (logout: ?ezit_gate_logout=1).', 'ezit-core'); ?></span>
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

        <div class="ezit-field">
            <label for="ezit_gate_title"><?php esc_html_e('Gate page title', 'ezit-core'); ?></label>
            <input id="ezit_gate_title" name="ezit_gate_title" type="text" value="<?php echo esc_attr($gate_s['uc_title']); ?>">
        </div>
        <div class="ezit-field">
            <label for="ezit_gate_message"><?php esc_html_e('Gate page message', 'ezit-core'); ?></label>
            <textarea id="ezit_gate_message" name="ezit_gate_message" rows="3"><?php echo esc_textarea($gate_s['uc_message']); ?></textarea>
        </div>

        <div class="ezit-field-grid">
            <div class="ezit-field">
                <label for="ezit_gate_accent"><?php esc_html_e('Accent color', 'ezit-core'); ?></label>
                <input id="ezit_gate_accent" name="ezit_gate_accent" type="text" class="ezit-color"
                       data-default-color="#76b900"
                       value="<?php echo esc_attr($gate_s['accent'] !== '' ? $gate_s['accent'] : '#76b900'); ?>">
                <span class="hint"><?php esc_html_e('Click the swatch to choose a color — used for buttons and highlights on the holding page.', 'ezit-core'); ?></span>
            </div>
            <div class="ezit-field">
                <label for="ezit_gate_countdown"><?php esc_html_e('Launch date (countdown)', 'ezit-core'); ?></label>
                <input id="ezit_gate_countdown" name="ezit_gate_countdown" type="datetime-local" value="<?php echo esc_attr($gate_s['countdown'] !== '' ? gmdate('Y-m-d\TH:i', strtotime($gate_s['countdown'])) : ''); ?>">
                <span class="hint"><?php esc_html_e('Empty = hide the timer. Only makes sense for Maintenance mode.', 'ezit-core'); ?></span>
            </div>
        </div>

        <div class="ezit-field">
            <label><?php esc_html_e('Page layout', 'ezit-core'); ?></label>
            <div class="ezit-ucpick">
                <?php foreach (EzIT_Gate::layouts() as $key => $label) :
                    $preview = add_query_arg('ezit_uc_preview', $key, home_url('/'));
                ?>
                <label class="ezit-ucpick__item">
                    <input type="radio" name="ezit_gate_layout" value="<?php echo esc_attr($key); ?>" <?php checked($gate_s['layout'], $key); ?>>
                    <span class="ezit-ucpick__frame">
                        <iframe src="<?php echo esc_url($preview); ?>" loading="lazy" tabindex="-1" aria-hidden="true"></iframe>
                        <span class="ezit-ucpick__tick" aria-hidden="true"><svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12.5 9.5 18 20 6"/></svg></span>
                    </span>
                    <span class="ezit-ucpick__meta">
                        <span class="ezit-ucpick__name"><?php echo esc_html($label); ?></span>
                        <a class="ezit-ucpick__open" href="<?php echo esc_url($preview); ?>" target="_blank" rel="noopener"><?php esc_html_e('Preview', 'ezit-core'); ?> ↗</a>
                    </span>
                </label>
                <?php endforeach; ?>
            </div>
            <span class="hint"><?php esc_html_e('Live previews — they render with the settings above.', 'ezit-core'); ?></span>
        </div>

        <details class="ezit-diag">
            <summary>
                <span class="ezit-diag__title"><?php esc_html_e('Gate diagnostics', 'ezit-core'); ?></span>
                <button type="button" class="ezit-copy" data-ezit-copy><?php esc_html_e('Copy', 'ezit-core'); ?></button>
            </summary>
            <pre><?php
                $gate = EzIT_Gate::instance();
                echo esc_html(print_r($gate ? $gate->report() : ['error' => 'gate not booted'], true));
            ?></pre>
            <p class="ezit-meta">
                <?php esc_html_e('guest_verdict is what an anonymous visitor gets right now. If it says GATED but guests still see the site, a page cache in front of WordPress is serving stale HTML — purge it (host panel, cache plugin, or CDN).', 'ezit-core'); ?>
                <a href="<?php echo esc_url(add_query_arg('ezit_gate_debug', 1, home_url('/'))); ?>" target="_blank" rel="noopener"><?php esc_html_e('Open raw endpoint', 'ezit-core'); ?> ↗</a>
            </p>
        </details>
    </div>
</form>
