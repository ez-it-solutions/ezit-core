<?php
/**
 * Settings section — Alerts & banners: theme-declared slots.
 * Vars in scope: $core
 */
if (! defined('ABSPATH')) {
    exit;
}

$slots = EzIT_Alerts::declared_slots();
$saved = EzIT_Alerts::saved();
?>
<div class="ezit-card">
    <div class="ezit-card__head">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
        <h3><?php esc_html_e('Alerts & banners', 'ezit-core'); ?></h3>
    </div>
    <p class="ezit-meta" style="margin-bottom:16px">
        <?php esc_html_e('Notice bars the active theme declares — toggle them, edit copy, link them anywhere, or schedule a window.', 'ezit-core'); ?>
    </p>

    <?php if ($slots === []) : ?>
        <p class="ezit-meta"><?php esc_html_e('The active theme hasn’t declared any alert slots.', 'ezit-core'); ?></p>
    <?php else : foreach ($slots as $key => $slot) :
        $cfg = array_merge($slot['defaults'] ?? [], $saved[$key] ?? []);
        // Never-saved slots start ON — the theme shipped showing them.
        $on = isset($saved[$key]) ? ! empty($saved[$key]['on']) : true;
        $to_dt = static function ($v) { return $v !== '' && strtotime((string) $v) ? gmdate('Y-m-d\TH:i', strtotime((string) $v)) : ''; };
    ?>
    <?php $mode = ($cfg['mode'] ?? 'structured') === 'custom' ? 'custom' : 'structured'; ?>
    <fieldset class="ezit-card" style="margin-bottom:18px">
        <div class="ezit-card__head" style="margin-bottom:10px">
            <h4 style="margin:0;font-size:14.5px"><?php echo esc_html($slot['label'] ?? $key); ?></h4>
            <span class="ezit-meta" style="margin-left:auto"><?php echo esc_html($slot['product']); ?></span>
        </div>

        <div class="ezit-field-grid" style="align-items:end">
            <div class="ezit-field ezit-field--check" style="margin-bottom:14px">
                <label>
                    <input type="checkbox" name="ezit_slots[<?php echo esc_attr($key); ?>][on]" value="1" <?php checked($on); ?>>
                    <?php esc_html_e('Show this banner', 'ezit-core'); ?>
                </label>
            </div>
            <div class="ezit-field">
                <label><?php esc_html_e('Layout', 'ezit-core'); ?></label>
                <select class="ezit-slot-mode" name="ezit_slots[<?php echo esc_attr($key); ?>][mode]">
                    <option value="structured" <?php selected($mode, 'structured'); ?>><?php esc_html_e('Structured — label, detail & button', 'ezit-core'); ?></option>
                    <option value="custom" <?php selected($mode, 'custom'); ?>><?php esc_html_e('Custom — free-form content & colors', 'ezit-core'); ?></option>
                </select>
            </div>
        </div>

        <div class="ezit-slot-pane" data-pane="structured"<?php echo $mode === 'custom' ? ' hidden' : ''; ?>>
            <div class="ezit-field-grid">
                <div class="ezit-field">
                    <label><?php esc_html_e('Label', 'ezit-core'); ?></label>
                    <input type="text" name="ezit_slots[<?php echo esc_attr($key); ?>][label]" value="<?php echo esc_attr($cfg['label'] ?? ''); ?>">
                </div>
                <div class="ezit-field">
                    <label><?php esc_html_e('Detail', 'ezit-core'); ?></label>
                    <input type="text" name="ezit_slots[<?php echo esc_attr($key); ?>][detail]" value="<?php echo esc_attr($cfg['detail'] ?? ''); ?>"
                           placeholder="<?php esc_attr_e('e.g. Oct 15 – Dec 7', 'ezit-core'); ?>">
                </div>
            </div>
            <div class="ezit-field-grid">
                <div class="ezit-field">
                    <label><?php esc_html_e('Call-to-action text', 'ezit-core'); ?></label>
                    <input type="text" name="ezit_slots[<?php echo esc_attr($key); ?>][cta]" value="<?php echo esc_attr($cfg['cta'] ?? ''); ?>">
                </div>
                <div class="ezit-field">
                    <label><?php esc_html_e('Link URL', 'ezit-core'); ?></label>
                    <input type="text" name="ezit_slots[<?php echo esc_attr($key); ?>][url]" value="<?php echo esc_attr($cfg['url'] ?? '') ?>"
                           placeholder="/contact/ or https://…">
                </div>
            </div>
        </div>

        <div class="ezit-slot-pane" data-pane="custom"<?php echo $mode === 'custom' ? '' : ' hidden'; ?>>
            <div class="ezit-field">
                <label><?php esc_html_e('Content', 'ezit-core'); ?></label>
                <?php
                wp_editor(
                    (string) ($cfg['custom'] ?? ''),
                    'ezit-slot-custom-' . sanitize_key($key),
                    [
                        'textarea_name' => "ezit_slots[$key][custom]",
                        'textarea_rows' => 3,
                        'media_buttons' => false,
                        'teeny'         => true,
                        'quicktags'     => true,
                    ]
                );
                ?>
                <span class="hint"><?php esc_html_e('Rendered as-is in the bar — include your own link if it should click through; the whole bar is not wrapped in an anchor in this mode.', 'ezit-core'); ?></span>
            </div>
            <div class="ezit-field-grid">
                <div class="ezit-field">
                    <label><?php esc_html_e('Background', 'ezit-core'); ?></label>
                    <input type="color" name="ezit_slots[<?php echo esc_attr($key); ?>][bg]" value="<?php echo esc_attr($cfg['bg'] ?? '#526335'); ?>">
                </div>
                <div class="ezit-field">
                    <label><?php esc_html_e('Text color', 'ezit-core'); ?></label>
                    <input type="color" name="ezit_slots[<?php echo esc_attr($key); ?>][color]" value="<?php echo esc_attr($cfg['color'] ?? '#ffffff'); ?>">
                </div>
            </div>
        </div>
        <div class="ezit-field-grid">
            <div class="ezit-field">
                <label><?php esc_html_e('Show from (optional)', 'ezit-core'); ?></label>
                <input type="datetime-local" name="ezit_slots[<?php echo esc_attr($key); ?>][start]" value="<?php echo esc_attr($to_dt($cfg['start'] ?? '')); ?>">
            </div>
            <div class="ezit-field">
                <label><?php esc_html_e('Hide after (optional)', 'ezit-core'); ?></label>
                <input type="datetime-local" name="ezit_slots[<?php echo esc_attr($key); ?>][end]" value="<?php echo esc_attr($to_dt($cfg['end'] ?? '')); ?>">
            </div>
        </div>
        <span class="hint"><?php esc_html_e('Empty schedule fields = always visible while the toggle is on.', 'ezit-core'); ?></span>
    </fieldset>
    <?php endforeach; endif; ?>

    <?php $server_state = $core->licensing()->server_state(); ?>
    <fieldset class="ezit-card" style="margin-bottom:0">
        <div class="ezit-card__head" style="margin-bottom:10px">
            <h4 style="margin:0;font-size:14.5px"><?php esc_html_e('License-server outage banner', 'ezit-core'); ?></h4>
            <span class="ezit-badge <?php echo $server_state === 'up' ? 'is-active' : 'is-inactive'; ?>" style="margin-left:auto">
                <?php echo $server_state === 'up' ? esc_html__('Standby', 'ezit-core') : esc_html__('Showing', 'ezit-core'); ?>
            </span>
        </div>
        <div class="ezit-field ezit-field--check" style="margin-bottom:10px">
            <label>
                <input type="checkbox" name="ezit_outage_guests" value="1" <?php checked(get_option('ezit_outage_banner_guests')); ?>>
                <?php esc_html_e('Show the banner to guests too', 'ezit-core'); ?>
            </label>
        </div>
        <?php
        $guest_mode   = (string) get_option('ezit_outage_guest_msg', 'default');
        $guest_custom = (string) get_option('ezit_outage_guest_msg_custom', '');
        ?>
        <div class="ezit-field">
            <label for="ezit_outage_msg_mode"><?php esc_html_e('Guest message', 'ezit-core'); ?></label>
            <select id="ezit_outage_msg_mode" name="ezit_outage_msg_mode" style="max-width:100%">
                <?php foreach (EzIT_Gate::outage_guest_presets() as $k => $text) : ?>
                <option value="<?php echo esc_attr($k); ?>"
                        data-text="<?php echo esc_attr($text); ?>"
                        <?php selected($guest_mode, $k); ?>>
                    <?php echo esc_html($text); ?>
                </option>
                <?php endforeach; ?>
                <?php foreach ($slots as $key => $slot) :
                    $slot_cfg  = array_merge($slot['defaults'] ?? [], $saved[$key] ?? []);
                    $slot_text = ($slot_cfg['mode'] ?? '') === 'custom' && trim((string) ($slot_cfg['custom'] ?? '')) !== ''
                        ? wp_strip_all_tags((string) $slot_cfg['custom'])
                        : trim(implode(' — ', array_filter([
                            (string) ($slot_cfg['label'] ?? ''),
                            (string) ($slot_cfg['detail'] ?? ''),
                        ])));
                ?>
                <option value="slot:<?php echo esc_attr($key); ?>"
                        data-text="<?php echo esc_attr($slot_text); ?>"
                        <?php selected($guest_mode, 'slot:' . $key); ?>>
                    <?php echo esc_html(sprintf(__('Use the "%s" banner text', 'ezit-core'), $slot['label'] ?? $key)); ?>
                </option>
                <?php endforeach; ?>
                <option value="custom" <?php selected($guest_mode, 'custom'); ?>>
                    <?php esc_html_e('Custom message…', 'ezit-core'); ?>
                </option>
            </select>
        </div>
        <div class="ezit-field" id="ezit-outage-custom-row"<?php echo $guest_mode === 'custom' ? '' : ' style="display:none"'; ?>>
            <label for="ezit_outage_msg_custom"><?php esc_html_e('Custom message', 'ezit-core'); ?></label>
            <input type="text" id="ezit_outage_msg_custom" name="ezit_outage_msg_custom"
                   value="<?php echo esc_attr($guest_custom); ?>"
                   placeholder="<?php esc_attr_e('This site is undergoing maintenance — content may be temporarily limited.', 'ezit-core'); ?>">
        </div>
        <p class="ezit-meta" style="margin:0">
            <?php esc_html_e('Automatic. While the license server is unreachable or in maintenance, a fixed red stripe pins under the admin bar on every front-end page for logged-in staff (editors and admins), telling them the site is running on its last verified license. With the box checked, guests and gate users also see the chosen notice — it never mentions licensing. Picking "Custom message" pre-fills the field with the option you had selected, as a starting point. wp-admin screens show the equivalent notice automatically.', 'ezit-core'); ?>
        </p>
<script>
(function () {
    var sel = document.getElementById('ezit_outage_msg_mode');
    var row = document.getElementById('ezit-outage-custom-row');
    var inp = document.getElementById('ezit_outage_msg_custom');
    if (!sel || !row || !inp) return;
    var prev = sel.value;
    sel.addEventListener('change', function () {
        row.style.display = sel.value === 'custom' ? '' : 'none';
        // Seed the custom field with whatever was selected before, so
        // the user starts from real copy instead of a blank box.
        if (sel.value === 'custom' && inp.value.trim() === '') {
            var prevOpt = sel.querySelector('option[value="' + CSS.escape(prev) + '"]');
            if (prevOpt && prevOpt.dataset.text) inp.value = prevOpt.dataset.text;
        }
        prev = sel.value;
    });
})();
</script>
    </fieldset>
</div>
<script>
(function () {
    document.querySelectorAll('.ezit-slot-mode').forEach(function (sel) {
        sel.addEventListener('change', function () {
            var fs = sel.closest('fieldset');
            if (!fs) return;
            fs.querySelectorAll('.ezit-slot-pane').forEach(function (pane) {
                pane.hidden = pane.dataset.pane !== sel.value;
            });
        });
    });
})();
</script>
