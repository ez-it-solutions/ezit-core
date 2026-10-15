<?php
/**
 * Settings section — license server connection.
 * Vars in scope: $core
 */
if (! defined('ABSPATH')) {
    exit;
}
?>
<div class="ezit-card">
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
