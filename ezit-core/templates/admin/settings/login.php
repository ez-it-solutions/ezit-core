<?php
/**
 * Settings section — branded wp-login.php appearance.
 * Vars in scope: $core
 */
if (! defined('ABSPATH')) {
    exit;
}

$login = EzIT_Login::settings();
?>
<div class="ezit-card">
    <div class="ezit-card__head">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M3 9h18"/><circle cx="6.5" cy="6" r=".8" fill="currentColor"/><circle cx="9.5" cy="6" r=".8" fill="currentColor"/></svg>
        <h3><?php esc_html_e('Login page', 'ezit-core'); ?></h3>
    </div>
    <p class="ezit-meta" style="margin-bottom:14px">
        <?php esc_html_e('Brands wp-login.php for this site — stored in Core, so it survives theme switches.', 'ezit-core'); ?>
    </p>

    <div class="ezit-field">
        <label for="ezit_login_logo"><?php esc_html_e('Logo image URL', 'ezit-core'); ?></label>
        <input id="ezit_login_logo" name="ezit_login_logo" type="url"
               value="<?php echo esc_attr($login['logo']); ?>"
               placeholder="<?php echo esc_attr(EzIT_Login::logo_url() !== '' ? EzIT_Login::logo_url() : home_url('/wp-content/uploads/logo.svg')); ?>">
        <span class="hint"><?php esc_html_e('Empty = auto-detect: Customizer logo, then the theme’s registered branding / logo asset.', 'ezit-core'); ?></span>
    </div>

    <div class="ezit-field-grid">
        <div class="ezit-field">
            <label for="ezit_login_accent"><?php esc_html_e('Accent color', 'ezit-core'); ?></label>
            <?php $accent_default = EzIT_Login::product_defaults()['accent'] !== '' ? EzIT_Login::product_defaults()['accent'] : '#76b900'; ?>
            <input id="ezit_login_accent" name="ezit_login_accent" type="text" class="ezit-color"
                   data-default-color="<?php echo esc_attr($accent_default); ?>"
                   value="<?php echo esc_attr($login['accent'] !== '' ? $login['accent'] : $accent_default); ?>">
            <span class="hint"><?php esc_html_e('Button, links, and the form’s top bar — defaults to the theme’s registered accent.', 'ezit-core'); ?></span>
        </div>
        <div class="ezit-field">
            <label for="ezit_login_bg_color"><?php esc_html_e('Background color', 'ezit-core'); ?></label>
            <input id="ezit_login_bg_color" name="ezit_login_bg_color" type="text" class="ezit-color"
                   data-default-color="#191919"
                   value="<?php echo esc_attr($login['bg_color'] !== '' ? $login['bg_color'] : '#191919'); ?>">
            <span class="hint"><?php esc_html_e('Page background — also the scrim base under a background image.', 'ezit-core'); ?></span>
        </div>
    </div>

    <div class="ezit-field">
        <label for="ezit_login_bg_image"><?php esc_html_e('Background image URL (optional)', 'ezit-core'); ?></label>
        <input id="ezit_login_bg_image" name="ezit_login_bg_image" type="url"
               value="<?php echo esc_attr($login['bg_image']); ?>"
               placeholder="<?php echo esc_attr(EzIT_Login::product_defaults()['bg_image'] !== '' ? EzIT_Login::product_defaults()['bg_image'] : home_url('/wp-content/uploads/hero.jpg')); ?>">
        <span class="hint"><?php esc_html_e('Full-bleed photo behind the card — empty = the theme’s registered background (hero image).', 'ezit-core'); ?></span>
    </div>

    <div class="ezit-field">
        <label for="ezit_login_message"><?php esc_html_e('Message (optional)', 'ezit-core'); ?></label>
        <textarea id="ezit_login_message" name="ezit_login_message" rows="2"
                  placeholder="<?php esc_attr_e('e.g. Client portal — sign in with the credentials Ez IT gave you.', 'ezit-core'); ?>"><?php echo esc_textarea($login['message']); ?></textarea>
        <span class="hint"><?php esc_html_e('Shown above the form. Blank = no message.', 'ezit-core'); ?></span>
    </div>

    <p class="ezit-meta">
        <a href="<?php echo esc_url(wp_login_url()); ?>" target="_blank" rel="noopener"><?php esc_html_e('Open the login page', 'ezit-core'); ?> ↗</a>
    </p>
</div>
