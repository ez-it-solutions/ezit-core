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
    <fieldset class="ezit-card" style="margin-bottom:18px">
        <div class="ezit-card__head" style="margin-bottom:10px">
            <h4 style="margin:0;font-size:14.5px"><?php echo esc_html($slot['label'] ?? $key); ?></h4>
            <span class="ezit-meta" style="margin-left:auto"><?php echo esc_html($slot['product']); ?></span>
        </div>

        <div class="ezit-field ezit-field--check">
            <label>
                <input type="checkbox" name="ezit_slots[<?php echo esc_attr($key); ?>][on]" value="1" <?php checked($on); ?>>
                <?php esc_html_e('Show this banner', 'ezit-core'); ?>
            </label>
        </div>
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
</div>
