<?php
/**
 * Settings section — under-construction / holding page appearance.
 * Vars in scope: $core
 */
if (! defined('ABSPATH')) {
    exit;
}

$gate_s = EzIT_Gate::settings();
?>
<div class="ezit-card">
    <div class="ezit-card__head">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18"/><path d="M8 14h8M8 17h5"/></svg>
        <h3><?php esc_html_e('Holding page', 'ezit-core'); ?></h3>
    </div>

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
